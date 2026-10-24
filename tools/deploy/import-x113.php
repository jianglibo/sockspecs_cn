<?php
/** Guarded domestic-only X113 draft import; plan/apply/verify, safe to repeat. */
use ParameterCommerce\Repository;
use ParameterCommerce\Product_Identity;
function x113_check($ok, $message) { if (!$ok) throw new RuntimeException($message); }
function x113_settings() {
    $out=[];
    foreach (['woocommerce_coming_soon','woocommerce_store_pages_only','woocommerce_currency','blog_public','WPLANG'] as $k) $out[$k]=get_option($k);
    return $out;
}
function x113_existing() {
    $out=[];
    foreach (get_posts(['post_type'=>['product','product_variation'],'post_status'=>'any','numberposts'=>-1,'orderby'=>'ID','order'=>'ASC','fields'=>'ids']) as $id) {
        $p=wc_get_product($id);
        if (!$p || $p->get_meta('_sscn_x113_import')) continue;
        $data=$p->get_data();
        $data['meta_data']=array_map(static fn($m)=>$m->get_data(),$data['meta_data']);
        $out[$id]=$data;
    }
    return hash('sha256',wp_json_encode($out));
}
x113_check(defined('WP_CLI') && WP_CLI && current_user_can('manage_options') && DB_NAME==='sockspecs_cn' && in_array(home_url(),['http://127.0.0.1:8089','https://sockspecs.cn'],true),'Domestic administrator CLI required.');
$mode=$args[0]??'plan';
x113_check(in_array($mode,['plan','apply','verify'],true),'Use plan/apply/verify.');
$dir=__DIR__.'/x113-20261024';
$r=json_decode(file_get_contents($dir.'/product.json'),true,512,JSON_THROW_ON_ERROR);
$digest=hash_file('sha256',$dir.'/product.json');
x113_check($r['sku']==='SS-S0012' && $r['model']==='X113' && $r['operation']==='x113-20261024-v1','Unexpected manifest.');
x113_check(hash_file('sha256',$dir.'/source.json')===$r['source_sha256'],'Source changed.');
foreach ($r['images'] as $im) x113_check(hash_file('sha256',$dir.'/'.basename($im['file']))===$im['sha256'] && (wp_getimagesize($dir.'/'.$im['file'])['mime']??'')==='image/webp','Image changed.');
$supplier=(int)get_option('pc_supplier_code_001');
x113_check($supplier && get_post_type($supplier)==='pc_supplier' && get_post_status($supplier)==='private' && get_post_meta($supplier,'_pc_supplier_identity',true)==='mingjun-catalog','Supplier identity mismatch.');
$cats=[];
foreach ($r['categories'] as $slug) { $term=get_term_by('slug',$slug,'product_cat'); x113_check($term,'Missing category.'); $cats[]=(int)$term->term_id; }
sort($cats);
global $wpdb;
$lock='sscn_x113_import_20261024';
x113_check((int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,0)',$lock))===1,'Import busy.');
try {
    $key='sscn_x113_import_20261024'; $s=get_option($key,[]);
    x113_check(!$s || $s['digest']===$digest,'Manifest changed after import.');
    $id=wc_get_product_id_by_sku($r['sku']);
    x113_check(!$id || ($id===($s['product']??0) && wc_get_product($id)->get_meta('_sscn_x113_import')===$r['operation']),'SKU already used.');
    $reservation=(int)get_option('pc_model_reserved_'.$r['sku']);
    x113_check(!$reservation || $reservation===($s['product']??0),'Model already reserved.');
    foreach (range(1,4) as $i) { $vid=wc_get_product_id_by_sku(sprintf('%s-V%03d',$r['sku'],$i)); x113_check(!$vid || in_array($vid,$s['variants']??[],true),'Variation SKU collision.'); }
    if ($mode==='plan') { WP_CLI::line(wp_json_encode(['model'=>$r['sku'],'status'=>'draft','colors'=>$r['colors'],'size'=>$r['size'],'images'=>count($r['images']),'claims'=>count($r['claims']),'price'=>null,'stock'=>null,'existing_product'=>$id],JSON_UNESCAPED_UNICODE)); return; }
    if ($mode==='apply' && empty($s['complete'])) {
        if ($s) { x113_check(count($s['images'])===4 && count($s['variants'])===4 && x113_existing()===$s['preserved'] && x113_settings()===$s['settings'],'Incomplete product or changed catalog; manual recovery required.'); }
        if (!$s) {
        x113_check(wp_image_editor_supports(['mime_type'=>'image/webp']),'WebP image editor required.');
        $s=['digest'=>$digest,'settings'=>x113_settings(),'preserved'=>x113_existing(),'variants'=>[],'images'=>[],'claims'=>[]]; update_option($key,$s,false);
        $p=new WC_Product_Variable();
        $p->set_name($r['name']); $p->set_slug(strtolower($r['sku'])); $p->set_status('draft');
        $p->set_description($r['description']); $p->set_short_description($r['short']); $p->set_category_ids($cats);
        $p->set_manage_stock(false); $p->set_stock_quantity(null); $p->set_stock_status('outofstock'); $p->set_backorders('no');
        $p->update_meta_data('_sscn_x113_import',$r['operation']); $p->update_meta_data('_pc_selected_supplier_model','X113'); $p->update_meta_data('_pc_supplier_id',$supplier); $p->update_meta_data('_sscn_catalog_preview','yes');
        $p->update_meta_data('_pc_sample_source',['archive'=>'x113-20261024/source.json','sha256'=>$r['source_sha256']]);
        $p->update_meta_data('_pc_sample_review_notes','成分比例不完整；厚薄与毛圈分布待样品确认；具体缝头工艺待核实；价格和库存待填写。');
        $attrs=[];
        foreach (['Color'=>$r['colors'],'Size'=>[$r['size']]] as $name=>$values) { $a=new WC_Product_Attribute(); $a->set_name($name); $a->set_options($values); $a->set_visible(true); $a->set_variation(true); $attrs[]=$a; }
        $p->set_attributes($attrs); $p->save(); $s['product']=$p->get_id(); update_option($key,$s,false);
        Product_Identity::assign($p,'S',$r['sku']); $p->save();
        foreach ($r['colors'] as $i=>$color) {
            $v=new WC_Product_Variation(); $v->set_parent_id($p->get_id()); $v->set_status('publish'); $v->set_sku(sprintf('%s-V%03d',$r['sku'],$i+1)); $v->set_attributes(['color'=>$color,'size'=>$r['size']]);
            $v->set_manage_stock(false); $v->set_stock_quantity(null); $v->set_stock_status('outofstock'); $v->set_backorders('no'); $v->set_regular_price(''); $v->set_sale_price(''); $v->set_price('');
            $v->update_meta_data('_sscn_x113_import',$r['operation']); $v->update_meta_data('_pc_selected_supplier_model','X113'); $v->save(); $s['variants'][]=$v->get_id(); update_option($key,$s,false);
        }
        require_once ABSPATH.'wp-admin/includes/file.php'; require_once ABSPATH.'wp-admin/includes/image.php'; require_once ABSPATH.'wp-admin/includes/media.php';
        add_filter('intermediate_image_sizes_advanced',static function($sizes){ foreach(['woocommerce_thumbnail','woocommerce_gallery_thumbnail'] as $k) if(isset($sizes[$k])) $sizes[$k]['crop']=false; return $sizes; });
        foreach ($r['images'] as $im) {
            $tmp=wp_tempnam($im['file']); x113_check(copy($dir.'/'.$im['file'],$tmp),'Cannot stage image.');
            $aid=media_handle_sideload(['name'=>$im['file'],'tmp_name'=>$tmp],$p->get_id(),$im['alt']);
            x113_check(!is_wp_error($aid),'Media import failed.');
            $s['images'][]=$aid; update_option($key,$s,false);
            update_post_meta($aid,'_wp_attachment_image_alt',$im['alt']); wp_update_post(['ID'=>$aid,'post_excerpt'=>$im['caption']]);
        }
        $p->set_image_id($s['images'][0]); $p->set_gallery_image_ids(array_slice($s['images'],1)); $p->save();
        }
        $p=wc_get_product($s['product']);
        x113_check($p->get_status()==='draft' && $p->get_name()===$r['name'] && $p->get_description()===$r['description'] && $p->get_gallery_image_ids()===array_slice($s['images'],1),'Draft changed before claim import.');
        foreach ($r['claims'] as $i=>$c) {
            $measurement=in_array($c['metric_key'],['pair_weight_g','catalog_height_mm','foot_length_mm'],true);
            $conditions=$measurement ? ['basis'=>'supplier_catalog','size_coverage'=>'unconfirmed'] : ['applicability'=>'all_variations'];
            if ($c['metric_key']==='pair_weight_g') $conditions+=['quantity'=>'one_pair','packaging'=>'excluded','approximate'=>true];
            if ($c['metric_key']==='wool_type') $conditions+=['area'=>'whole','material_component'=>'混纺织物中的羊毛纤维'];
            $existing=isset($s['claims'][$i]) ? Repository::find($s['claims'][$i]) : null;
            if ($existing && $existing['status']==='published') continue;
            $cid=Repository::save(['wc_product_id'=>(string)$p->get_id(),'wc_variation_id'=>'0','metric_key'=>$c['metric_key'],'value'=>$c['value'],'unit'=>$c['unit'],'source_type'=>'supplier_declared','measured_at'=>'2026-10-24','size_scope'=>$measurement?'产品手册记录，具体尺码覆盖待确认':'该型号已列颜色和尺码','conditions_json'=>wp_json_encode($conditions),'note'=>$c['note']],['visibility'=>'private','public_title'=>'供应商产品资料','source_org'=>'供应商','note'=>'Stored source SHA-256: '.$r['source_sha256'].'. Archived source: x113-20261024/source.json. Receipt date 2026-10-24, not a laboratory test date.'], $existing ? (int)$existing['id'] : 0, $existing ? (int)$existing['revision'] : 0);
            $s['claims'][$i]=$cid; update_option($key,$s,false);
            foreach (['reviewed','published'] as $target) { $claim=Repository::find($cid); Repository::transition($cid,(int)$claim['revision'],$target); }
        }
        WC_Product_Variable::sync($p->get_id()); wc_delete_product_transients($p->get_id());
        $s['complete']=true; update_option($key,$s,false);
    }
    x113_check(!empty($s['complete']),'Run apply first.');
    $p=wc_get_product($s['product']);
    x113_check($p && $p->get_status()==='draft' && $p->get_sku()===$r['sku'] && $p->get_slug()===strtolower($r['sku']) && $p->get_meta('_pc_selected_supplier_model')==='X113','Product identity/status mismatch.');
    x113_check($p->get_name()===$r['name'] && $p->get_description()===$r['description'] && $p->get_short_description()===$r['short'],'Product text differs.');
    $actualCats=$p->get_category_ids(); sort($actualCats); x113_check($actualCats===$cats,'Categories differ.');
    x113_check(count($p->get_children())===4 && $p->get_price()==='' && $p->get_stock_quantity()===null,'Parent commerce state differs.');
    foreach ($s['variants'] as $i=>$vid) { $v=wc_get_product($vid); x113_check($v->get_parent_id()===$p->get_id() && $v->get_attributes()===['color'=>$r['colors'][$i],'size'=>$r['size']] && $v->get_regular_price()==='' && $v->get_price()==='' && $v->get_stock_quantity()===null && !$v->get_manage_stock() && $v->get_stock_status()==='outofstock','Variant mismatch.'); }
    x113_check((int)$p->get_image_id()===(int)$s['images'][0] && $p->get_gallery_image_ids()===array_slice($s['images'],1),'Gallery mismatch.');
    foreach ($s['images'] as $i=>$aid) x113_check(hash_file('sha256',get_attached_file($aid))===$r['images'][$i]['sha256'] && wp_get_attachment_image_srcset($aid,'full'),'Image or responsive sizes missing.');
    $claims=Repository::listing($p->get_id(),1); x113_check(count($claims)===count($r['claims']),'Unexpected claim count.');
    foreach ($s['claims'] as $i=>$cid) { $c=Repository::find($cid); $expected=$r['claims'][$i]; x113_check($c['status']==='published' && $c['source_type']==='supplier_declared' && $c['evidence']['visibility']==='private' && $c['metric_key']===$expected['metric_key'] && (string)($c['value_enum']?: (float)$c['value_num'])===(string)$expected['value'] && $c['unit']===$expected['unit'],'Claim mismatch.'); }
    $uid=get_current_user_id(); wp_set_current_user(0); x113_check(!$p->is_visible() && !$p->is_purchasable() && !is_post_publicly_viewable($p->get_id()),'Draft exposed.'); foreach ($s['variants'] as $vid) x113_check(!wc_get_product($vid)->is_purchasable(),'Variant purchasable.'); wp_set_current_user($uid);
    x113_check(x113_settings()===$s['settings'] && x113_existing()===$s['preserved'],'Existing catalog/settings changed.');
    WP_CLI::line(wp_json_encode(['mode'=>$mode,'id'=>$p->get_id(),'sku'=>$p->get_sku(),'status'=>'draft','variations'=>4,'images'=>4,'claims'=>count($s['claims']),'preserved'=>true,'edit_url'=>admin_url('post.php?post='.$p->get_id().'&action=edit')],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
} finally { $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock)); }
