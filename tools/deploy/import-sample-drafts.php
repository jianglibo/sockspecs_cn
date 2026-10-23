<?php
/** Add six sample candidates without publishing or changing existing products. */
use ParameterCommerce\Repository;
use ParameterCommerce\Suppliers;
require_once __DIR__.'/sample-categories.php';

function sample_require($condition, $message) {
    if (!$condition) { throw new RuntimeException($message); }
}
function sample_settings() {
    $out = [];
    foreach (['woocommerce_coming_soon','woocommerce_store_pages_only','woocommerce_currency','blog_public','WPLANG'] as $key) { $out[$key] = get_option($key); }
    return $out;
}
function sample_preserved() {
    $out = [];
    foreach (get_posts(['post_type'=>['product','product_variation'],'post_status'=>'any','numberposts'=>-1,'orderby'=>'ID','order'=>'ASC','fields'=>'ids']) as $id) {
        $p = wc_get_product($id);
        if ($p && !$p->get_meta('_pc_sample_drafts_operation')) { $out[$id] = $p->get_data(); }
    }
    return hash('sha256', wp_json_encode($out));
}
function sample_claims($id) {
    $all = [];
    for ($page=1; ; ++$page) {
        $rows = Repository::listing($id, $page);
        foreach (array_slice($rows,0,20) as $r) { $all[] = Repository::find((int)$r['id']); }
        if (count($rows)<=20) { return $all; }
    }
}
sample_require(defined('WP_CLI') && WP_CLI && current_user_can('manage_options'), 'Administrator CLI required.');
[$mode] = array_pad($args ?? [],1,'plan');
sample_require(in_array($mode,['plan','apply','verify'],true), 'Use plan/apply/verify.');
$root = __DIR__.'/sample-drafts-20261019';
$file = $root.'/products.json';
$input = json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR);
$operation = $input['operation'];
sample_require($operation==='sample-drafts-20261019-v1' && count($input['products'])===6, 'Wrong manifest.');
$cn = $input['site']==='domestic';
$allowed = $cn ? ['https://sockspecs.cn','http://127.0.0.1:8089'] : ['https://sockspecs.com','http://127.0.0.1:8088'];
sample_require(in_array(home_url(),$allowed,true), 'Wrong target site.');
sample_require(!$cn || (DB_NAME==='sockspecs_cn' && (wp_get_environment_type()==='local' || (defined('SSCN_DOMESTIC_SITE') && SSCN_DOMESTIC_SITE))), 'Domestic guard failed.');
sample_require($cn || !defined('SSCN_DOMESTIC_SITE'), 'International guard failed.');
global $wpdb;
$lock = 'pc_sample_drafts_20261019_'.get_current_blog_id();
sample_require((int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 0)',$lock))===1,'Another import is running.');
try {
    $key = 'pc_sample_drafts_20261019';
    $state = get_option($key);
    $digest = hash_file('sha256',$file);
    sample_require(!$state || $state['sha256']===$digest,'Manifest changed since import; review required.');
    $plan = [];
    foreach ($input['products'] as $r) {
        $id = wc_get_product_id_by_sku($r['sku']);
        sample_require(!$id || wc_get_product($id)->get_meta('_pc_sample_drafts_operation')===$operation,'Existing SKU belongs to another product: '.$r['sku']);
        foreach ($r['variants'] as $v) {
            $vid = wc_get_product_id_by_sku($v['sku']);
            sample_require(!$vid || ($id && wc_get_product($vid)->get_parent_id()===$id),'Variation collision: '.$v['sku']);
        }
        if (isset($r['image'])) { sample_require(hash_file('sha256',$root.'/'.$r['image']['file'])===$r['image']['sha256'],'Image hash mismatch.'); }
        $plan[] = ['sku'=>$r['sku'],'id'=>$id,'variations'=>count($r['variants']),'status'=>'draft'];
    }
    $supplier = (int)get_option('pc_supplier_code_001');
    sample_require(!$supplier || (get_post_type($supplier)==='pc_supplier' && get_post_status($supplier)==='private' && get_post_meta($supplier,'_pc_supplier_identity',true)==='mingjun-catalog'),'Supplier 001 collision.');
    if ($mode==='plan') { WP_CLI::line(wp_json_encode(['plan'=>$plan,'settings'=>sample_settings()],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)); return; }
    if ($mode==='apply' && (!$state || $state['status']!=='complete')) {
        $state = $state ?: ['sha256'=>$digest,'settings'=>sample_settings(),'preserved'=>sample_preserved(),'products'=>[],'status'=>'applying'];
        require_once ABSPATH.'wp-admin/includes/file.php';
        require_once ABSPATH.'wp-admin/includes/media.php';
        require_once ABSPATH.'wp-admin/includes/image.php';
        update_option($key,$state,false);
        try {
            if (!$supplier) {
                $supplier = wp_insert_post(['post_type'=>'pc_supplier','post_status'=>'private','post_title'=>$cn?'名郡':'Mingjun'],true);
                sample_require(!is_wp_error($supplier),'Supplier creation failed.');
                update_post_meta($supplier,'_pc_supplier_identity','mingjun-catalog');
                Suppliers::save($supplier,['code'=>'001','verification_notes'=>$cn?'用户指定的目录供应商，主体身份未独立核验。':'Operator-identified catalog supplier; legal identity not independently verified.']);
            }
            foreach ($input['products'] as $r) {
                if (isset($state['products'][$r['sku']])) { continue; }
                sample_require(!wc_get_product_id_by_sku($r['sku']),'Partial draft retained for review; do not overwrite it automatically.');
                $p = new WC_Product_Variable();
                $p->set_name($r['name']); $p->set_sku($r['sku']); $p->set_slug(strtolower($r['sku'])); $p->set_status('draft');
                $p->set_description($r['description']); $p->set_short_description($r['description']);
                $p->set_manage_stock(false); $p->set_stock_quantity(null); $p->set_stock_status('outofstock'); $p->set_backorders('no');
                $p->update_meta_data('_pc_sample_drafts_operation',$operation);
                $p->update_meta_data('_pc_selected_supplier_model',$r['model']);
                $p->update_meta_data('_pc_supplier_id',$supplier);
                $p->update_meta_data('_pc_sample_source',$r['source']);
                $p->update_meta_data('_pc_sample_review_notes',$r['review_notes']);
                $p->update_meta_data('_pc_description_suggestion',['short'=>$cn?'已订购样品，等待评估。':'Samples ordered; evaluation pending.','full'=>$r['review_notes']]);
                if ($cn) { $p->update_meta_data('_sscn_catalog_preview','yes'); }
                $options=[];
                foreach ($r['variants'] as $v) { foreach ($v['attributes'] as $name=>$value) { $options[$name][]=$value; } }
                $attributes=[];
                foreach ($options as $name=>$values) {
                    $a = new WC_Product_Attribute(); $a->set_name(ucfirst($name)); $a->set_options(array_values(array_unique($values))); $a->set_visible(true); $a->set_variation(true); $attributes[]=$a;
                }
                $p->set_attributes($attributes); $p->set_category_ids(pc_sample_category_ids($r['model'],$cn,true)); $p->save();
                if (isset($r['image'])) {
                    $tmp=wp_tempnam(basename($r['image']['file']));
                    sample_require(copy($root.'/'.$r['image']['file'],$tmp),'Image staging failed.');
                    $aid=media_handle_sideload(['name'=>basename($r['image']['file']),'tmp_name'=>$tmp],$p->get_id(),$r['name']);
                    sample_require(!is_wp_error($aid),'Image import failed.');
                    update_post_meta($aid,'_wp_attachment_image_alt',$r['name']);
                    $p->set_image_id($aid); $p->save();
                }
                foreach ($r['variants'] as $v) {
                    $child=new WC_Product_Variation(); $child->set_parent_id($p->get_id()); $child->set_sku($v['sku']); $child->set_status('publish'); $child->set_attributes($v['attributes']);
                    $child->set_manage_stock(false); $child->set_stock_quantity(null); $child->set_stock_status('outofstock'); $child->set_backorders('no');
                    $child->set_regular_price(''); $child->set_sale_price(''); $child->set_price('');
                    $child->update_meta_data('_pc_sample_drafts_operation',$operation); $child->save();
                }
                foreach ($r['claims'] as $c) {
                    $measurement=in_array($c['metric_key'],['pair_weight_g','catalog_height_mm','foot_length_mm'],true);
                    $conditions=$measurement ? ['basis'=>'supplier_catalog','scope'=>'catalog reference; size coverage unconfirmed'] : [];
                    if ($c['metric_key']==='pair_weight_g') { $conditions+=['quantity'=>'one pair','packaging'=>'excluded']; }
                    $note=($cn?'样品采购草稿；未经独立检测。尺寸测量位置与具体尺码范围待确认。':'Sample procurement draft; not independently tested. Dimension endpoints and exact size scope remain unconfirmed.').' '.$c['note'].' '.$r['review_notes'];
                    Repository::save(['wc_product_id'=>(string)$p->get_id(),'wc_variation_id'=>'0','metric_key'=>$c['metric_key'],'value'=>$c['value'],'unit'=>$c['unit'],'source_type'=>'supplier_declared','conditions_json'=>wp_json_encode((object)$conditions),'note'=>$note],['visibility'=>'private','public_title'=>$cn?'供应商资料 — '.$r['model']:'Supplier source — '.$r['model'],'source_org'=>'Mingjun','note'=>wp_json_encode($r['source'],JSON_UNESCAPED_UNICODE)]);
                }
                WC_Product_Variable::sync($p->get_id()); wc_delete_product_transients($p->get_id());
                $state['products'][$r['sku']]=$p->get_id(); update_option($key,$state,false);
            }
            sample_require(sample_settings()===$state['settings'] && sample_preserved()===$state['preserved'],'Existing products/settings changed.');
            $state['status']='complete'; update_option($key,$state,false);
            // Repository owns each claim transaction; completed products are checkpointed.
        } catch (Throwable $e) { throw $e; }
    }
    sample_require($state && $state['status']==='complete','Apply first.');
    $checks=0; $check=function($ok,$msg)use(&$checks){sample_require($ok,$msg); ++$checks;}; $report=[];
    foreach ($input['products'] as $r) {
        $p=wc_get_product(wc_get_product_id_by_sku($r['sku']));
        $check($p && $p->get_id()===$state['products'][$r['sku']] && $p->get_status()==='draft','Parent must be its recorded draft.');
        $cats=$p->get_category_ids(); sort($cats); $check($cats===pc_sample_category_ids($r['model'],$cn),'Product categories missing or incorrect.');
        $check($p->get_name()===$r['name'] && $p->get_description()===$r['description'],'Localized content mismatch.');
        $check($p->get_price()==='' && $p->get_stock_quantity()===null && !$p->get_manage_stock(),'Parent commerce values must remain unset.');
        $check(count($p->get_children())===count($r['variants']),'Variation count mismatch.');
        foreach ($r['variants'] as $v) {
            $child=wc_get_product(wc_get_product_id_by_sku($v['sku']));
            $check($child && $child->get_parent_id()===$p->get_id() && $child->get_attributes()===$v['attributes'],'Variation identity mismatch.');
            $check($child->get_price()==='' && $child->get_regular_price()==='' && $child->get_sale_price()==='' && $child->get_stock_quantity()===null && !$child->get_manage_stock() && $child->get_weight()==='','Unset price/stock/shipping weight required.');
        }
        $claims=sample_claims($p->get_id()); $by_metric=[]; foreach($claims as $c){$by_metric[$c['metric_key']]=$c;} foreach($r['claims'] as $expected){$actual=$by_metric[$expected['metric_key']]??null; $check($actual && (string)($actual['value_enum']!==null && $actual['value_enum']!=='' ? $actual['value_enum'] : (float)$actual['value_num'])===(string)(is_numeric($expected['value'])?(float)$expected['value']:$expected['value']) && $actual['unit']===$expected['unit'],'Structured parameter differs from manifest.');} $check(count($claims)===count($r['claims']),'Claim count mismatch.');
        foreach ($claims as $c) { $check($c['status']==='draft' && !$c['published_at'] && $c['evidence']['visibility']==='private' && (int)$c['wc_variation_id']===0,'Evidence must remain unpublished, private and product-level.'); }
        if (isset($r['image'])) { $check($p->get_image_id() && get_post_mime_type($p->get_image_id())==='image/webp' && hash_file('sha256',get_attached_file($p->get_image_id()))===$r['image']['sha256'],'Image mismatch.'); }
        $uid=get_current_user_id(); wp_set_current_user(0);
        $check(!$p->is_purchasable() && !$p->is_visible() && !is_post_publicly_viewable($p->get_id()),'Draft exposed to visitors.');
        foreach ($p->get_children() as $cid) { $check(!wc_get_product($cid)->is_purchasable(),'Variation is purchasable.'); }
        wp_set_current_user($uid);
        $report[]=['sku'=>$r['sku'],'id'=>$p->get_id(),'status'=>$p->get_status(),'variations'=>count($r['variants']),'edit_url'=>admin_url('post.php?post='.$p->get_id().'&action=edit')];
    }
    $check(sample_settings()===$state['settings'],'Store settings changed.');
    $check(sample_preserved()===$state['preserved'],'Existing catalog changed.');
    WP_CLI::line(wp_json_encode(['mode'=>$mode,'checks'=>$checks,'products'=>$report,'settings'=>sample_settings()],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
} finally { $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock)); }
