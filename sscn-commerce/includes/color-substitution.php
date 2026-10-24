<?php
namespace SockSpecsCN\Commerce;

defined('ABSPATH') || exit;

/** Order-scoped permission and fulfillment log. Never changes prices or moves money. */
final class ColorSubstitution {
    const FIELD = 'sscn/color-substitution';
    const CONSENT = '_sscn_color_consent';
    const META = '_sscn_color_fulfillment';
    const LABEL = '如所选颜色缺货，我接受同款、同尺码、同袜筒长度的其他颜色随机替换；实际换色后，每单退还 ¥2。';

    public static function init(): void {
        add_action('woocommerce_init', [self::class, 'register_field']);
        add_action('woocommerce_after_order_notes', [self::class, 'classic_field']);
        add_action('woocommerce_checkout_create_order', [self::class, 'classic_save'], 20, 2);
        add_action('woocommerce_store_api_checkout_update_order_from_request', [self::class, 'blocks_save'], 20, 2);
        add_action('add_meta_boxes', [self::class, 'boxes']);
        add_action('woocommerce_process_shop_order_meta', [self::class, 'admin_save'], 60);
        add_action('woocommerce_order_details_after_order_table', [self::class, 'customer_summary']);
        add_action('woocommerce_email_after_order_table', [self::class, 'email'], 20, 4);
        add_action('admin_post_sscn_print_order', [self::class, 'print_order']);
    }

    public static function register_field(): void {
        if (!function_exists('woocommerce_register_additional_checkout_field')) { return; }
        woocommerce_register_additional_checkout_field([
            'id' => self::FIELD, 'label' => self::LABEL, 'optionalLabel' => self::LABEL,
            'location' => 'order', 'type' => 'checkbox', 'required' => false,
            'show_in_order_confirmation' => false,
        ]);
    }

    public static function classic_field($checkout): void {
        woocommerce_form_field('sscn_color_consent', [
            'type' => 'checkbox', 'label' => self::LABEL, 'required' => false,
        ], $checkout->get_value('sscn_color_consent'));
    }

    public static function capture(\WC_Order $order, bool $accepted): void {
        if ($order->get_currency() !== 'CNY' || $order->is_paid() ||
            !in_array($order->get_status(), ['pending', 'checkout-draft'], true) || $order->get_meta(self::META)) { return; }
        $old = $order->get_meta(self::CONSENT);
        if (is_array($old) && $old['accepted'] === $accepted) { return; }
        $order->update_meta_data(self::CONSENT, [
            'accepted' => $accepted, 'text' => self::LABEL, 'version' => 1, 'at' => gmdate('c'),
        ]);
        // The checkout caller persists the order. This permission is never a customer-wide preference.
    }

    public static function classic_save($order, $data): void {
        self::capture($order, isset($_POST['sscn_color_consent']) && $_POST['sscn_color_consent'] === '1');
    }

    public static function blocks_save($order, $request): void {
        $fields = $request->get_param('additional_fields');
        self::capture($order, is_array($fields) && ($fields[self::FIELD] ?? false) === true);
    }

    public static function state(\WC_Order $order): array {
        $value = $order->get_meta(self::META);
        return array_merge(['revision' => 0, 'later' => false, 'performed' => false, 'details' => '', 'refund_id' => 0], is_array($value) ? $value : []);
    }

    public static function accepted(\WC_Order $order): bool {
        $c = $order->get_meta(self::CONSENT);
        return is_array($c) && ($c['accepted'] ?? false) === true;
    }

    public static function consent_text(\WC_Order $order): string {
        if (self::accepted($order)) { return '下单时已同意随机换色'; }
        if (self::state($order)['later']) { return '联系后已同意随机换色'; }
        return '未授权随机换色，缺货时请先联系客户';
    }

    public static function valid_refund(\WC_Order $order, int $id): bool {
        $refund = $id ? wc_get_order($id) : false;
        return $refund instanceof \WC_Order_Refund && $refund->get_parent_id() === $order->get_id() &&
            $order->get_currency() === 'CNY' && (int) round((float) $refund->get_amount() * 100) === 200;
    }

    public static function summary(\WC_Order $order): array {
        $s = self::state($order);
        $lines = ['换色意愿：' . self::consent_text($order)];
        if ($s['performed']) {
            $lines[] = '实际换色：' . $s['details'];
            $lines[] = self::valid_refund($order, (int) $s['refund_id']) ? '换色补偿：已登记 ¥2 退款' : '换色补偿：待退 ¥2（每单一次）';
        } else {
            $lines[] = '实际换色后，每单退还 ¥2。';
        }
        return $lines;
    }

    public static function boxes(): void {
        foreach (['shop_order', 'woocommerce_page_wc-orders'] as $screen) {
            add_meta_box('sscn-color-substitution', '缺货换色', [self::class, 'box'], $screen, 'normal', 'default');
        }
    }

