<?php
namespace SockSpecsCN\Commerce;

defined('ABSPATH') || exit;

abstract class Gateway extends \WC_Payment_Gateway {
    public function __construct(string $id, string $title, string $description) {
        $this->id = $id; $this->method_title = $title; $this->title = $title;
        $this->description = $description;
        $this->method_description = $description . ' 正式接入默认关闭，需先配置私有密钥、平台域名及权限。退款暂在商户平台处理。';
        $this->has_fields = false; $this->supports = ['products'];
        $this->form_fields = ['enabled' => ['title' => '启用', 'type' => 'checkbox', 'label' => '启用' . $title, 'default' => 'no']];
        $this->init_settings(); $this->enabled = $this->get_option('enabled', 'no');
        add_action('woocommerce_update_options_payment_gateways_' . $id, [$this, 'process_admin_options']);
        add_action('woocommerce_receipt_' . $id, [$this, 'receipt']);
    }
    public function is_available() {
        return parent::is_available() && get_woocommerce_currency() === 'CNY' && gateway_ready($this->id);
    }
    public function process_payment($order_id) {
        try {
            if (!$this->is_available()) { throw new \RuntimeException('Gateway disabled'); }
            locked((int) $order_id, function () use ($order_id): void {
                $order = wc_get_order($order_id);
                if (!$order || !$order->needs_payment() || $order->get_currency() !== 'CNY' || $order->get_payment_method() !== $this->id) { throw new \RuntimeException('Order not payable'); }
                $amount = minor_units($order->get_total()); if ($amount <= 0) { throw new \RuntimeException('Invalid amount'); }
                $a = $order->get_meta(ATTEMPT_META);
                $mode = $this->id === 'sscn_wechat' ? payment_mode() : (wp_is_mobile() ? 'wap' : 'page');
                if (is_array($a) && !empty($a['trade'])) {
                    if ($a['method'] !== $this->id || $a['amount'] !== $amount || $a['mode'] !== $mode || time() > $a['created'] + 3500) { throw new \RuntimeException('Attempt changed or expired'); }
                } else {
                    $a = ['trade' => 'SCN' . $order_id . 'X' . bin2hex(random_bytes(8)), 'amount' => $amount, 'method' => $this->id, 'mode' => $mode, 'created' => time()];
                    $order->update_meta_data(ATTEMPT_META, $a); $order->save();
                }
                if (!wp_next_scheduled('sscn_payment_reconcile', [(int) $order_id])) { wp_schedule_single_event(time() + 300, 'sscn_payment_reconcile', [(int) $order_id]); }
            });
            WC()->cart?->empty_cart();
            return ['result' => 'success', 'redirect' => wc_get_order($order_id)->get_checkout_payment_url(true)];
        } catch (\Throwable $e) {
            log_failure('start-payment', (int) $order_id);
            wc_add_notice('暂时无法发起支付。请使用原支付方式重试；如订单已过期，请联系客服。', 'error');
            return ['result' => 'failure'];
        }
    }
    public function receipt($order_id): void {
        nocache_headers();
        $order = wc_get_order($order_id);
        $key = isset($_GET['key']) && is_string($_GET['key']) ? wp_unslash($_GET['key']) : '';
        if (!$order || !$order->needs_payment() || $order->get_payment_method() !== $this->id ||
            !$key || !hash_equals($order->get_order_key(), $key)) { return; }
        $a = $order->get_meta(ATTEMPT_META);
        if (!is_array($a) || ($a['method'] ?? '') !== $this->id || ($a['amount'] ?? -1) !== minor_units($order->get_total())) { return; }
        if (!$this->is_available() || time() > $a['created'] + 3500) { echo '<p>此支付已过期或暂不可用，请联系客服。</p>'; return; }
        if ($this->id === 'sscn_wechat' && $a['mode'] !== payment_mode()) { echo '<p>请在发起订单的浏览器中继续付款。</p>'; return; }
        if ($this->id === 'sscn_wechat' && $a['mode'] === 'jsapi' && !customer_openid()) {
            echo '<p>请先授权微信身份，再继续支付。</p><a class="button" href="' . esc_url(login_url($order->get_checkout_payment_url(true))) . '">微信授权并继续付款</a>'; return;
        }
        try {
            echo '<h2>' . esc_html($this->title) . '</h2><p>' . wp_kses_post($order->get_formatted_order_total()) . '</p>';
            $return = add_query_arg(['order_id' => $order_id, 'key' => $order->get_order_key()], api_url('sscn_return'));
            if ($this->id === 'sscn_alipay') {
                echo Providers::production()->alipay_form($a['trade'], $a['amount'], api_url($this->id), $return, $a['mode'] === 'wap'); // Trusted SDK signed form.
            } else {
                $a = locked((int) $order_id, function () use ($order_id): array {
                    $current = wc_get_order($order_id); $a = $current->get_meta(ATTEMPT_META);
                    if (!$current->needs_payment() || $current->get_payment_method() !== $this->id || $a['amount'] !== minor_units($current->get_total())) { throw new \RuntimeException('Order changed'); }
                    if (empty($a['provider'])) {
                        $p = Providers::production();
                        $a['provider'] = $a['mode'] === 'native' ? ['code_url' => $p->wechat_create($a['trade'], $a['amount'], api_url($this->id), $a['created'] + 3600)]
                            : $p->wechat_mobile($a['mode'], $a['trade'], $a['amount'], api_url($this->id), $a['created'] + 3600, customer_openid(), payer_client_ip());
                        if ($a['mode'] === 'jsapi') { $a['payer'] = customer_openid(); }
                        $current->update_meta_data(ATTEMPT_META, $a); $current->save();
                    }
                    if ($a['mode'] === 'jsapi' && ($a['payer'] ?? '') !== customer_openid()) { throw new \RuntimeException('Payer changed'); }
                    return $a;
                });
                if ($a['mode'] === 'native') {
                    $renderer = new \BaconQrCode\Renderer\ImageRenderer(new \BaconQrCode\Renderer\RendererStyle\RendererStyle(280), new \BaconQrCode\Renderer\Image\SvgImageBackEnd());
                    $svg = (new \BaconQrCode\Writer($renderer))->writeString($a['provider']['code_url']);
                    echo '<p>请使用微信扫描二维码支付。支付成功后，本页将自动更新。</p><img width="280" height="280" alt="微信支付二维码" src="data:image/svg+xml;base64,' . esc_attr(base64_encode($svg)) . '">';
                } elseif ($a['mode'] === 'h5') {
                    // H5 links expire quickly; an existing prepay is not silently recreated.
                    echo '<a class="button" href="' . esc_url(add_query_arg('redirect_url', $return, $a['provider']['h5_url'])) . '">打开微信支付</a>';
                } else {
                    echo '<button type="button" id="sscn-jsapi-pay" class="button">微信支付</button><p id="sscn-payment-message" aria-live="polite"></p>';
                    wp_enqueue_script('sscn-jsapi', plugins_url('../assets/jsapi.js', __FILE__), [], '1.0.0', true);
                    wp_localize_script('sscn-jsapi', 'sscnJsapi', Providers::production()->jsapi_parameters($a['provider']['prepay_id']));
                }
            }
            wp_enqueue_script('sscn-payment-status', plugins_url('../assets/status.js', __FILE__), [], '1.0.0', true);
            wp_localize_script('sscn-payment-status', 'sscnPayment', ['url' => admin_url('admin-ajax.php'), 'order_id' => $order_id, 'key' => $order->get_order_key(), 'expires' => $a['created'] + 3600]);
        } catch (\Throwable $e) { log_failure('render-payment', (int) $order_id); echo '<p>暂时无法显示支付页面，请刷新重试或联系客服。</p>'; }
    }
}
final class AlipayGateway extends Gateway {
    public function __construct() { parent::__construct('sscn_alipay', '支付宝', '前往支付宝收银台支付。'); }
}
final class WechatGateway extends Gateway {
    public function __construct() { parent::__construct('sscn_wechat', '微信支付', '使用微信安全支付。'); }
}
