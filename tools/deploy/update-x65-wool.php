<?php
/** Publish the operator-confirmed X65 supplier wool percentage and warm daily copy. */
use ParameterCommerce\Repository;

function x65_require($ok, $message) { if (!$ok) { throw new RuntimeException($message); } }
x65_require(defined('WP_CLI') && WP_CLI && current_user_can('manage_options') && DB_NAME === 'sockspecs_cn' && in_array(home_url(), ['http://127.0.0.1:8089','https://sockspecs.cn'], true) && get_woocommerce_currency() === 'CNY', 'Domestic administrator CLI only.');
$mode = $args[0] ?? 'verify';
x65_require(in_array($mode, ['apply','verify'], true), 'Use apply or verify.');
$file = __DIR__.'/x65-wool-20261021.json';
$m = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
$hash = hash_file('sha256', $file);
x65_require($m['site'] === 'domestic' && $m['sku'] === 'SS-001-X65' && $m['metric_key'] === 'wool_pct' && $m['value'] === 30 && $m['unit'] === '%', 'Unexpected source.');
$p = wc_get_product(wc_get_product_id_by_sku($m['sku']));
x65_require($p && $p->is_type('variable') && count($p->get_children()) === 4, 'Unexpected product.');
function x65_rows($pid) {
    global $wpdb;
    return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}pc_claims WHERE wc_product_id=%d ORDER BY id", $pid), ARRAY_A);
}
function x65_preserved($p) {
    $data = $p->get_data();
    unset($data['description'], $data['short_description'], $data['date_modified']);
    $data['meta_data'] = array_map(static fn($v) => $v->get_data(), $data['meta_data']);
    $children = [];
    foreach ($p->get_children() as $id) {
        $d = wc_get_product($id)->get_data();
        $d['meta_data'] = array_map(static fn($v) => $v->get_data(), $d['meta_data']);
        $children[$id] = $d;
    }
    $other = array_values(array_filter(x65_rows($p->get_id()), static fn($r) => $r['metric_key'] !== 'wool_pct'));
    return hash('sha256', wp_json_encode([$data, $children, $other, get_option('woocommerce_coming_soon'), get_woocommerce_currency()]));
}
$before = x65_preserved($p);
$key = 'sscn_x65_wool_20261021';
$state = get_option($key);
$wool = array_values(array_filter(x65_rows($p->get_id()), static fn($r) => $r['metric_key'] === 'wool_pct'));
x65_require(count($wool) <= 1, 'Multiple wool records require review.');
if (!$state) {
    x65_require($mode === 'apply' && !$wool, 'No migration state, or existing wool record requires review.');
    $state = ['source_hash'=>$hash, 'description'=>$p->get_description(), 'short_description'=>$p->get_short_description(), 'claim_id'=>0];
    add_option($key, $state, '', false);
}
x65_require($state['source_hash'] === $hash, 'Source changed.');
if ($mode === 'apply') {
    if (!$state['claim_id']) {
        if ($wool) {
            $candidate = Repository::find((int)$wool[0]['id']);
            x65_require(str_contains($candidate['evidence']['note'] ?? '', $hash), 'Existing wool evidence differs.');
            $state['claim_id'] = (int)$candidate['id'];
        } else {
            $state['claim_id'] = Repository::save([
                'wc_product_id'=>(string)$p->get_id(), 'wc_variation_id'=>'0',
                'metric_key'=>'wool_pct', 'value'=>'30', 'unit'=>'%', 'source_type'=>'supplier_declared',
                'size_scope'=>'X65 型号；适用于已列颜色及 EU 39–44 尺码',
                'measured_at'=>$m['source_received_at'],
                'conditions_json'=>wp_json_encode(['applicability'=>'all_variations','area'=>'whole']),
                'note'=>'厂家声明羊毛含量 30%；其余纤维比例未提供，未进行独立成分检测。',
            ], [
                'visibility'=>'private', 'public_title'=>$m['source']['title'], 'source_org'=>$m['source']['organization'],
                'note'=>'Stored source SHA-256: '.$hash.'. Archived source: x65-wool-20261021.json. Date is operator confirmation/source receipt, not laboratory testing. '.wp_json_encode($m['source'], JSON_UNESCAPED_UNICODE),
            ]);
        }
        update_option($key, $state, false);
    }
    $c = Repository::find((int)$state['claim_id']);
    x65_require((int)$c['wc_product_id'] === $p->get_id() && !(int)$c['wc_variation_id'] && $c['metric_key'] === 'wool_pct' && (float)$c['value_num'] === 30.0 && $c['unit'] === '%' && $c['source_type'] === 'supplier_declared', 'Unexpected wool claim.');
    if ($c['status'] === 'draft') { Repository::transition((int)$c['id'], (int)$c['revision'], 'reviewed'); $c = Repository::find((int)$c['id']); }
    if ($c['status'] === 'reviewed') { Repository::transition((int)$c['id'], (int)$c['revision'], 'published'); }
    x65_require(in_array($p->get_description(), [$state['description'], $m['description']], true) && in_array($p->get_short_description(), [$state['short_description'], $m['description']], true), 'Copy changed since snapshot.');
    $p->set_description($m['description']); $p->set_short_description($m['description']); $p->save();
    wc_delete_product_transients($p->get_id());
}
$p = wc_get_product($p->get_id());
x65_require($p->get_description() === $m['description'] && $p->get_short_description() === $m['description'], 'Copy mismatch.');
$wool = array_values(array_filter(x65_rows($p->get_id()), static fn($r) => $r['metric_key'] === 'wool_pct'));
x65_require(count($wool) === 1 && $wool[0]['status'] === 'published' && $wool[0]['source_type'] === 'supplier_declared' && (float)$wool[0]['value_num'] === 30.0, 'Wool publication mismatch.');
foreach (array_merge([0], $p->get_children()) as $variation) {
    $spec = Repository::public_specifications($p->get_id(), $variation);
    x65_require((float)$spec['groups']['composition']['metrics']['wool_pct']['value'] === 30.0, 'Missing shared wool projection.');
    foreach ($spec['groups']['composition']['metrics'] as $metric => $row) {
        if ($metric !== 'wool_pct') { x65_require($row['value'] === null, 'Unexpected inferred fibre percentage.'); }
    }
}
x65_require(x65_preserved($p) === $before, 'Unrelated product data or claims changed.');
WP_CLI::success('X65 warm daily copy and 30% supplier-declared wool verified for product and all four variants; prices, stock, images, other claims and visibility preserved.');
