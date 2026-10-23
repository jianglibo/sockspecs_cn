<?php
/** Publish only the approved domestic printable wear-feedback page. */
if (!defined('WP_CLI') || !WP_CLI || DB_NAME !== 'sockspecs_cn' || !current_user_can('manage_options') ||
    !in_array(home_url(), ['http://127.0.0.1:8089', 'https://sockspecs.cn'], true) || get_woocommerce_currency() !== 'CNY') {
    throw new RuntimeException('Domestic administrator CLI only.');
}
$mode = $args[0] ?? 'verify';
if (!in_array($mode, ['apply', 'verify'], true)) { throw new RuntimeException('Use apply or verify.'); }
$content = trim(file_get_contents(__DIR__ . '/content/wear-feedback.html'));
$page = get_post(absint(get_option('sscn_wear_feedback_page_id'))) ?: get_page_by_path('wear-feedback');
if ($page && ($page->post_type !== 'page' || ($page->post_content !== $content && !in_array(hash('sha256', trim($page->post_content)), ['d6fc787f6bce4873f9ccfe5627798ce57577ac56993ee2f94123a24c67159dde', '2449451c59a5b82a5f953f9c591187e28b45222580812f84da6a5c016ee6bde7', '2fb2fdc21ac6c72a6a7a6bade745dc974fff6f5899720e7f842e212e79ab0aaa'], true)) || $page->post_password)) {
    throw new RuntimeException('Existing page differs; preserve editor changes and review first.');
}
$visibility = get_option('woocommerce_coming_soon');
if ($mode === 'apply') {
    if (!$page) {
        $id = wp_insert_post(wp_slash(['post_type'=>'page', 'post_status'=>'publish', 'post_name'=>'wear-feedback',
            'post_title'=>'穿着体验记录', 'post_content'=>$content, 'comment_status'=>'closed', 'ping_status'=>'closed']), true);
        if (is_wp_error($id)) { throw new RuntimeException($id->get_error_message()); }
        $page = get_post($id);
    }
    if ($page->post_content !== $content) {
        add_option('sscn_wear_feedback_original_20261022', $page->post_content, '', false);
        $updated = wp_update_post(wp_slash(['ID'=>$page->ID, 'post_content'=>$content]), true);
        if (is_wp_error($updated)) { throw new RuntimeException($updated->get_error_message()); }
        $page = get_post($page->ID);
    }
    update_option('sscn_wear_feedback_page_id', $page->ID, false);
}
$pdf = get_theme_file_path('assets/downloads/sockspecs-wear-record.pdf');
if (!$page || $page->post_content !== $content || $page->post_status !== 'publish' || !sscn_wear_feedback_url() ||
    !shortcode_exists('sscn_wear_download') || !shortcode_exists('sscn_wear_submit') ||
    !is_file($pdf) || substr(file_get_contents($pdf), 0, 5) !== '%PDF-' ||
    get_option('woocommerce_coming_soon') !== $visibility) {
    throw new RuntimeException('Feedback page, PDF or visibility verification failed.');
}
WP_CLI::success('Wear feedback page verified: ' . get_permalink($page));
WP_CLI::log('PDF SHA256: ' . hash_file('sha256', $pdf));