    public static function box($object): void {
        $order = $object instanceof \WC_Order ? $object : wc_get_order($object->ID);
        if (!$order || $order->get_currency() !== 'CNY') { return; }
        $s = self::state($order);
        echo '<p><strong>' . esc_html(self::consent_text($order)) . '</strong></p>';
        wp_nonce_field('sscn_color_' . $order->get_id(), 'sscn_color_nonce');
        echo '<input type="hidden" name="sscn_color_revision" value="' . esc_attr($s['revision']) . '">';
        if (!self::accepted($order)) {
            echo '<p><label><input type="checkbox" name="sscn_color_later" value="1" ' . checked($s['later'], true, false) . '> 已联系客户并取得随机换色同意</label></p>';
        }
        echo '<p><label><input type="checkbox" name="sscn_color_performed" value="1" ' . checked($s['performed'], true, false) . '> 已实际换色</label></p>';
        echo '<p><label for="sscn-color-details">替换内容（型号、原颜色 → 实发颜色、双数）</label><br><textarea id="sscn-color-details" name="sscn_color_details" rows="3" style="width:100%" maxlength="2000">' . esc_textarea($s['details']) . '</textarea></p>';
        echo '<p>仅替换同款、同尺码、同袜筒长度的颜色，不能替换成其他款式或材质。保存后会记录操作人和时间。</p>';
        echo '<p><label for="sscn-color-refund">关联换色退款</label> <select id="sscn-color-refund" name="sscn_color_refund"><option value="0">未登记退款</option>';
        foreach ($order->get_refunds() as $refund) {
            if (self::valid_refund($order, $refund->get_id())) {
                echo '<option value="' . esc_attr($refund->get_id()) . '" ' . selected($s['refund_id'], $refund->get_id(), false) . '>#' . esc_html($refund->get_id()) . ' · ¥2 · ' . esc_html($refund->get_reason()) . '</option>';
            }
        }
        echo '</select></p><p>实际换色每单退 ¥2。先在商户平台退款，再在订单的“退款”中登记 ¥2 手动退款，原因填“缺货换色补偿”，最后在这里关联该记录。此处不发起付款或退款。</p>';
        if ($s['performed']) { $summary = self::summary($order); echo '<p><strong>' . esc_html(end($summary)) . '</strong></p>'; }
        $url = wp_nonce_url(admin_url('admin-post.php?action=sscn_print_order&order_id=' . $order->get_id()), 'sscn_print_' . $order->get_id());
        echo '<p><a class="button" href="' . esc_url($url) . '" target="_blank" rel="noopener">打印订单／配货单</a> 请先更新订单，打印内容以已保存记录为准。</p>';
    }

    /** Validated, serialized update; original checkout choice remains immutable to admin edits. */
    public static function update(int $id, array $input): void {
        if (!current_user_can('edit_shop_order', $id)) { throw new \RuntimeException('没有编辑此订单的权限。'); }
        locked($id, static function () use ($id, $input): void {
            $order = wc_get_order($id);
            if (!$order instanceof \WC_Order || $order instanceof \WC_Order_Refund || $order->get_currency() !== 'CNY') { throw new \RuntimeException('订单不适用。'); }
            $old = self::state($order);
            if ((string) ($input['revision'] ?? '') !== (string) $old['revision']) { throw new \RuntimeException('换色记录已被修改，请刷新订单后重试。'); }
            $new = [
                'revision' => $old['revision'], 'later' => ($input['later'] ?? '') === '1',
                'performed' => ($input['performed'] ?? '') === '1',
                'details' => sanitize_textarea_field(is_string($input['details'] ?? null) ? $input['details'] : ''),
                'refund_id' => absint($input['refund_id'] ?? 0),
            ];
            if (mb_strlen($new['details']) > 2000) { throw new \RuntimeException('替换内容请控制在2000字以内。'); }
            if ($new['performed'] && (!self::accepted($order) && !$new['later'])) { throw new \RuntimeException('请先取得客户同意，再登记实际换色。'); }
            if ($new['performed'] && $new['details'] === '') { throw new \RuntimeException('请填写实际替换的颜色和数量。'); }
            if (!$new['performed'] && ($new['refund_id'] || $new['details'] !== '')) { throw new \RuntimeException('尚未实际换色，不能填写替换内容或关联补偿退款。'); }
            if ($new['refund_id'] && !self::valid_refund($order, $new['refund_id'])) { throw new \RuntimeException('请选择本订单金额为 ¥2 的退款记录。'); }
            if ($old['refund_id'] && self::valid_refund($order, (int) $old['refund_id']) && $new['refund_id'] !== (int) $old['refund_id']) { throw new \RuntimeException('本订单已关联换色退款，不能重复登记或解除。'); }
            $compare = array_intersect_key($old, $new);
            if ($compare === $new) { return; }
            $new['revision']++;
            $new['by'] = get_current_user_id(); $new['at'] = gmdate('c');
            $order->update_meta_data(self::META, $new); $order->save();
            $order->add_order_note('换色记录更新（操作人 #' . $new['by'] . '）：' . implode('；', self::summary($order)));
        });
    }

