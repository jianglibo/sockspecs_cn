<?php
namespace SockSpecsCN\Commerce;

defined('ABSPATH') || exit;

/** Only this store's private configuration may enable external commerce. */
function config(): array {
    $path = defined('SSCN_COMMERCE_CONFIG') ? SSCN_COMMERCE_CONFIG : '/etc/sockspecs-cn/commerce/config.json';
    if (!is_readable($path)) { return []; }
    try { $data = json_decode(file_get_contents($path), true, 32, JSON_THROW_ON_ERROR); }
    catch (\Throwable $e) { return []; }
    return is_array($data) ? $data : [];
}
function domestic_site(): bool {
    return defined('DB_NAME') && DB_NAME === 'sockspecs_cn' &&
        in_array(wp_parse_url(home_url(), PHP_URL_HOST), ['sockspecs.cn', '127.0.0.1'], true);
}
function live_ready(): bool {
    $c = config();
    return domestic_site() && ($c['live_enabled'] ?? false) === true &&
        ($c['site_url'] ?? '') === untrailingslashit(home_url()) &&
        untrailingslashit(home_url()) === 'https://sockspecs.cn';
}
function in_wechat(): bool { return stripos($_SERVER['HTTP_USER_AGENT'] ?? '', 'MicroMessenger') !== false; }
function payment_mode(): string { return in_wechat() ? 'jsapi' : (wp_is_mobile() ? 'h5' : 'native'); }
function login_app(string $kind): array {
    $app = config()['login'][$kind] ?? [];
    return is_array($app) ? $app : [];
}
function login_ready(string $kind): bool {
    $app = login_app($kind);
    return live_ready() && get_option('sscn_wechat_login_enabled', 'no') === 'yes' &&
        ($app['enabled'] ?? false) === true && !empty($app['app_id']) && !empty($app['secret']);
}
function gateway_ready(string $method): bool {
    if (!live_ready() || !class_exists('WeChatPay\\Builder') || !class_exists('Alipay\\EasySDK\\Kernel\\Factory')) { return false; }
    $section = $method === 'sscn_alipay' ? 'alipay' : 'wechat';
    $c = config()[$section] ?? [];
    $fields = $section === 'alipay' ? ['private_key', 'app_cert', 'public_cert', 'root_cert'] : ['private_key', 'platform_cert'];
    if (empty($c['app_id']) || ($c['enabled'] ?? false) !== true) { return false; }
    foreach ($fields as $field) { if (empty($c[$field]) || !is_readable($c[$field])) { return false; } }
    if ($section === 'wechat') {
        if (empty($c['mch_id']) || empty($c['serial']) || empty($c['platform_serial']) || strlen($c['api_v3_key'] ?? '') !== 32) { return false; }
        $mode = payment_mode();
        if (($c['modes'][$mode] ?? false) !== true) { return false; }
        if ($mode === 'jsapi' && (!login_ready('official') || login_app('official')['app_id'] !== $c['app_id'])) { return false; }
    }
    return true;
}
add_action('admin_menu', static function (): void {
    add_submenu_page('woocommerce', '国内登录与支付', '国内登录与支付', 'manage_options', 'sscn-commerce', __NAMESPACE__ . '\\settings_page');
});
function settings_page(): void {
    if (!current_user_can('manage_options')) { return; }
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        check_admin_referer('sscn-commerce-settings');
        update_option('sscn_wechat_login_enabled', isset($_POST['login_enabled']) ? 'yes' : 'no', false);
    }
    echo '<div class="wrap"><h1>国内登录与支付</h1><p>' . esc_html(live_ready() ? '正式接入开关已开启。' : '尚未启用正式接入，客户不会看到登录和支付入口。备案及平台域名配置完成后再开启。') . '</p>';
    echo '<form method="post">'; wp_nonce_field('sscn-commerce-settings');
    echo '<label><input type="checkbox" name="login_enabled" value="1" ' . checked(get_option('sscn_wechat_login_enabled', 'no'), 'yes', false) . '> 启用微信登录（同时需要私有配置授权）</label>';
    submit_button('保存'); echo '</form><p>微信支付、支付宝开关位于 WooCommerce → 设置 → 付款。密钥仅从网站目录外的私有文件读取，不显示在后台。</p>';
    echo '<h2>备案后需要配置的地址</h2><p>网站应用扫码登录与公众号网页授权回调：</p><code>' . esc_html(home_url('/?sscn_auth=callback')) . '</code>';
    echo '<p>支付通知：</p><code>' . esc_html(api_url('sscn_wechat')) . '</code><br><code>' . esc_html(api_url('sscn_alipay')) . '</code>';
    echo '<p>微信 JSAPI 支付页面：' . esc_html(wc_get_checkout_url()) . '（支付目录须覆盖订单付款页面）</p><p>自动退款尚未启用。请在商户平台退款后，在 WooCommerce 中记录手动退款。</p></div>';
}

/** The last X-Forwarded-For entry is used only behind an explicitly trusted proxy. */
function payer_client_ip(): string {
    $remote = $_SERVER['REMOTE_ADDR'] ?? '';
    if (in_array($remote, config()['trusted_proxies'] ?? [], true)) {
        $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
        $remote = trim(end($parts));
    }
    return filter_var($remote, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) ? $remote : '';
}
