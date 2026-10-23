<?php
/** Domestic transactional mail configuration; token stays outside Git and web root. */
defined('ABSPATH') || exit;
$sscn_email_path = defined('SSCN_EMAIL_CONFIG') ? SSCN_EMAIL_CONFIG : '/etc/sockspecs-cn/email.json';
$sscn_email = is_readable($sscn_email_path) ? json_decode(file_get_contents($sscn_email_path), true) : [];
$sscn_email = is_array($sscn_email) ? $sscn_email : [];
$sscn_mail_enabled = defined('SSCN_DOMESTIC_SITE') && SSCN_DOMESTIC_SITE &&
    defined('DB_NAME') && DB_NAME === 'sockspecs_cn' &&
    get_option('home') === 'https://sockspecs.cn' &&
    ($sscn_email['enabled'] ?? false) === true &&
    is_string($sscn_email['token'] ?? null) && $sscn_email['token'] !== '';

define('WPMS_ON', true);
define('WPMS_MAILER', 'smtp');
define('WPMS_SMTP_HOST', 'smtp.mx.cloudflare.net');
define('WPMS_SMTP_PORT', 465);
define('WPMS_SSL', 'ssl');
define('WPMS_SMTP_AUTOTLS', false);
define('WPMS_SMTP_AUTH', true);
define('WPMS_SMTP_USER', 'api_token');
define('WPMS_SMTP_PASS', (string) ($sscn_email['token'] ?? ''));
define('WPMS_MAIL_FROM', 'hello@sockspecs.cn');
// Preserve WooCommerce's per-email sender selection below.
define('WPMS_MAIL_FROM_FORCE', false);
define('WPMS_MAIL_FROM_NAME', 'SockSpecs');
define('WPMS_MAIL_FROM_NAME_FORCE', true);
define('WPMS_DO_NOT_SEND', !$sscn_mail_enabled);
define('WPMS_LOGS_ENABLED', false);
define('WPMS_LOGS_LOG_EMAIL_CONTENT', false);
unset($sscn_email, $sscn_email_path, $sscn_mail_enabled);

// Missing configuration or transport must not fall back to unauthenticated PHP mail.
add_filter('pre_wp_mail', static function ($pre) {
    return WPMS_DO_NOT_SEND || !function_exists('wp_mail_smtp') ? false : $pre;
}, PHP_INT_MAX);

// WordPress account/reset messages use hello; native WooCommerce filters run at 10.
add_filter('wp_mail_from', static fn() => 'hello@sockspecs.cn', 9);
add_filter('wp_mail_from_name', static fn() => 'SockSpecs', 9);
add_filter('woocommerce_email_from_address', static function ($address, $email) {
    return in_array($email->id, ['customer_new_account', 'customer_reset_password'], true)
        ? 'hello@sockspecs.cn' : 'orders@sockspecs.cn';
}, 20, 2);

// Customer replies go to hello; admin order alerts retain the customer's Reply-To.
add_filter('woocommerce_email_headers', static function ($headers, $id) {
    if (in_array($id, ['new_order', 'cancelled_order', 'failed_order', 'low_stock', 'no_stock', 'backorder'], true)) {
        return $headers;
    }
    $headers = preg_replace('/^Reply-to:[^\r\n]*(?:\r?\n|$)/mi', '', $headers);
    return rtrim($headers, "\r\n") . "\r\nReply-To: SockSpecs <hello@sockspecs.cn>\r\n";
}, 20, 2);
