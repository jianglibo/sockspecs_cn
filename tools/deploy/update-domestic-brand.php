<?php
/** Apply the approved domestic brand without replacing edited page content. */
if (!defined('WP_CLI') || !WP_CLI || !current_user_can('manage_options') || DB_NAME !== 'sockspecs_cn' || get_woocommerce_currency() !== 'CNY' || !in_array(home_url(), ['http://127.0.0.1:8089', 'https://sockspecs.cn'], true)) {
    throw new RuntimeException('Domestic administrator CLI only.');
}
$mode = $args[0] ?? 'verify';
if (!in_array($mode, ['apply', 'verify'], true)) {
    throw new RuntimeException('Use apply or verify.');
}
$home = get_post((int) get_option('page_on_front'));
$about = get_page_by_path('about');
if (!$home || !$about) {
    throw new RuntimeException('Expected home and about pages.');
}
$visibility = get_option('woocommerce_coming_soon');
if ($mode === 'apply') {
    add_option('sscn_brand_before_20261022', [
        'blogname' => get_option('blogname'),
        'home_id' => $home->ID, 'home_content' => $home->post_content,
        'about_id' => $about->ID, 'about_title' => $about->post_title,
    ], '', false);
    update_option('blogname', '索克派 SockSpecs');
    $content = str_replace('<p class="eyebrow">SOCKSPECS / 从一双袜子开始</p>', '<p class="eyebrow">索克派 SOCKSPECS / 从一双袜子开始</p>', $home->post_content);
    if ($content !== $home->post_content) {
        $result = wp_update_post(wp_slash(['ID' => $home->ID, 'post_content' => $content]), true);
        if (is_wp_error($result)) { throw new RuntimeException($result->get_error_message()); }
    }
    if ($about->post_title === '关于 SockSpecs') {
        $result = wp_update_post(['ID' => $about->ID, 'post_title' => '关于索克派'], true);
        if (is_wp_error($result)) { throw new RuntimeException($result->get_error_message()); }
    }
}
if (get_option('blogname') !== '索克派 SockSpecs' || get_option('woocommerce_coming_soon') !== $visibility) {
    throw new RuntimeException('Brand or visibility verification failed.');
}
WP_CLI::success('Domestic brand updated; edited page content and store visibility preserved.');
