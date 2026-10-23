<?php
/** Printable wear records and manually reviewed customer feedback. */
defined('ABSPATH') || exit;

function sscn_wear_feedback_url(): string {
    $id = absint(get_option('sscn_wear_feedback_page_id'));
    $page = $id ? get_post($id) : null;
    return $page && $page->post_type === 'page' && $page->post_status === 'publish' && !$page->post_password
        ? (string) get_permalink($page) : '';
}

function sscn_wear_download_html(): string {
    $url = add_query_arg('v', '20261022-3', get_theme_file_uri('assets/downloads/sockspecs-wear-record.pdf'));
    return '<div class="sscn-wear-actions"><a class="sscn-button" href="' . esc_url($url) . '" download="索克派-袜子穿着与耐用性记录表.pdf">下载可填写记录表（PDF）</a>'
        . '<a href="' . esc_url($url) . '" target="_blank" rel="noopener">在线查看或打印</a></div>'
        . '<p class="sscn-wear-help">可直接填写并保存。打印时请选择 A4、纵向、实际大小。</p>';
}
add_shortcode('sscn_wear_download', 'sscn_wear_download_html');

function sscn_wear_submit_html(): string {
    $support = sscn_support_page_url();
    $mail = 'mailto:feedback@sockspecs.cn?subject=' . rawurlencode('索克派穿着体验反馈');
    return '<div class="sscn-wear-actions"><a class="sscn-button" href="' . esc_url($support ? $support . '#wechat-support' : sscn_wechat_support_url()) . '">通过微信客服提交</a>'
        . '<a href="' . esc_url($mail) . '">发送反馈邮件</a></div>'
        . '<p>邮箱：<a href="mailto:feedback@sockspecs.cn">feedback@sockspecs.cn</a>。发送时请附上填好的 PDF，或纸质表格的照片、扫描件。</p>';
}
add_shortcode('sscn_wear_submit', 'sscn_wear_submit_html');

function sscn_wear_prompt_html(): string {
    $url = sscn_wear_feedback_url();
    if (!$url || is_admin()) { return ''; }
    return '<aside class="sscn-wear-prompt" aria-label="穿着体验记录"><strong>分享穿着体验</strong>'
        . '<p>填写穿着记录表，反馈日常或运动中的使用感受。符合要求的反馈经审核可获 ¥2–¥10 优惠券，每个订单限一次。</p>'
        . '<a href="' . esc_url($url) . '">查看活动与下载表格</a></aside>';
}
function sscn_render_wear_prompt(): void {
    echo sscn_wear_prompt_html(); // Fixed copy with escaped internal URL.
}
add_action('woocommerce_single_product_summary', 'sscn_render_wear_prompt', 33);
// Both classic and block order confirmation run this native hook after access checks.
add_action('woocommerce_thankyou', static function ($order_id) {
    $order = wc_get_order($order_id);
    if ($order && $order->is_paid()) { sscn_render_wear_prompt(); }
}, 35);
