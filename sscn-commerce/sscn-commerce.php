<?php
/**
 * Plugin Name: SockSpecs 国内登录与支付
 * Description: Direct CNY Alipay web checkout and WeChat Native QR payments using SockSpecs's existing merchant accounts.
 * Version: 1.0.0
 * Requires PHP: 8.3
 * Requires Plugins: woocommerce
 */
namespace SockSpecsCN\Commerce;

defined('ABSPATH') || exit;
// Keep SDK fixtures, command-line utilities, and dependencies outside the web root.
require_once __DIR__ . '/includes/config.php';
if (!domestic_site()) { return; }
$autoload = defined('SSCN_COMMERCE_AUTOLOAD') ? SSCN_COMMERCE_AUTOLOAD : '/srv/sockspecs-cn/current/commerce-libraries/vendor/autoload.php';
if (is_readable($autoload)) { require_once $autoload; }
require_once __DIR__ . '/includes/login.php';
require_once __DIR__ . '/includes/providers.php';
require_once __DIR__ . '/includes/wear-feedback.php';
require_once __DIR__ . '/includes/selection-cart.php';
require_once __DIR__ . '/includes/selection-draft.php';
require_once __DIR__ . '/includes/selection-ui.php';
require_once __DIR__ . '/includes/color-substitution.php';

const ATTEMPT_META = '_sscn_payment_attempt';

function api_url(string $name): string {
    // Provider callbacks must bypass storefront language redirects.
    return add_query_arg('wc-api', $name, home_url('/'));
}

function order_url($order): string { return $order->get_checkout_order_received_url(); }

function locked(int $id, callable $callback) {
    global $wpdb;
    $key = 'sscn_pay_' . $wpdb->prefix . $id;
    if ((int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)', $key)) !== 1) {
        throw new \RuntimeException('Order payment is busy');
    }
    try { return $callback(); }
    finally { $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $key)); }
}

function log_failure(string $stage, ?int $id = null): void {
    // SDK exception messages can contain request bodies or credentials. Never log them.
    wc_get_logger()->error('Payment operation failed: ' . $stage, ['source' => 'sscn-payments', 'order_id' => $id]);
}

function complete_payment(string $method, array $event): void {
    if (!preg_match('/^SCN([1-9][0-9]{0,11})X[a-f0-9]{16}$/D', $event['trade'] ?? '', $match)) {
        throw new \RuntimeException('Unrecognized payment order');
    }
    $id = (int) $match[1];
    locked($id, static function () use ($id, $method, $event): void {
        $order = wc_get_order($id);
        if (!$order) { throw new \RuntimeException('Order not found'); }
        $attempt = $order->get_meta(ATTEMPT_META);
        if (!is_array($attempt)) { throw new \RuntimeException('No payment attempt'); }
        validate_event($event, $attempt, $method, $order->get_currency(), minor_units($order->get_total()));
        if ($order->get_payment_method() !== $method) { throw new \RuntimeException('Payment method changed'); }
        if ($order->get_transaction_id()) {
            if ($order->get_transaction_id() !== $event['transaction']) { throw new \RuntimeException('Conflicting transaction'); }
            return; // Idempotent, including retries after an order was refunded.
        }
        if ($order->has_status(['refunded', 'trash', 'cancelled', 'failed'])) { throw new \RuntimeException('Order requires manual review'); }
        if (!$order->payment_complete($event['transaction'])) { throw new \RuntimeException('Could not complete payment'); }
        $order->add_order_note('已通过支付平台验签及金额核对确认收款。');
        wp_clear_scheduled_hook('sscn_payment_reconcile', [$id]);
    });
}

function reconcile(int $id): void {
    // Do not hold the order mutation lock while calling complete_payment().
    $event = locked($id, static function () use ($id) {
        $order = wc_get_order($id);
        if (!$order || !$order->needs_payment()) { return null; }
        $a = $order->get_meta(ATTEMPT_META);
        if (!is_array($a) || empty($a['trade']) || time() - ($a['last_query'] ?? 0) < 20) { return null; }
        $a['last_query'] = time();
        $order->update_meta_data(ATTEMPT_META, $a);
        $order->save();
        return ['method' => $a['method'], 'event' => Providers::production()->query($a['method'], $a['trade'])];
    });
    if (!empty($event['event'])) { complete_payment($event['method'], $event['event']); }
}

