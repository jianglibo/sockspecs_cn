<?php
/** Real WordPress link-rewrite tests; read-only plan loads the helper. */
if (!defined('WP_CLI') || !WP_CLI || wp_get_environment_type() !== 'local') { throw new RuntimeException('Local tests only.'); }
$args = ['plan']; ob_start(); require dirname(__DIR__) . '/tools/deploy/model-product-urls.php'; ob_end_clean();
$map = [['old' => 'ss-001-x31', 'new' => 'ss-s0007']];
$home = home_url('/');
$cases = [
    [$home . '?product=ss-001-x31', $home . '?product=ss-s0007'],
    ['<a href="/?product=ss-001-x31&amp;pc_pack_size=6#pc-pack-title">X31</a>', '<a href="/?product=ss-s0007&amp;pc_pack_size=6#pc-pack-title">X31</a>'],
    ['/?lang=zh&amp;product=ss-001-x31&#038;attribute_size=M', '/?lang=zh&amp;product=ss-s0007&#038;attribute_size=M'],
    ['{"url":"/?product=ss-001-x31"}', '{"url":"/?product=ss-s0007"}'],
    ['/product/ss-001-x31/?attribute_color=black', '/product/ss-s0007/?attribute_color=black'],
    ['?product=ss-001-x31', '?product=ss-s0007'],
    ['https://example.com/?product=ss-001-x31', 'https://example.com/?product=ss-001-x31'],
    ['https://sockspecs.com/?product=ss-001-x31', 'https://sockspecs.com/?product=ss-001-x31'],
    ['/?product=ss-001-x310', '/?product=ss-001-x310'],
    ['ss-001-x31.webp ss-001-x31 model X31', 'ss-001-x31.webp ss-001-x31 model X31'],
];
foreach ($cases as $i => [$input, $expected]) {
    if (model_url_links($input, $map) !== $expected) { throw new RuntimeException('Link case failed: ' . $i . ' ' . model_url_links($input, $map)); }
}
echo count($cases) . " URL rewriting cases passed.\n";

$p = new WC_Product_Simple(); $p->set_name('FICTIONAL fingerprint test'); $p->set_regular_price('7.00');
$p->update_meta_data('_wc_gla_sync_status', 'synced');
$p->update_meta_data('_wc_gla_synced_at', '100');
foreach ($p->get_meta_data() as $meta) { $meta->apply_changes(); }
$p->apply_changes();
$before = model_url_guard($p);
$p->update_meta_data('_wc_gla_sync_status', 'pending');
$p->update_meta_data('_wc_gla_synced_at', '200');
foreach ($p->get_meta_data() as $meta) { $meta->apply_changes(); }
if (!model_url_guard_matches($p, $before)) { throw new RuntimeException('Google pending status should not change commerce fingerprint.'); }
$p->set_regular_price('9.00'); $p->apply_changes();
if (model_url_guard_matches($p, $before)) { throw new RuntimeException('Price change must fail even if Google status changes.'); }
echo "Google queue/timestamp change accepted; price change rejected.\n";
