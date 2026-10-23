<?php
/** Domestic returns-policy discovery, including WooCommerce blocks and classic pages. */
defined('ABSPATH') || exit;

function sscn_returns_policy_url(): string {
    $id = absint(get_option('sscn_returns_page_id'));
    return $id && get_post_status($id) === 'publish' ? (string) get_permalink($id) : '';
}

function sscn_returns_notice_html(): string {
    $url = sscn_returns_policy_url();
    if (!$url || is_admin() || (function_exists('is_order_received_page') && is_order_received_page())) { return ''; }
    return '<aside class="sscn-returns-notice" aria-label="退换货说明">'
        . '<strong>支持七天无理由退货</strong>'
        . '<p>正常拆包触摸可退；所购袜子须完整退回。非质量问题退回运费由买家承担。</p>'
        . '<a href="' . esc_url($url) . '">查看退换货政策</a></aside>';
}

function sscn_render_returns_notice(): void {
    echo sscn_returns_notice_html(); // Fixed copy and escaped policy permalink.
}

// Our pack form is rendered at summary priority 30, without the native cart-form hooks.
add_action('woocommerce_single_product_summary', 'sscn_render_returns_notice', 31);
add_action('woocommerce_before_cart', 'sscn_render_returns_notice', 15);
add_action('woocommerce_cart_is_empty', 'sscn_render_returns_notice', 15);
add_action('woocommerce_before_checkout_form', 'sscn_render_returns_notice', 15);
foreach (['woocommerce/cart', 'woocommerce/checkout'] as $block) {
    add_filter('render_block_' . $block, static function ($content) {
        return sscn_returns_notice_html() . $content;
    });
}
