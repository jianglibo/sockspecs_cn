<?php
/** Update public product URLs to permanent model codes. WP-CLI: eval-file FILE [plan|apply|verify]. */
if (!defined('WP_CLI') || !WP_CLI || !in_array(wp_parse_url(home_url(), PHP_URL_HOST), ['sockspecs.cn', 'sockspecs.com', '127.0.0.1', 'localhost'], true)) {
    throw new RuntimeException('Unexpected site.');
}
$mode = $args[0] ?? 'plan';
if (!in_array($mode, ['plan', 'apply', 'verify'], true)) { throw new RuntimeException('Use plan, apply or verify.'); }
function model_url_assert($ok, $message) { if (!$ok) { throw new RuntimeException($message); } }
function model_url_guard($p) {
    $data = $p->get_data();
    unset($data['slug'], $data['description'], $data['short_description'], $data['date_modified']);
    // Google updates its queue status and timestamp when the product URL changes.
    $data['meta_data'] = array_values(array_filter($data['meta_data'], static fn($meta) => !in_array($meta->key, ['_wc_gla_sync_status', '_wc_gla_synced_at'], true)));
    return hash('sha256', serialize($data));
}
function model_url_guard_matches($p, $expected) { return model_url_guard($p) === $expected; }
function model_url_links($text, $mapping) {
    // Match whole same-site URLs, including relative links and Gutenberg URL values.
    return preg_replace_callback('~https?://[^\s<>"\'\\\\]+|(?<![A-Za-z0-9:/])/(?:\?[^\s<>"\'\\\\]+|product/[^\s<>"\'\\\\]+)|(?<![A-Za-z0-9/])\?product=[^\s<>"\'\\\\]+~', static function ($match) use ($mapping) {
        $url = $match[0];
        $parsed = wp_parse_url(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if (!$parsed || (isset($parsed['host']) && strtolower($parsed['host']) !== strtolower(wp_parse_url(home_url(), PHP_URL_HOST)))) { return $url; }
        foreach ($mapping as $row) {
            $old = preg_quote($row['old'], '~');
            $new = $row['new'];
            $url = preg_replace('~([?&](?:amp;|#038;|#38;)?product=)' . $old . '(?=&|#|$)~', '${1}' . $new, $url);
            $url = preg_replace('~(/product/)' . $old . '(?=/|\?|#|$)~', '${1}' . $new, $url);
        }
        return $url;
    }, $text);
}
$key = 'pc_model_urls_20261024';
$state = get_option($key, []);
if ($mode === 'apply' && !empty($state['complete'])) { $mode = 'verify'; }
if (!$state && $mode === 'verify') { throw new RuntimeException('No URL migration snapshot.'); }
if (!$state) {
    $state = ['guard_version' => 2, 'products' => [], 'before' => [], 'posts' => [], 'menus' => [], 'visibility' => get_option('woocommerce_coming_soon'), 'complete' => false];
    for ($i = 1; $i <= 11; ++$i) {
        $code = sprintf('SS-S%04d', $i);
        $p = wc_get_product(wc_get_product_id_by_sku($code));
        model_url_assert($p && $p->is_type('variable') && $p->get_meta('_pc_public_model') === $code, 'Missing public model: ' . $code);
        $new = strtolower($code);
        $existing = get_page_by_path($new, OBJECT, 'product');
        model_url_assert(!$existing || (int)$existing->ID === $p->get_id(), 'Destination slug already in use: ' . $new);
        $state['products'][$p->get_id()] = ['old' => $p->get_slug(), 'new' => $new, 'code' => $code];
        foreach (array_merge([$p->get_id()], $p->get_children()) as $id) { $state['before'][$id] = model_url_guard(wc_get_product($id)); }
    }
    $posts = get_posts(['post_type' => ['product', 'post', 'page', 'wp_block', 'wp_navigation', 'wp_template', 'wp_template_part'], 'post_status' => ['publish', 'draft', 'pending', 'private', 'future'], 'numberposts' => -1]);
    foreach ($posts as $post) {
        foreach (['post_content', 'post_excerpt'] as $field) {
            $new = model_url_links($post->$field, $state['products']);
            if ($new !== $post->$field) { $state['posts'][$post->ID][$field] = ['old' => $post->$field, 'new' => $new]; }
        }
    }
    foreach (get_posts(['post_type' => 'nav_menu_item', 'post_status' => 'any', 'numberposts' => -1]) as $item) {
        $old = get_post_meta($item->ID, '_menu_item_url', true);
        $new = model_url_links($old, $state['products']);
        if ($old !== $new) { $state['menus'][$item->ID] = ['old' => $old, 'new' => $new]; }
    }
}
if ($mode === 'plan') {
    echo wp_json_encode(['products' => $state['products'], 'linked_posts' => array_keys($state['posts']), 'custom_menu_links' => array_keys($state['menus'])], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    return;
}
if (($state['guard_version'] ?? 1) !== 2) { throw new RuntimeException('Use verified fingerprint upgrade for the initial URL migration snapshot.'); }
if ($mode === 'apply') {
    $admin = get_users(['role' => 'administrator', 'fields' => 'ID', 'number' => 1]);
    model_url_assert((bool)$admin, 'No administrator.'); wp_set_current_user($admin[0]);
    if (!get_option($key)) { model_url_assert(add_option($key, $state, '', false), 'Migration already started.'); }
    foreach ($state['before'] as $id => $hash) { model_url_assert(model_url_guard_matches(wc_get_product($id), $hash), 'Commerce state changed before migration: ' . $id); }
    // Check every edited field before any writes; retry can accept its own completed values.
    foreach ($state['posts'] as $id => $fields) {
        foreach ($fields as $field => $values) { model_url_assert(in_array(get_post_field($field, $id, 'raw'), [$values['old'], $values['new']], true), 'Editorial conflict: ' . $id); }
    }
    foreach ($state['menus'] as $id => $values) { model_url_assert(in_array(get_post_meta($id, '_menu_item_url', true), [$values['old'], $values['new']], true), 'Menu conflict: ' . $id); }
    foreach ($state['products'] as $id => $row) {
        $p = wc_get_product($id);
        model_url_assert(in_array($p->get_slug(), [$row['old'], $row['new']], true), 'Slug changed: ' . $id);
        if ($p->get_slug() !== $row['new']) { $p->set_slug($row['new']); $p->save(); }
        model_url_assert(wc_get_product($id)->get_slug() === $row['new'], 'Slug collision: ' . $id);
        wc_delete_product_transients($id); clean_post_cache($id);
    }
    foreach ($state['posts'] as $id => $fields) {
        $update = ['ID' => $id]; foreach ($fields as $field => $values) { $update[$field] = $values['new']; }
        $result = wp_update_post(wp_slash($update), true); model_url_assert(!is_wp_error($result), 'Content save failed: ' . $id);
    }
    foreach ($state['menus'] as $id => $values) { update_post_meta($id, '_menu_item_url', $values['new']); }
}
foreach ($state['products'] as $id => $row) { model_url_assert(wc_get_product($id)->get_slug() === $row['new'], 'Unexpected final slug: ' . $id); }
foreach ($state['before'] as $id => $hash) { model_url_assert(model_url_guard_matches(wc_get_product($id), $hash), 'Commerce data changed: ' . $id); }
model_url_assert(get_option('woocommerce_coming_soon') === $state['visibility'], 'Visibility changed.');
if ($mode === 'apply') {
    foreach ($state['posts'] as $id => $fields) { foreach ($fields as $field => $values) { model_url_assert(get_post_field($field, $id, 'raw') === $values['new'], 'Link update failed: ' . $id); } }
    $state['complete'] = true; update_option($key, $state, false);
}
echo wp_json_encode(['mode' => $mode, 'products' => count($state['products']), 'linked_posts' => count($state['posts']), 'custom_menu_links' => count($state['menus']), 'commerce_unchanged' => true, 'visibility_unchanged' => true], JSON_PRETTY_PRINT);
