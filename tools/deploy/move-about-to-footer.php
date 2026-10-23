<?php
/** Move only the domestic About entry out of the primary menu. */
if (!defined('WP_CLI') || !WP_CLI || !current_user_can('manage_options') || DB_NAME !== 'sockspecs_cn' || get_woocommerce_currency() !== 'CNY' || !in_array(home_url(), ['http://127.0.0.1:8089', 'https://sockspecs.cn'], true)) {
    throw new RuntimeException('Domestic administrator CLI only.');
}
$mode = $args[0] ?? 'plan';
if (!in_array($mode, ['plan', 'apply', 'verify'], true)) {
    throw new RuntimeException('Use plan, apply or verify.');
}
$about = get_page_by_path('about');
$locations = get_nav_menu_locations();
$menu_id = (int) ($locations['primary'] ?? 0);
if (!$about || $about->post_status !== 'publish' || !$menu_id) {
    throw new RuntimeException('Expected a published About page and primary menu.');
}
$visibility = get_option('woocommerce_coming_soon');
$items = wp_get_nav_menu_items($menu_id) ?: [];
$matches = array_values(array_filter($items, static function ($item) use ($about) {
    return $item->type === 'post_type' && $item->object === 'page' && (int) $item->object_id === (int) $about->ID;
}));
if ($mode === 'plan') {
    WP_CLI::log(wp_json_encode(['menu_id' => $menu_id, 'about_id' => $about->ID, 'remove_item_ids' => array_column($matches, 'ID')], JSON_UNESCAPED_UNICODE));
    return;
}
if ($mode === 'apply') {
    add_option('sscn_about_navigation_before_20261022', array_map(static function ($item) {
        return ['ID' => $item->ID, 'post_status' => get_post_status($item->ID)];
    }, $matches), '', false);
    foreach ($matches as $item) {
        // Retain the menu entry as a draft so the move is reversible.
        $result = wp_update_post(['ID' => $item->ID, 'post_status' => 'draft'], true);
        if (is_wp_error($result)) {
            throw new RuntimeException($result->get_error_message());
        }
    }
}
foreach (wp_get_nav_menu_items($menu_id) ?: [] as $item) {
    if ($item->post_status === 'publish' && $item->object === 'page' && (int) $item->object_id === (int) $about->ID) {
        throw new RuntimeException('About is still published in the primary menu.');
    }
}
if (get_option('woocommerce_coming_soon') !== $visibility || get_post_status($about->ID) !== 'publish') {
    throw new RuntimeException('Store visibility or About page changed.');
}
WP_CLI::success('About is available in the theme footer; primary menu entry unpublished.');
