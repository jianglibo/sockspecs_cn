<?php
/** Explicit, repeatable domestic customer-registration configuration. */
if (!defined('WP_CLI') || !WP_CLI || DB_NAME !== 'sockspecs_cn' ||
    !in_array(home_url(), ['https://sockspecs.cn', 'http://127.0.0.1:8089'], true)) {
    throw new RuntimeException('Domestic store only');
}
$changes = [
    'woocommerce_enable_myaccount_registration' => 'yes',
    'woocommerce_enable_signup_and_login_from_checkout' => 'yes',
    'woocommerce_registration_privacy_policy_text' => '我们将使用您的个人信息管理账户、提供网站服务，并用于[privacy_policy]中说明的其他用途。',
    'woocommerce_checkout_privacy_policy_text' => '我们将使用您的个人信息处理订单、提供网站服务，并用于[privacy_policy]中说明的其他用途。',
];
// Keep an initial rollback record; do not reset existing customers or other settings.
$before = get_option('sscn_registration_before_20261020', []);
foreach ($changes as $key => $value) {
    if (!array_key_exists($key, $before)) { $before[$key] = get_option($key); }
}
update_option('sscn_registration_before_20261020', $before, false);
foreach ($changes as $key => $value) {
    update_option($key, $value);
    if (get_option($key) !== $value) { throw new RuntimeException('Registration setting failed'); }
}
WP_CLI::success('Domestic account-page and checkout registration enabled. OAuth/payment gates unchanged.');
