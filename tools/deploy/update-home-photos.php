<?php
/** Publish reviewed homepage photography; retain an undo snapshot. */
if (!defined('WP_CLI') || !WP_CLI || DB_NAME !== 'sockspecs_cn' || !current_user_can('manage_options') ||
    !in_array(home_url(), ['http://127.0.0.1:8089', 'https://sockspecs.cn'], true) || get_woocommerce_currency() !== 'CNY') {
    throw new RuntimeException('Domestic administrator CLI only.');
}
$mode = $args[0] ?? 'plan';
if (!in_array($mode, ['plan', 'apply', 'verify'], true)) { throw new RuntimeException('Use plan, apply or verify.'); }
$id = (int) get_option('page_on_front');
$post = get_post($id);
if (!$post || $post->post_type !== 'page' || $post->post_name !== 'home' || $post->post_status !== 'publish') {
    throw new RuntimeException('Unexpected homepage.');
}
$catalog = get_page_by_path('catalog');
$guide = get_page_by_path('sock-guides');
if (!$catalog || !$guide || $catalog->post_status !== 'publish' || $guide->post_status !== 'publish') {
    throw new RuntimeException('Published catalog and guide required.');
}
$asset = set_url_scheme(get_stylesheet_directory_uri(), wp_parse_url(home_url(), PHP_URL_SCHEME)) . '/assets/home-photos';
foreach (['sole-comparison', 'flat-knit-detail', 'zoned-sole-detail'] as $photo) {
    foreach ([640, 1200] as $width) {
        if (!is_file(get_stylesheet_directory() . '/assets/home-photos/' . $photo . '-' . $width . '.webp')) {
            throw new RuntimeException('Missing photo: ' . $photo);
        }
    }
}
$content = str_replace(['{{ASSET_URL}}', '{{CATALOG_URL}}', '{{GUIDE_URL}}'],
    [esc_url($asset), esc_url(get_permalink($catalog)), esc_url(get_permalink($guide))],
    file_get_contents(__DIR__ . '/content/home-photos-20261023.html'));
$known = ['f5699347abdad3a36a22f1b6d4b2119f5d2101a08deb6286e9e44b0b5c4a3880',
    '79c13a5238224020972f878d7ee5dad2f388fbc50077937a0f69e9f7ab47acba'];
$legacy_http_content = str_replace('https://sockspecs.cn/wp-content/themes/', 'http://sockspecs.cn/wp-content/themes/', $content);
if ($post->post_content !== $content && $post->post_content !== $legacy_http_content && !in_array(hash('sha256', $post->post_content), $known, true)) {
    throw new RuntimeException('Homepage changed since review; inspect before publishing.');
}
$settings = [];
foreach (['woocommerce_coming_soon', 'woocommerce_currency', 'sscn_referrals_enabled', 'sscn_wechat_login_enabled',
    'woocommerce_sscn_wechat_settings', 'woocommerce_sscn_alipay_settings'] as $key) { $settings[$key] = get_option($key); }
if ($mode === 'apply' && $post->post_content !== $content) {
    add_option('sscn_home_before_photos_20261023', ['id' => $id, 'content' => $post->post_content], '', false);
    $result = wp_update_post(wp_slash(['ID' => $id, 'post_content' => $content]), true);
    if (is_wp_error($result)) { throw new RuntimeException($result->get_error_message()); }
}
if ($mode !== 'plan' && get_post($id)->post_content !== $content) { throw new RuntimeException('Homepage content mismatch.'); }
foreach ($settings as $key => $value) {
    if (get_option($key) !== $value) { throw new RuntimeException('Protected setting changed: ' . $key); }
}
WP_CLI::success('Homepage photos ' . $mode . '; page ' . $id . '; store settings preserved.');
