<?php
namespace SockSpecsCN\Commerce;

defined('ABSPATH') || exit;

function identity_key(string $app): string { return '_sscn_wechat_' . hash('sha256', $app); }
function customer_openid(): string {
    $app = login_app('official');
    return get_current_user_id() && !empty($app['app_id']) ? (string) get_user_meta(get_current_user_id(), identity_key($app['app_id']), true) : '';
}
function safe_next(string $next): string {
    $next = wp_validate_redirect($next, wc_get_page_permalink('myaccount'));
    return wp_parse_url($next, PHP_URL_HOST) === wp_parse_url(home_url(), PHP_URL_HOST) ? $next : wc_get_page_permalink('myaccount');
}
function login_url(string $next = ''): string {
    return add_query_arg(['sscn_auth' => 'start', 'next' => safe_next($next), '_wpnonce' => wp_create_nonce('sscn-wechat-start')], home_url('/'));
}
function login_button(): void {
    $kind = in_wechat() ? 'official' : 'website';
    if (!login_ready($kind)) {
        if (!is_user_logged_in()) {
            echo '<div class="sscn-wechat-login"><button class="button" type="button" disabled>使用微信登录（暂未开放）</button><p class="sscn-login-help">微信登录即将开放，请先使用邮箱注册或登录。</p></div>';
        }
        return;
    }
    if (is_user_logged_in() && get_user_meta(get_current_user_id(), identity_key(login_app($kind)['app_id']), true)) { return; }
    echo '<p class="sscn-wechat-login"><a class="button" href="' . esc_url(login_url(is_checkout() ? wc_get_checkout_url() : wc_get_page_permalink('myaccount'))) . '">' . esc_html(is_user_logged_in() ? '绑定微信账户' : '使用微信登录') . '</a></p>';
}
add_action('woocommerce_before_customer_login_form', __NAMESPACE__ . '\\login_button');
add_action('woocommerce_before_checkout_form', __NAMESPACE__ . '\\login_button');
add_action('woocommerce_account_dashboard', __NAMESPACE__ . '\\login_button');
add_filter('render_block_woocommerce/checkout', static function ($content) {
    ob_start(); login_button(); return ob_get_clean() . $content;
});