    public static function admin_save($id): void {
        if (!isset($_POST['sscn_color_nonce'])) { return; }
        if (!is_string($_POST['sscn_color_nonce']) || !wp_verify_nonce(wp_unslash($_POST['sscn_color_nonce']), 'sscn_color_' . $id)) {
            \WC_Admin_Meta_Boxes::add_error('换色记录未保存：校验已过期，请刷新重试。'); return;
        }
        try {
            self::update((int) $id, [
                'revision' => wp_unslash($_POST['sscn_color_revision'] ?? ''),
                'later' => $_POST['sscn_color_later'] ?? '', 'performed' => $_POST['sscn_color_performed'] ?? '',
                'details' => wp_unslash($_POST['sscn_color_details'] ?? ''), 'refund_id' => $_POST['sscn_color_refund'] ?? 0,
            ]);
        } catch (\Throwable $e) { \WC_Admin_Meta_Boxes::add_error('换色记录未保存：' . $e->getMessage()); }
    }

    public static function customer_summary($order): void {
        if (!$order->get_meta(self::CONSENT) && !$order->get_meta(self::META)) { return; }
        echo '<section class="sscn-color-summary"><h2>缺货换色</h2>';
        foreach (self::summary($order) as $line) { echo '<p>' . esc_html($line) . '</p>'; }
        echo '</section>';
    }

    public static function email($order, $admin, $plain, $email): void {
        if (!$order->get_meta(self::CONSENT) && !$order->get_meta(self::META)) { return; }
        if ($plain) { echo "\n缺货换色\n" . implode("\n", self::summary($order)) . "\n"; }
        else { self::customer_summary($order); }
    }

    public static function print_html(\WC_Order $order): string {
        ob_start();
        echo '<!doctype html><html lang="zh-CN"><head><meta charset="utf-8"><title>订单 #' . esc_html($order->get_order_number()) . '</title><style>body{font:15px sans-serif;max-width:850px;margin:30px auto;color:#111}table{width:100%;border-collapse:collapse}td,th{padding:10px;text-align:left;border-bottom:1px solid #ccc}p{white-space:pre-line}@media print{button{display:none}body{margin:0}}</style></head><body>';
        echo '<button onclick="window.print()">打印</button><h1>索克派 · 订单 #' . esc_html($order->get_order_number()) . '</h1>';
        echo '<p>' . esc_html(wc_get_order_status_name($order->get_status())) . '</p><h2>收货信息</h2>';
        echo wp_kses_post($order->get_formatted_shipping_address() ?: $order->get_formatted_billing_address());
        echo '<p>' . esc_html($order->get_shipping_phone() ?: $order->get_billing_phone()) . '</p>';
        echo '<table><thead><tr><th>订购商品／规格</th><th>双数</th><th>金额</th></tr></thead><tbody>';
        foreach ($order->get_items() as $item) {
            echo '<tr><td>' . esc_html($item->get_name()) . wp_kses_post(wc_display_item_meta($item, ['echo' => false])) . '</td><td>' . esc_html($item->get_quantity()) . '</td><td>' . wp_kses_post($order->get_formatted_line_subtotal($item)) . '</td></tr>';
        }
        echo '</tbody></table><p>订单总额：' . wp_kses_post($order->get_formatted_order_total()) . '</p><h2>缺货换色</h2>';
        foreach (self::summary($order) as $line) { echo '<p>' . esc_html($line) . '</p>'; }
        if ($order->get_customer_note()) { echo '<h2>客户备注</h2><p>' . esc_html($order->get_customer_note()) . '</p>'; }
        echo '</body></html>';
        return ob_get_clean();
    }

    public static function print_order(): void {
        $id = absint($_GET['order_id'] ?? 0);
        if (!current_user_can('edit_shop_order', $id)) { wp_die('无权查看此订单。', '', ['response' => 403]); }
        check_admin_referer('sscn_print_' . $id);
        $order = wc_get_order($id);
        if (!$order instanceof \WC_Order || $order instanceof \WC_Order_Refund) { wp_die('订单不存在。'); }
        nocache_headers(); header('Content-Type: text/html; charset=utf-8'); header('X-Robots-Tag: noindex, nofollow');
        echo self::print_html($order);
        exit;
    }
}
ColorSubstitution::init();
