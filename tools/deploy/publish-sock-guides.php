<?php
/** Publish the three approved Chinese guides; domestic staff CLI only. */
if (!defined('WP_CLI') || !WP_CLI || DB_NAME !== 'sockspecs_cn' ||
    !current_user_can('manage_options') ||
    !in_array(home_url(), ['http://127.0.0.1:8089', 'https://sockspecs.cn'], true)) {
    throw new RuntimeException('Domestic staff CLI only');
}
$mode = $args[0] ?? 'verify';
if (!in_array($mode, ['apply', 'verify'], true)) { throw new RuntimeException('Use apply or verify'); }
$definitions = [
    'sock-price' => ['袜子的价格差在哪里？', '了解材料品质、用量和织造工艺如何影响袜子的价格。'],
    'wool-content' => ['羊毛含量怎么看？', '区分成品含量、纱线配比与羊毛种类，查看检测报告的适用范围。'],
    'sock-thickness' => ['日常穿着怎么选厚薄和毛圈？', '从鞋内空间、气温和活动量出发，选择袜子厚薄与毛圈分布。'],
];
$menu_id = (int) (get_nav_menu_locations()['primary'] ?? 0);
if (!$menu_id) { throw new RuntimeException('Primary menu missing'); }
$state = get_option('sscn_sock_guides_20261021', []);
$slugs = array_merge(array_keys($definitions), ['sock-guides']);
foreach ($slugs as $slug) {
    $existing = get_page_by_path($slug, OBJECT, ['page', 'post']);
    if ($existing && (int)($state[$slug] ?? 0) !== (int)$existing->ID) {
        throw new RuntimeException('Unmanaged slug already exists: ' . $slug);
    }
}
$before = ['menu' => wp_get_nav_menu_items($menu_id), 'state' => $state, 'posts' => []];
foreach ($state as $id) { $before['posts'][] = get_post($id, ARRAY_A); }
if ($mode === 'apply' && !get_option('sscn_sock_guides_before_20261021')) {
    add_option('sscn_sock_guides_before_20261021', $before, '', false);
}
$category = get_term_by('slug', 'sock-guides', 'category');
if (!$category && $mode === 'apply') {
    $created = wp_insert_term('选袜指南', 'category', ['slug' => 'sock-guides']);
    if (is_wp_error($created)) { throw new RuntimeException($created->get_error_message()); }
    $category = get_term($created['term_id'], 'category');
}
if (!$category || is_wp_error($category)) { throw new RuntimeException('Guide category missing'); }
$upsert = static function ($slug, $type, $title, $body, $excerpt = '') use ($mode, &$state, $category) {
    $data = ['post_type'=>$type, 'post_status'=>'publish', 'post_name'=>$slug,
        'post_title'=>$title, 'post_content'=>$body, 'post_excerpt'=>$excerpt,
        'post_password'=>'', 'comment_status'=>'closed', 'ping_status'=>'closed'];
    $post = get_post((int)($state[$slug] ?? 0));
    if ($post && $post->post_type !== $type) { throw new RuntimeException('Wrong post type'); }
    $changed = !$post;
    foreach ($data as $key=>$value) { if ($post && $post->$key !== $value) { $changed = true; } }
    if ($mode === 'apply' && $changed) {
        if ($post) { $data['ID'] = $post->ID; }
        $id = wp_insert_post(wp_slash($data), true);
        if (is_wp_error($id)) { throw new RuntimeException($id->get_error_message()); }
        $state[$slug] = $id;
        update_option('sscn_sock_guides_20261021', $state, false);
        $post = get_post($id);
    }
    if (!$post) { throw new RuntimeException('Missing guide: '.$slug); }
    foreach ($data as $key=>$value) {
        if ($key !== 'ID' && $post->$key !== $value) { throw new RuntimeException('Guide mismatch: '.$slug.' '.$key); }
    }
    if ($type === 'post') {
        if ($mode === 'apply') { wp_set_post_categories($post->ID, [(int)$category->term_id]); }
        if (wp_get_post_categories($post->ID) !== [(int)$category->term_id]) { throw new RuntimeException('Category mismatch'); }
    }
    return $post->ID;
};
// Reserve the index first so all article links work with either permalink scheme.
$index_id = (int)($state['sock-guides'] ?? 0);
if (!$index_id && $mode === 'apply') { $index_id = $upsert('sock-guides', 'page', '选袜指南', '<p>选袜指南正在整理。</p>'); }
if (!$index_id) { throw new RuntimeException('Missing guide index'); }
$index_url = esc_url(get_permalink($index_id));
foreach ($definitions as $slug=>$entry) {
    $body = file_get_contents(__DIR__.'/content/'.$slug.'.html');
    if (!$body) { throw new RuntimeException('Missing article source'); }
    $body .= '<p class="sscn-guide-back"><a href="'.$index_url.'">← 返回选袜指南</a></p>';
    $upsert($slug, 'post', $entry[0], $body, $entry[1]);
}
$body = '<section class="sscn-guides"><p class="sscn-guide-lead">了解袜子的材料、厚薄和工艺，按自己的穿着需要选择。</p><div class="sscn-guide-list">';
foreach ($definitions as $slug=>$entry) {
    $body .= '<article><h2><a href="'.esc_url(get_permalink($state[$slug])).'">'.esc_html($entry[0]).'</a></h2><p>'.esc_html($entry[1]).'</p></article>';
}
$catalog = get_page_by_path('catalog');
if (!$catalog) { throw new RuntimeException('Catalog missing'); }
$body .= '</div><p><a href="'.esc_url(get_permalink($catalog)).'">查看全部袜子 →</a></p></section>';
$upsert('sock-guides', 'page', '选袜指南', $body);
$items = wp_get_nav_menu_items($menu_id) ?: [];
$matches = array_values(array_filter($items, static function ($item) use ($index_id) {
    return $item->object === 'page' && (int)$item->object_id === $index_id;
}));
if (count($matches) > 1) { throw new RuntimeException('Duplicate guide menu items'); }
if (!$matches && $mode === 'apply') {
    $result = wp_update_nav_menu_item($menu_id, 0, ['menu-item-object-id'=>$index_id,
        'menu-item-object'=>'page','menu-item-type'=>'post_type','menu-item-status'=>'publish','menu-item-title'=>'选袜指南']);
    if (is_wp_error($result)) { throw new RuntimeException($result->get_error_message()); }
}
$matches = array_filter(wp_get_nav_menu_items($menu_id) ?: [], static function ($item) use ($index_id) {
    return $item->object === 'page' && (int)$item->object_id === $index_id && $item->post_status === 'publish';
});
if (count($matches) !== 1) { throw new RuntimeException('Guide navigation verification failed'); }
foreach ($state as $slug=>$id) { WP_CLI::log($slug.': '.get_permalink($id)); }
WP_CLI::success('Three Chinese guides, index, category and navigation verified.');
