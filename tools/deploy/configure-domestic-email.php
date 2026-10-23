<?php
/** Native WooCommerce email settings; no credentials stored in the database. */
if (!defined('WP_CLI') || !WP_CLI || DB_NAME !== 'sockspecs_cn' ||
    !in_array(home_url(), ['https://sockspecs.cn', 'http://127.0.0.1:8089'], true)) {
    throw new RuntimeException('Domestic store only');
}
$changes = [
    'woocommerce_email_from_address' => 'orders@sockspecs.cn',
    'woocommerce_email_from_name' => 'SockSpecs',
    'woocommerce_email_reply_to_enabled' => 'yes',
    'woocommerce_email_reply_to_address' => 'hello@sockspecs.cn',
    'woocommerce_email_reply_to_name' => 'SockSpecs',
];
$before = get_option('sscn_email_before_20261020', []);
foreach ($changes as $key => $value) {
    if (!array_key_exists($key, $before)) { $before[$key] = get_option($key); }
}
update_option('sscn_email_before_20261020', $before, false);
foreach ($changes as $key => $value) { update_option($key, $value); }
// WP Mail SMTP treats a false force-from constant as unset, so persist this
// non-secret preference too. Preserve all unrelated plugin settings.
$smtp = get_option('wp_mail_smtp', []);
$smtp = is_array($smtp) ? $smtp : [];
$smtp['mail']['from_email_force'] = false;
update_option('wp_mail_smtp', $smtp);
WP_CLI::success('Domestic order sender and customer Reply-To configured.');
