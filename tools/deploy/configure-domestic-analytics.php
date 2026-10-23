<?php
/** Operator-invoked domestic Matomo traffic setup. No order or stock changes. */
if (!defined('WP_CLI') || !WP_CLI || DB_NAME !== 'sockspecs_cn' ||
    !in_array(home_url(), ['http://127.0.0.1:8089', 'https://sockspecs.cn'], true) ||
    !current_user_can('manage_options') || !class_exists('WpMatomo')) {
    throw new RuntimeException('Domestic staff CLI with Matomo required');
}
$mode = $args[0] ?? 'verify';
if (!in_array($mode, ['apply', 'verify'], true)) { throw new RuntimeException('Use apply or verify'); }
$expected = [
    'track_mode' => 'default', 'track_ecommerce' => false,
    'disable_cookies' => true, 'track_user_id' => 'disabled',
    'track_admin' => false, 'track_search' => false,
    'site_currency' => 'CNY',
    'caps_tracking' => ['administrator' => true, 'shop_manager' => true],
];
$expected[\WpMatomo\Settings::DELETE_ALL_DATA_ON_UNINSTALL] = false;
if ($mode === 'apply') {
    if (!get_option('sscn_analytics_before_20261020')) {
        add_option('sscn_analytics_before_20261020', [
            'settings' => get_option(\WpMatomo\Settings::OPTION_GLOBAL),
            'timezone' => get_option('timezone_string'),
        ], '', false);
    }
    update_option('timezone_string', 'Asia/Shanghai');
    \WpMatomo::$settings->apply_tracking_related_changes($expected);
    (new \WpMatomo\Site\Sync(\WpMatomo::$settings))->sync_all();
}
\WpMatomo\Bootstrap::do_bootstrap();
// claw-hub overwrites this header; claw-host accepts only that proxy and loopback.
$proxy = new \WpMatomo\Site\Sync\SyncConfig(\WpMatomo::$settings);
$proxy_headers = home_url() === 'https://sockspecs.cn' ? ['HTTP_X_FORWARDED_FOR'] : [];
if ($mode === 'apply') { $proxy->set_config_value('General', 'proxy_client_headers', $proxy_headers); }
if ($proxy->get_config_value('General', 'proxy_client_headers') !== $proxy_headers) {
    throw new RuntimeException('Unexpected proxy configuration');
}
\Piwik\Access::doAsSuperUser(static function () use ($mode) {
    if ($mode === 'apply') {
        \Piwik\Plugins\PrivacyManager\API::getInstance()->setAnonymizeIpSettings(true, 2, true, true, true, 'exclude_query', true);
        \Piwik\Plugins\SitesManager\API::getInstance()->setGlobalExcludedQueryParameters(
            'key,order_key,token,access_token,code,state,password,pass,email,phone,login,_wpnonce,sscn_ref,pc_pack_group'
        );
    }
});
foreach ($expected as $key => $value) {
    if (\WpMatomo::$settings->get_global_option($key) !== $value) {
        throw new RuntimeException('Unexpected Matomo setting: ' . $key);
    }
}
$code = \WpMatomo::$settings->get_js_tracking_code();
if (!str_contains($code, 'trackPageView') || !str_contains($code, 'disableCookies') || !str_contains(str_replace('\\/', '/', $code), '//' . wp_parse_url(home_url(), PHP_URL_HOST))) {
    throw new RuntimeException('Missing same-site cookieless page tracking');
}
// Refresh in-memory role capabilities after changing exclusions in this CLI process.
(new \WpMatomo\Capabilities(\WpMatomo::$settings))->add_capabilities_to_roles(wp_roles());
wp_get_current_user()->get_role_caps();
$tracker = new \WpMatomo\TrackingCode(\WpMatomo::$settings);
ob_start(); $tracker->add_javascript_code(); $admin_code = ob_get_clean();
if ($admin_code !== '') { throw new RuntimeException('Administrator should be excluded'); }
wp_set_current_user(0);
ob_start(); $tracker->add_javascript_code(); $guest_code = ob_get_clean();
if (!str_contains($guest_code, 'trackPageView')) { throw new RuntimeException('Guest tracking missing'); }
$privacy = new \Piwik\Plugins\PrivacyManager\Config();
if (!$privacy->ipAnonymizerEnabled || $privacy->ipAddressMaskLength !== 2 || !$privacy->forceCookielessTracking) {
    throw new RuntimeException('Privacy settings not applied');
}
echo wp_json_encode(['status' => 'verified', 'site_id' => \WpMatomo\Site::get_matomo_site_id(get_current_blog_id()),
    'home' => home_url(), 'timezone' => get_option('timezone_string'), 'currency' => 'CNY',
    'traffic_tracking' => true, 'ecommerce_tracking' => false, 'staff_excluded' => true]) . PHP_EOL;
