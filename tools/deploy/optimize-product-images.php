<?php
/**
 * Explicit WP-CLI operation for a parent's public gallery, not a runtime plugin.
 * wp eval-file optimize-product-images.php prepare|apply SKU /private/backup-dir --user=staff
 * Originals/attachment IDs stay intact; prepare allows visual review before apply.
 */
if (!defined('WP_CLI') || !WP_CLI || !current_user_can('manage_woocommerce')) {
    throw new RuntimeException('Authorized WooCommerce WP-CLI session required.');
}
[$mode, $sku, $backup_dir] = array_pad($args ?? [], 3, '');
if (!in_array($mode, ['prepare', 'apply'], true) || !$sku || !$backup_dir) {
    WP_CLI::error('Usage: prepare|apply SKU /existing/private/backup-directory');
}
$product = wc_get_product(wc_get_product_id_by_sku($sku));
$backup_dir = realpath($backup_dir);
if (!$product || $product->is_type('variation') || !$backup_dir || !is_writable($backup_dir)
    || str_starts_with($backup_dir . '/', realpath(ABSPATH) . '/')) {
    WP_CLI::error('Parent product and writable backup directory outside the web root required.');
}
if (!wp_image_editor_supports(['mime_type' => 'image/webp'])) {
    WP_CLI::error('Installed WordPress image editor does not support WebP.');
}
$lock = fopen($backup_dir . '/optimize.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    WP_CLI::error('An optimization is already running.');
}
require_once ABSPATH . 'wp-admin/includes/image.php';
add_filter('wp_editor_set_quality', static function ($quality, $mime) {
    return $mime === 'image/webp' ? 82 : $quality;
}, 10, 2);
$ids = array_values(array_unique(array_filter(array_merge([$product->get_image_id()], $product->get_gallery_image_ids()))));
$uploads = realpath(wp_get_upload_dir()['basedir']);
$plans = [];
foreach ($ids as $id) {
    $source = realpath(get_attached_file($id));
    if (!$source || !str_starts_with($source, $uploads . '/') || !wp_attachment_is_image($id)) {
        WP_CLI::error('Image is missing or outside native uploads: ' . $id);
    }
    $backup_file = $backup_dir . '/' . $id . '.json';
    if (get_post_mime_type($id) === 'image/webp') {
        WP_CLI::log('Retained existing WebP attachment ' . $id);
        continue;
    }
    if (!in_array(get_post_mime_type($id), ['image/png', 'image/jpeg'], true)) {
        WP_CLI::error('Only PNG/JPEG still images are supported: ' . $id);
    }
    if (file_exists($backup_file)) {
        $plan = json_decode(file_get_contents($backup_file), true, 512, JSON_THROW_ON_ERROR);
        if ($plan['source'] !== $source || $plan['source_sha256'] !== hash_file('sha256', $source)
            || $plan['product_id'] !== $product->get_id()) {
            WP_CLI::error('Existing backup belongs to different content; use a new backup directory.');
        }
    } else {
        if ($mode !== 'prepare') {
            WP_CLI::error('Run prepare and inspect the images before apply.');
        }
        $plan = [
            'id' => (int) $id, 'product_id' => $product->get_id(), 'source' => $source,
            'source_sha256' => hash_file('sha256', $source),
            'mime' => get_post_mime_type($id), 'metadata' => wp_get_attachment_metadata($id),
            'target' => dirname($source) . '/' . wp_unique_filename(dirname($source), pathinfo($source, PATHINFO_FILENAME) . '-web.webp'),
        ];
    }
    if ($mode === 'prepare') {
        if (!isset($plan['target_sha256'])) {
            $editor = wp_get_image_editor($source);
            if (is_wp_error($editor)) { WP_CLI::error($editor->get_error_message()); }
            $saved = $editor->save($plan['target'], 'image/webp');
            if (is_wp_error($saved)) { WP_CLI::error($saved->get_error_message()); }
            $plan['target'] = $saved['path'];
            $plan['source_bytes'] = filesize($source);
            $plan['target_bytes'] = filesize($saved['path']);
            $original_size = wp_getimagesize($source);
            $new_size = wp_getimagesize($saved['path']);
            if ($saved['mime-type'] !== 'image/webp' || array_slice($original_size, 0, 2) !== array_slice($new_size, 0, 2)
                || $plan['target_bytes'] >= $plan['source_bytes']) {
                WP_CLI::error('Output must preserve dimensions and reduce file size; original attachment unchanged.');
            }
            $plan['target_sha256'] = hash_file('sha256', $saved['path']);
            file_put_contents($backup_file, wp_json_encode($plan, JSON_PRETTY_PRINT), LOCK_EX);
            chmod($backup_file, 0600);
        }
    }
    if (!is_file($plan['target']) || hash_file('sha256', $plan['target']) !== $plan['target_sha256']) {
        WP_CLI::error('Prepared image is missing or changed.');
    }
    $plans[] = $plan;
}
// All inputs have been checked before any attachment switches to WebP.
foreach ($plans as $plan) {
    $id = $plan['id'];
    if ($mode === 'apply') {
        try {
            $metadata = wp_generate_attachment_metadata($id, $plan['target']);
            if (empty($metadata['width']) || empty($metadata['sizes'])) {
                throw new RuntimeException('Responsive thumbnail generation failed.');
            }
            update_attached_file($id, $plan['target']);
            wp_update_attachment_metadata($id, $metadata);
            $updated = wp_update_post(['ID' => $id, 'post_mime_type' => 'image/webp'], true);
            if (is_wp_error($updated)) { throw new RuntimeException($updated->get_error_message()); }
            clean_post_cache($id);
            if (get_attached_file($id) !== $plan['target'] || get_post_mime_type($id) !== 'image/webp') {
                throw new RuntimeException('Attachment update failed.');
            }
        } catch (Throwable $error) {
            update_attached_file($id, $plan['source']);
            wp_update_attachment_metadata($id, $plan['metadata']);
            wp_update_post(['ID' => $id, 'post_mime_type' => $plan['mime']]);
            WP_CLI::error($error->getMessage() . ' Current attachment restored; earlier completed images retained.');
        }
    }
    WP_CLI::log(wp_json_encode(['id' => $id, 'mode' => $mode, 'original_bytes' => $plan['source_bytes'],
        'webp_bytes' => $plan['target_bytes'], 'file' => $plan['target']]));
}
WP_CLI::success(count($plans) . ' images ' . ($mode === 'prepare' ? 'prepared for review' : 'optimized') . '; no product or variation records changed.');
