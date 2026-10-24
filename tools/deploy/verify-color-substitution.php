<?php
if (!defined('WP_CLI') || !WP_CLI || DB_NAME !== 'sockspecs_cn') { throw new RuntimeException('Domestic only'); }
$rows = [];
foreach (wc_get_products(['limit' => -1, 'status' => ['publish', 'draft', 'private', 'pending'], 'type' => ['variable', 'simple']]) as $p) {
    foreach (array_merge([$p->get_id()], $p->is_type('variable') ? $p->get_children() : []) as $id) {
        $v = wc_get_product($id);
        $rows[$id] = [$v->get_status(), $v->get_regular_price(), $v->get_sale_price(), $v->get_manage_stock(), $v->get_stock_quantity(), $v->get_stock_status(), $v->get_backorders()];
    }
}
ksort($rows);
$settings = [];
foreach (['woocommerce_coming_soon', 'woocommerce_store_pages_only', 'woocommerce_currency', 'woocommerce_sscn_alipay_settings', 'woocommerce_sscn_wechat_settings'] as $key) { $settings[$key] = get_option($key); }
$result = ['catalog_settings_sha256' => hash('sha256', wp_json_encode([$rows, $settings]))];
if (class_exists('SockSpecsCN\\Commerce\\ColorSubstitution')) {
    $fields = Automattic\WooCommerce\Blocks\Package::container()->get(Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFields::class)->get_additional_fields();
    $f = $fields['sscn/color-substitution'] ?? [];
    $result['optional_checkbox'] = ($f['type'] ?? '') === 'checkbox' && ($f['required'] ?? true) === false;
    $result['order_scope'] = ($f['location'] ?? '') === 'order';
    $result['admin_save_hook'] = (bool) has_action('woocommerce_process_shop_order_meta', ['SockSpecsCN\\Commerce\\ColorSubstitution', 'admin_save']);
    $result['print_hook'] = (bool) has_action('admin_post_sscn_print_order', ['SockSpecsCN\\Commerce\\ColorSubstitution', 'print_order']);
}
echo wp_json_encode($result, JSON_UNESCAPED_UNICODE) . "\n";
