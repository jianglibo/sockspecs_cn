<?php
/** Explicit domestic referral migration and discovery-page publication. */
use ParameterCommerce\Referral_Store;
use ParameterCommerce\Referrals;
if (!defined('WP_CLI') || !WP_CLI || !Referrals::domestic() || !current_user_can('manage_woocommerce')) {
    throw new RuntimeException('Domestic staff CLI only.');
}
$mode = $args[0] ?? 'plan';
if (!in_array($mode, ['plan', 'apply', 'verify', 'pause'], true)) { throw new RuntimeException('Unknown mode'); }
if (get_woocommerce_currency() !== 'CNY' || wc_get_price_decimals() !== 2) { throw new RuntimeException('CNY required'); }
$protected = ['woocommerce_coming_soon', 'woocommerce_currency', 'woocommerce_calc_taxes', 'woocommerce_manage_stock', 'sscn_wechat_login_enabled', 'woocommerce_sscn_wechat_settings', 'woocommerce_sscn_alipay_settings'];
$before = [];
foreach ($protected as $key) { $before[$key] = get_option($key); }
if ($mode === 'apply') {
    $menu = get_nav_menu_locations()['primary'] ?? 0;
    if (!$menu) { throw new RuntimeException('Domestic primary menu missing'); }
    Referral_Store::install();
    $page = get_page_by_path('refer-and-earn');
    if (!$page) {
        $id = wp_insert_post([
            'post_type' => 'page', 'post_status' => 'publish', 'post_name' => 'refer-and-earn',
            'post_title' => '推荐有礼',
            'post_content' => trim(file_get_contents(__DIR__ . '/content/refer-and-earn.html')),
        ], true);
        if (is_wp_error($id)) { throw new RuntimeException('Could not create referral page'); }
        update_option('sscn_referral_page_id', $id, false);
        $page = get_post($id);
    }
    if ($page->post_status !== 'publish' || $page->post_password) { throw new RuntimeException('Referral page must be public'); }
    $linked = false;
    foreach (wp_get_nav_menu_items($menu) ?: [] as $item) {
        if ((int) $item->object_id === (int) $page->ID && $item->object === 'page') { $linked = true; }
    }
    if (!$linked) {
        $item = wp_update_nav_menu_item($menu, 0, [
            'menu-item-title' => '推荐有礼', 'menu-item-type' => 'post_type',
            'menu-item-object' => 'page', 'menu-item-object-id' => $page->ID,
            'menu-item-status' => 'publish',
        ]);
        if (is_wp_error($item)) { throw new RuntimeException('Could not add referral menu'); }
    }
    Referrals::endpoint();
    flush_rewrite_rules(false);
    if (!wp_next_scheduled('sscn_referrals_reconcile')) {
        wp_schedule_event(time() + 300, 'sscn_referrals_five_minutes', 'sscn_referrals_reconcile');
    }
    update_option('sscn_referrals_enabled', 'yes', false);
}
if ($mode === 'pause') { update_option('sscn_referrals_enabled', 'no', false); }
if (in_array($mode, ['apply', 'verify'], true)) {
    if (!Referrals::enabled() || !wp_next_scheduled('sscn_referrals_reconcile') || !ParameterCommerce\Referral_UI::url()) {
        throw new RuntimeException('Domestic referral setup incomplete');
    }
}
foreach ($before as $key => $value) {
    if (get_option($key) !== $value) { throw new RuntimeException('Protected option changed'); }
}
echo wp_json_encode([
    'mode' => $mode, 'enabled' => Referrals::enabled(), 'currency' => get_woocommerce_currency(),
    'friend_discount' => 5, 'referrer_credit' => 5, 'cash_withdrawal' => false,
    'page' => ParameterCommerce\Referral_UI::url(),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
