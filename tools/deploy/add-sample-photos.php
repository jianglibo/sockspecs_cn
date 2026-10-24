<?php
/** Append reviewed operator photographs, preserving existing galleries. */
if (!defined('WP_CLI') || !WP_CLI || DB_NAME !== 'sockspecs_cn' || !current_user_can('manage_options') ||
    !in_array(home_url(), ['http://127.0.0.1:8089', 'https://sockspecs.cn'], true)) {
    throw new RuntimeException('Domestic administrator CLI only.');
}
$mode = $args[0] ?? 'prepare';
if (!in_array($mode, ['prepare', 'apply', 'verify', 'rollback'], true)) {
    throw new RuntimeException('Use prepare, apply, verify or rollback.');
}
$dir = __DIR__ . '/sample-photos-20261024';
$manifest = json_decode(file_get_contents($dir . '/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
$key = 'sscn_sample_photos_20261024';
$state = get_option($key, []);
function samplePhotosFingerprint($product) {
    $data = $product->get_data();
    unset($data['gallery_image_ids'], $data['date_modified']);
    $data['meta_data'] = array_map(static fn($m) => $m->get_data(), $data['meta_data']);
    $children = [];
    foreach ($product->get_children() as $id) {
        $child = wc_get_product($id)->get_data();
        $child['meta_data'] = array_map(static fn($m) => $m->get_data(), $child['meta_data']);
        $children[$id] = $child;
    }
    return hash('sha256', wp_json_encode([$data, $children, get_option('woocommerce_coming_soon')]));
}
$products = [];
foreach ($manifest as $row) {
    $sku = $row['sku'];
    $product = wc_get_product(wc_get_product_id_by_sku($sku));
    if (!$product || !$product->is_type('variable') || $product->get_meta('_pc_selected_supplier_model') !== $row['original_model']) {
        throw new RuntimeException('Unexpected product identity: ' . $sku);
    }
    $file = $dir . '/' . basename($row['file']);
    if (!is_file($file) || hash_file('sha256', $file) !== $row['sha256'] || (wp_getimagesize($file)['mime'] ?? '') !== 'image/webp') {
        throw new RuntimeException('Reviewed file mismatch: ' . $sku);
    }
    if (isset($state[$sku]) && ($state[$sku]['id'] !== $product->get_id() || $state[$sku]['fingerprint'] !== samplePhotosFingerprint($product))) {
        throw new RuntimeException('Product changed since snapshot: ' . $sku);
    }
    if (!isset($state[$sku]) && $mode !== 'prepare') {
        throw new RuntimeException('Run prepare first.');
    }
    $products[$sku] = $product;
}
if ($mode === 'prepare') {
    foreach ($products as $sku => $product) {
        if (!isset($state[$sku])) {
            $state[$sku] = ['id' => $product->get_id(), 'gallery' => $product->get_gallery_image_ids(), 'fingerprint' => samplePhotosFingerprint($product)];
        }
        WP_CLI::log($sku . ': existing gallery count ' . count($state[$sku]['gallery']));
    }
    update_option($key, $state, false);
    WP_CLI::success('Identity, WebP checksums and preservation snapshots prepared.');
    return;
}
if ($mode === 'apply') {
    if (!wp_image_editor_supports(['mime_type' => 'image/webp'])) {
        throw new RuntimeException('WebP editor required.');
    }
    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    add_filter('intermediate_image_sizes_advanced', static function ($sizes) {
        foreach (['woocommerce_thumbnail', 'woocommerce_gallery_thumbnail'] as $name) {
            if (isset($sizes[$name])) $sizes[$name]['crop'] = false;
        }
        return $sizes;
    });
}
foreach ($manifest as $row) {
    $sku = $row['sku'];
    $product = $products[$sku];
    $snapshot = &$state[$sku];
    $expected = $snapshot['gallery'];
    if (!empty($snapshot['attachment'])) $expected[] = $snapshot['attachment'];
    if ($product->get_gallery_image_ids() !== $snapshot['gallery'] && $product->get_gallery_image_ids() !== $expected) {
        throw new RuntimeException('Gallery edited since snapshot: ' . $sku);
    }
    if ($mode === 'rollback') {
        $product->set_gallery_image_ids($snapshot['gallery']);
        $product->save();
        wc_delete_product_transients($product->get_id());
        continue;
    }
    if ($mode === 'apply') {
        if (empty($snapshot['attachment'])) {
            $tmp = wp_tempnam($row['file']);
            if (!copy($dir . '/' . $row['file'], $tmp)) throw new RuntimeException('Cannot stage image.');
            $id = media_handle_sideload(['name' => $row['file'], 'tmp_name' => $tmp], $product->get_id(), $row['alt']);
            if (is_wp_error($id)) { @unlink($tmp); throw new RuntimeException($id->get_error_message()); }
            $snapshot['attachment'] = $id;
            update_option($key, $state, false);
            update_post_meta($id, '_wp_attachment_image_alt', $row['alt']);
            wp_update_post(['ID' => $id, 'post_excerpt' => $row['caption']]);
        }
        $expected = array_merge($snapshot['gallery'], [$snapshot['attachment']]);
        if ($product->get_gallery_image_ids() !== $expected) {
            $product->set_gallery_image_ids($expected);
            $product->save();
            wc_delete_product_transients($product->get_id());
        }
    }
    $id = $snapshot['attachment'] ?? 0;
    $product = wc_get_product($product->get_id());
    if (!$id || $product->get_gallery_image_ids() !== $expected || samplePhotosFingerprint($product) !== $snapshot['fingerprint'] ||
        hash_file('sha256', get_attached_file($id)) !== $row['sha256'] || !wp_get_attachment_image_srcset($id, 'full')) {
        throw new RuntimeException('Gallery, responsive media or preservation verification failed: ' . $sku);
    }
    WP_CLI::log($sku . ': attachment ' . $id . ', ' . wp_get_attachment_url($id));
    unset($snapshot);
}
WP_CLI::success($mode === 'rollback' ? 'Original galleries restored; media retained.' : 'All reviewed sample photographs verified; existing images, copy, prices and stock preserved.');