/** A state is browser-bound, expires in five minutes and can be consumed only once. */
function consume_state(string $state, string $cookie): array {
    if (!preg_match('/^[a-f0-9]{64}$/D', $state) || !preg_match('/^[a-f0-9]{64}$/D', $cookie)) { throw new \RuntimeException('Invalid state'); }
    global $wpdb; $lock = 'sscn_oauth_' . substr(hash('sha256', $state), 0, 40);
    if ((int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)', $lock)) !== 1) { throw new \RuntimeException('State busy'); }
    try {
        $key = 'sscn_oauth_' . hash('sha256', $state); $row = get_transient($key);
        if (!is_array($row) || $row['expires'] < time() || !hash_equals($row['browser'], hash('sha256', $cookie))) { throw new \RuntimeException('Expired or wrong browser'); }
        delete_transient($key); return $row;
    } finally { $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock)); }
}
/** No email/UnionID guessing: linking requires the same authenticated session that started it. */
function resolve_customer(string $app, string $openid, int $bind = 0): int {
    if (!preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $openid)) { throw new \RuntimeException('Invalid identity'); }
    global $wpdb; $lock = 'sscn_identity_' . substr(hash('sha256', $app . ':' . $openid), 0, 40);
    if ((int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)', $lock)) !== 1) { throw new \RuntimeException('Identity busy'); }
    try {
        $key = identity_key($app);
        $ids = get_users(['meta_key' => $key, 'meta_value' => $openid, 'fields' => 'ID', 'number' => 2]);
        if (count($ids) > 1 || ($bind && $ids && (int) $ids[0] !== $bind)) { throw new \RuntimeException('Identity already linked'); }
        $id = $bind ?: (int) ($ids[0] ?? 0);
        if ($id && (user_can($id, 'manage_options') || user_can($id, 'manage_woocommerce') || user_can($id, 'edit_posts'))) { throw new \RuntimeException('Customer login only'); }
        if ($bind) {
            $previous = get_user_meta($bind, $key, true);
            if ($previous && $previous !== $openid) { throw new \RuntimeException('Do not replace existing binding'); }
        }
        if (!$id) {
            $id = wp_insert_user(['user_login' => 'wx_' . substr(hash('sha256', $app . ':' . $openid), 0, 32),
                'user_pass' => wp_generate_password(40, true, true), 'display_name' => '微信用户', 'role' => 'customer']);
            if (is_wp_error($id)) { throw new \RuntimeException('Could not create customer'); }
        }
        update_user_meta($id, $key, $openid); return (int) $id;
    } finally { $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock)); }
}
function exchange_identity(string $code, array $app): string {
    $url = add_query_arg(['appid' => $app['app_id'], 'secret' => $app['secret'], 'code' => $code, 'grant_type' => 'authorization_code'], 'https://api.weixin.qq.com/sns/oauth2/access_token');
    $r = wp_remote_get($url, ['timeout' => 20, 'redirection' => 0, 'limit_response_size' => 16384]);
    if (is_wp_error($r) || wp_remote_retrieve_response_code($r) !== 200) { throw new \RuntimeException('OAuth unavailable'); }
    $data = json_decode(wp_remote_retrieve_body($r), true);
    if (!is_array($data) || !empty($data['errcode']) || empty($data['access_token']) || empty($data['openid'])) { throw new \RuntimeException('OAuth rejected'); }
    return (string) $data['openid']; // Tokens are never persisted or sent to the browser.
}
add_action('template_redirect', static function (): void {
    $action = isset($_GET['sscn_auth']) && is_string($_GET['sscn_auth']) ? wp_unslash($_GET['sscn_auth']) : '';
    if (!$action) { return; }
    nocache_headers(); header('Referrer-Policy: no-referrer');
    try {
        if ($action === 'start') {
            $kind = in_wechat() ? 'official' : 'website';
            if (!login_ready($kind) || !wp_verify_nonce($_GET['_wpnonce'] ?? '', 'sscn-wechat-start')) { throw new \RuntimeException('Disabled or invalid start'); }
            $state = bin2hex(random_bytes(32)); $browser = bin2hex(random_bytes(32)); $app = login_app($kind);
            $next = isset($_GET['next']) && is_string($_GET['next']) ? wp_unslash($_GET['next']) : '';
            set_transient('sscn_oauth_' . hash('sha256', $state), ['kind' => $kind, 'app_id' => $app['app_id'], 'browser' => hash('sha256', $browser),
                'bind' => get_current_user_id(), 'session' => hash('sha256', wp_get_session_token()), 'next' => safe_next($next), 'expires' => time() + 300], 300);
            setcookie('sscn_oauth', $browser, ['expires' => time() + 300, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax']);
            $url = add_query_arg(['appid' => $app['app_id'], 'redirect_uri' => home_url('/?sscn_auth=callback'), 'response_type' => 'code',
                'scope' => $kind === 'official' ? 'snsapi_base' : 'snsapi_login', 'state' => $state],
                'https://open.weixin.qq.com/connect/' . ($kind === 'official' ? 'oauth2/authorize' : 'qrconnect')) . '#wechat_redirect';
            wp_redirect($url); exit;
        }
        if ($action !== 'callback') { throw new \RuntimeException('Unknown action'); }
        $row = consume_state(is_string($_GET['state'] ?? null) ? $_GET['state'] : '', is_string($_COOKIE['sscn_oauth'] ?? null) ? $_COOKIE['sscn_oauth'] : '');
        setcookie('sscn_oauth', '', ['expires' => time() - 3600, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax']);
        if (!login_ready($row['kind']) || login_app($row['kind'])['app_id'] !== $row['app_id'] ||
            get_current_user_id() !== $row['bind'] || !hash_equals($row['session'], hash('sha256', wp_get_session_token()))) { throw new \RuntimeException('Login session changed'); }
        $code = $_GET['code'] ?? ''; if (!is_string($code) || !preg_match('/^[A-Za-z0-9_-]{1,256}$/D', $code)) { throw new \RuntimeException('Authorization cancelled'); }
        $openid = exchange_identity($code, login_app($row['kind']));
        $id = resolve_customer($row['app_id'], $openid, $row['bind']);
        wp_set_current_user($id); wp_set_auth_cookie($id, false, true); do_action('wp_login', get_userdata($id)->user_login, get_userdata($id));
        wp_safe_redirect(safe_next($row['next'])); exit;
    } catch (\Throwable $e) {
        wp_die('微信登录未完成或已过期，请返回账户页面重新尝试。', '微信登录', ['response' => 400, 'back_link' => true]);
    }
}, 0);
