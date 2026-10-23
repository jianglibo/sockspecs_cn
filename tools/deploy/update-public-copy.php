<?php
/** Reviewed domestic public copy only; refuse concurrent editor changes. */
if (!defined('WP_CLI') || !WP_CLI || DB_NAME !== 'sockspecs_cn' || !current_user_can('manage_options') ||
    home_url() !== 'https://sockspecs.cn' || get_woocommerce_currency() !== 'CNY') {
    throw new RuntimeException('Domestic production administrator CLI only.');
}
$mode = $args[0] ?? 'plan';
if (!in_array($mode, ['plan', 'apply', 'verify'], true)) { throw new RuntimeException('Use plan, apply or verify.'); }
$entries = json_decode(file_get_contents(__DIR__ . '/content/copy-review-20261022.json'), true, 512, JSON_THROW_ON_ERROR);
$protected = ['woocommerce_coming_soon', 'woocommerce_currency', 'sscn_referrals_enabled', 'sscn_wechat_login_enabled',
    'woocommerce_sscn_wechat_settings', 'woocommerce_sscn_alipay_settings'];
$settings = [];
foreach ($protected as $key) { $settings[$key] = get_option($key); }
$backup = [];
// Validate every target before writing any page.
foreach ($entries as $entry) {
    $post = get_post($entry['id']);
    if (!$post || $post->post_type !== $entry['type'] || $post->post_status !== 'publish' || $post->post_password) {
        throw new RuntimeException('Unexpected publication target: ' . $entry['id']);
    }
    foreach ($entry['fields'] as $key => $value) {
        if (!in_array($key, ['post_content', 'post_excerpt'], true)) { throw new RuntimeException('Unsupported field.'); }
        if ($post->$key !== $value && hash('sha256', $post->$key) !== $entry['before'][$key]) {
            throw new RuntimeException('Page changed since review: ' . $post->ID . ' ' . $key);
        }
        if ($mode === 'verify' && $post->$key !== $value) { throw new RuntimeException('Copy mismatch: ' . $post->ID); }
        $backup[$post->ID][$key] = $post->$key;
    }
    WP_CLI::log($post->ID . ': ' . $post->post_title);
}
if ($mode === 'apply') {
    add_option('sscn_public_copy_before_20261022', $backup, '', false);
    foreach ($entries as $entry) {
        $post = get_post($entry['id']);
        $changed = false;
        foreach ($entry['fields'] as $key => $value) { if ($post->$key !== $value) { $changed = true; } }
        if ($changed) {
            $result = wp_update_post(wp_slash(array_merge(['ID' => $post->ID], $entry['fields'])), true);
            if (is_wp_error($result)) { throw new RuntimeException($result->get_error_message()); }
        }
        $post = get_post($entry['id']);
        foreach ($entry['fields'] as $key => $value) {
            if ($post->$key !== $value) { throw new RuntimeException('Saved copy mismatch: ' . $post->ID); }
        }
    }
}
foreach ($settings as $key => $value) {
    if (get_option($key) !== $value) { throw new RuntimeException('Protected option changed: ' . $key); }
}
WP_CLI::success('Public copy ' . $mode . ': ' . count($entries) . ' pages/posts; store settings preserved.');
