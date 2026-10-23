<?php
/** Read-only WordPress/PHPMailer checks: never connects or sends. */
if (!defined('WP_CLI') || !WP_CLI || DB_NAME !== 'sockspecs_cn' ||
    !in_array(home_url(), ['http://127.0.0.1:8089', 'https://sockspecs.cn'], true)) {
    throw new RuntimeException('Domestic CLI only');
}
$count = 0;
$check = static function ($ok, $message) use (&$count) {
    if (!$ok) { throw new RuntimeException($message); }
    ++$count;
};
$check(function_exists('wp_mail_smtp') && defined('WPMS_ON'), 'SMTP transport/configuration missing');
$check(WPMS_MAIL_FROM_FORCE === false, 'Per-email sender selection must remain available');
$check(apply_filters('wp_mail_from', 'wordpress@example.invalid') === 'hello@sockspecs.cn', 'WordPress account sender');
$emails = WC()->mailer()->get_emails();
foreach (['WC_Email_Customer_New_Account', 'WC_Email_Customer_Reset_Password'] as $key) {
    $check($emails[$key]->get_from_address() === 'hello@sockspecs.cn', 'Account sender incorrect');
}
foreach (['WC_Email_New_Order', 'WC_Email_Customer_Processing_Order', 'WC_Email_Customer_Completed_Order', 'WC_Email_Customer_Refunded_Order'] as $key) {
    $check($emails[$key]->get_from_address() === 'orders@sockspecs.cn', 'Order sender incorrect');
}
$check(str_contains($emails['WC_Email_Customer_Processing_Order']->get_headers(), 'hello@sockspecs.cn'), 'Customer Reply-To');
$email = $emails['WC_Email_Customer_Processing_Order'];
add_filter('wp_mail_from', [$email, 'get_from_address']);
$check(apply_filters('wp_mail_from', 'wordpress@example.invalid') === 'orders@sockspecs.cn', 'SMTP must preserve WooCommerce order sender');
remove_filter('wp_mail_from', [$email, 'get_from_address']);
// Exercise WooCommerce's real send wrapper with an in-process no-send callback.
$mock = static function () use ($check) {
    return static function () use ($check) {
        $check(apply_filters('wp_mail_from', 'wordpress@example.invalid') === 'orders@sockspecs.cn', 'Order send-wrapper sender');
        return true;
    };
};
add_filter('woocommerce_mail_callback', $mock);
try {
    $check($email->send('nobody@example.invalid', 'No-send fixture', 'Fixture', $email->get_headers(), []), 'Native order send wrapper');
} finally {
    remove_filter('woocommerce_mail_callback', $mock);
}
require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
$mailer = new PHPMailer\PHPMailer\PHPMailer();
do_action_ref_array('phpmailer_init', [&$mailer]);
$check($mailer->Mailer === 'smtp' && $mailer->Host === 'smtp.mx.cloudflare.net' && (int) $mailer->Port === 465, 'SMTP endpoint');
$check($mailer->SMTPSecure === 'ssl' && $mailer->SMTPAuth && $mailer->Username === 'api_token', 'TLS/authentication settings');
$check($mailer->Password === WPMS_SMTP_PASS, 'Private credential loading');
if (WPMS_SMTP_PASS !== '') {
    $check(!str_contains(wp_json_encode(get_option('wp_mail_smtp')), WPMS_SMTP_PASS), 'Token must not be stored in WordPress options');
}
if (wp_get_environment_type() === 'local') {
    $check(WPMS_DO_NOT_SEND && apply_filters('pre_wp_mail', null, []) === false, 'Local mail must remain blocked');
}
WP_CLI::success("$count domestic email checks passed; no message sent.");
