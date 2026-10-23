<?php
/** Publish only the authorized domestic customer-support page. */
if (!defined('WP_CLI') || !WP_CLI || DB_NAME !== 'sockspecs_cn' ||
    !current_user_can('manage_options') ||
    !in_array(home_url(), ['http://127.0.0.1:8089', 'https://sockspecs.cn'], true)) {
    throw new RuntimeException('Domestic staff CLI only');
}
$mode = $args[0] ?? 'verify';
if (!in_array($mode, ['apply', 'verify'], true)) { throw new RuntimeException('Use apply or verify'); }
$content = '[sscn_customer_support]';
$page = get_post(absint(get_option('sscn_support_page_id'))) ?: get_page_by_path('contact');
if ($page && $page->post_type !== 'page') { throw new RuntimeException('Unexpected contact object'); }
if ($mode === 'apply') {
    if (!get_option('sscn_support_before_20261021')) {
        add_option('sscn_support_before_20261021', ['page' => $page ? $page->to_array() : null, 'page_id_option' => get_option('sscn_support_page_id')], '', false);
    }
    if (!$page || $page->post_content !== $content || $page->post_status !== 'publish' || $page->post_title !== '联系客服') {
        $data = ['post_type'=>'page', 'post_status'=>'publish', 'post_name'=>'contact',
            'post_title'=>'联系客服', 'post_content'=>$content, 'post_password'=>''];
        if ($page) { $data['ID'] = $page->ID; }
        $id = wp_insert_post(wp_slash($data), true);
        if (is_wp_error($id)) { throw new RuntimeException($id->get_error_message()); }
        $page = get_post($id);
    }
    update_option('sscn_support_page_id', $page->ID, false);
}
if (!$page || $page->post_content !== $content || $page->post_status !== 'publish' || $page->post_password ||
    (int)get_option('sscn_support_page_id') !== (int)$page->ID || !shortcode_exists('sscn_customer_support')) {
    throw new RuntimeException('Contact page verification failed');
}
WP_CLI::success('Customer support page verified: ' . get_permalink($page));