function authorize_order(): \WC_Order {
    $id = absint($_REQUEST['order_id'] ?? 0);
    $key = isset($_REQUEST['key']) && is_string($_REQUEST['key']) ? wp_unslash($_REQUEST['key']) : '';
    $order = wc_get_order($id);
    if (!$order || $key === '' || !hash_equals($order->get_order_key(), $key) ||
        !in_array($order->get_payment_method(), ['sscn_alipay', 'sscn_wechat'], true)) {
        throw new \RuntimeException('Invalid order credentials');
    }
    return $order;
}

add_action('before_woocommerce_init', static function (): void {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
});

add_action('plugins_loaded', static function (): void {
    if (!class_exists('WC_Payment_Gateway')) { return; }
    require_once __DIR__ . '/includes/gateways.php';
    add_filter('woocommerce_payment_gateways', static function (array $methods): array {
        $methods[] = AlipayGateway::class;
        $methods[] = WechatGateway::class;
        return $methods;
    });
});

foreach (['sscn_alipay', 'sscn_wechat'] as $method) {
    add_action('woocommerce_api_' . $method, static function () use ($method): void {
        nocache_headers();
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { status_header(405); exit; }
        try {
            $body = file_get_contents('php://input', false, null, 0, 65537);
            if (strlen($body) > 65536) { throw new \RuntimeException('Oversized notification'); }
            $providers = Providers::production();
            if ($method === 'sscn_alipay') {
                $event = $providers->alipay_notification(wp_unslash($_POST));
            } else {
                $headers = [];
                foreach (['timestamp', 'nonce', 'serial', 'signature'] as $field) {
                    $headers[$field] = $_SERVER['HTTP_WECHATPAY_' . strtoupper($field)] ?? '';
                }
                $event = $providers->wechat_notification($body, $headers);
            }
            if ($event) { complete_payment($method, $event); }
            status_header($method === 'sscn_alipay' ? 200 : 204);
            if ($method === 'sscn_alipay') { header('Content-Type: text/plain; charset=utf-8'); echo 'success'; }
        } catch (\Throwable $e) {
            log_failure('notification-rejected');
            status_header(400);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'fail';
        }
        exit;
    });
}

add_action('woocommerce_api_sscn_return', static function (): void {
    nocache_headers();
    try { $order = authorize_order(); }
    catch (\Throwable $e) { status_header(403); exit; }
    try { reconcile($order->get_id()); }
    catch (\Throwable $e) { log_failure('return-query', $order->get_id()); }
    // Browser parameters never mark an order paid; only a verified provider query can.
    wp_safe_redirect(order_url($order));
    exit;
});

foreach (['wp_ajax_sscn_payment_status', 'wp_ajax_nopriv_sscn_payment_status'] as $hook) {
    add_action($hook, static function (): void {
        nocache_headers();
    try { $order = authorize_order(); }
        catch (\Throwable $e) { wp_send_json_error(['message' => 'Invalid order'], 403); }
        try { reconcile($order->get_id()); }
        catch (\Throwable $e) { log_failure('status-query', $order->get_id()); }
        $order = wc_get_order($order->get_id());
        wp_send_json_success(['paid' => $order->is_paid(), 'redirect' => order_url($order)]);
    });
}

add_action('sscn_payment_reconcile', static function ($id): void {
    try { reconcile((int) $id); }
    catch (\Throwable $e) { log_failure('scheduled-query', (int) $id); }
    $order = wc_get_order($id);
    $a = $order ? $order->get_meta(ATTEMPT_META) : null;
    if ($order && $order->needs_payment() && is_array($a) && time() < ($a['created'] ?? 0) + 7200) {
        wp_schedule_single_event(time() + 300, 'sscn_payment_reconcile', [(int) $id]);
    }
});

add_filter('woocommerce_order_actions', static function (array $actions, $order): array {
    if ($order && in_array($order->get_payment_method(), ['sscn_alipay', 'sscn_wechat'], true)) {
        $actions['sscn_check_payment'] = '查询支付平台收款状态';
    }
    return $actions;
}, 10, 2);
add_action('woocommerce_order_action_sscn_check_payment', static function ($order): void {
    try { reconcile($order->get_id()); }
    catch (\Throwable $e) { log_failure('admin-query', $order->get_id()); }
});

add_action('woocommerce_blocks_loaded', static function (): void {
    require_once __DIR__ . '/includes/blocks.php';
    add_action('woocommerce_blocks_payment_method_type_registration', static function ($registry): void {
        $registry->register(new Blocks('sscn_alipay'));
        $registry->register(new Blocks('sscn_wechat'));
    });
});
