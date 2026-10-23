<?php
/** Domestic customer support. The operator supplied this public WeChat service link. */
defined('ABSPATH') || exit;

function sscn_support_page_url(): string {
    $id = absint(get_option('sscn_support_page_id'));
    $page = $id ? get_post($id) : null;
    return $page && $page->post_type === 'page' && $page->post_status === 'publish' && !$page->post_password
        ? (string) get_permalink($id) : '';
}

function sscn_wechat_support_url(): string {
    return 'https://work.weixin.qq.com/kfid/kfc2e4782f067438e20';
}

function sscn_support_card_html(): string {
    return '<section class="sscn-support-card" id="wechat-support" aria-labelledby="sscn-support-title">'
        . '<div class="sscn-support-copy"><h2 id="sscn-support-title">微信客服</h2>'
        . '<p>选购、订单和售后问题，可通过微信或邮件联系我们。</p>'
        . '<p><a class="sscn-button" href="' . esc_url(sscn_wechat_support_url()) . '" rel="noopener noreferrer" target="_blank">打开微信客服</a></p>'
        . '<p>手机上点击按钮进入微信客服，电脑上可用微信扫描二维码。</p>'
        . '<p>咨询订单时，请附上订单号并说明问题。</p>'
        . '<p>客服不在线时可以留言，我们会尽快回复。</p>'
        . '<p>也可以发邮件至 <a href="mailto:hello@sockspecs.cn">hello@sockspecs.cn</a>。</p></div>'
        . '<figure class="sscn-support-qr"><a href="' . esc_url(get_theme_file_uri('assets/wechat-support-qr.png')) . '" target="_blank" rel="noopener noreferrer" aria-label="查看完整的微信客服二维码">'
        . '<img src="' . esc_url(get_theme_file_uri('assets/wechat-support-qr.png')) . '" width="700" height="908" alt="SockSpecs 微信客服二维码，使用微信扫一扫联系客服" decoding="async"></a>'
        . '<figcaption>使用微信扫一扫 · 点击图片可查看原图</figcaption></figure></section>';
}
add_shortcode('sscn_customer_support', 'sscn_support_card_html');

function sscn_support_prompt_html(): string {
    $url = sscn_support_page_url();
    if (!$url || is_admin()) { return ''; }
    return '<aside class="sscn-support-prompt" aria-label="客服咨询"><strong>需要帮助？</strong> '
        . '<a href="' . esc_url($url . '#wechat-support') . '">联系微信客服</a>'
        . '<span>选购咨询与售后服务</span></aside>';
}
function sscn_render_support_prompt(): void {
    echo sscn_support_prompt_html(); // Fixed text and escaped internal permalink.
}
// The pack form uses summary priority 30; the returns notice uses 31.
add_action('woocommerce_single_product_summary', 'sscn_render_support_prompt', 32);
add_action('woocommerce_account_dashboard', 'sscn_render_support_prompt', 20);
add_action('woocommerce_after_customer_login_form', 'sscn_render_support_prompt', 20);
add_action('woocommerce_thankyou', 'sscn_render_support_prompt', 30);
