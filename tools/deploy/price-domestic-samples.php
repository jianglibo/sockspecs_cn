<?php
/** Approved domestic draft prices only. Run with administrator WP-CLI plan/apply/verify. */
use ParameterCommerce\Pack_Pricing;
use ParameterCommerce\Domestic_Catalog;
function price_check($ok,$message){if(!$ok){throw new RuntimeException($message);}}
price_check(defined('WP_CLI') && WP_CLI && current_user_can('manage_options'),'Administrator CLI required.');
price_check(DB_NAME==='sockspecs_cn' && in_array(home_url(),['http://127.0.0.1:8089','https://sockspecs.cn'],true) && get_woocommerce_currency()==='CNY','Domestic CNY site only.');
$prices=['SS-001-323'=>'6.90','SS-001-X31'=>'9.90','SS-001-X59'=>'10.90','SS-001-X72'=>'10.90','SS-001-J02-J03'=>'11.90','SS-001-X65'=>'14.90'];
$targets=[];$parents=[];$rows=[];
$j03_priced=!empty(get_option('sscn_j03_prices_20261020')['complete']);
foreach($prices as $sku=>$unit){
    $p=wc_get_product(wc_get_product_id_by_sku($sku));
    price_check($p && $p->is_type('variable') && $p->get_status()==='draft' && $p->get_meta('_pc_sample_drafts_operation')==='sample-drafts-20261019-v1' && Domestic_Catalog::preview($p),'Expected sample draft: '.$sku);
    $config=['currency'=>'CNY'];foreach([4,6,12] as $n){$config[$n]=number_format((float)$unit*$n,2,'.','');}
    $parents[$p->get_id()]=$config;$targets[$p->get_id()]=null;
    foreach($p->get_children() as $vid){
        $v=wc_get_product($vid);
        // Later operator-confirmed J03 equality is applied by a separate audited amendment.
        $targets[$vid]=(!$j03_priced && $sku==='SS-001-J02-J03' && strtolower($v->get_attribute('model'))==='j03') ? '' : $unit;
    }
    $rows[$sku]=$config;
}
function price_snapshot(){
    $out=[];foreach(get_posts(['post_type'=>['product','product_variation'],'post_status'=>'any','numberposts'=>-1,'orderby'=>'ID','order'=>'ASC','fields'=>'ids']) as $id){
        $p=wc_get_product($id);if(!$p){continue;}$d=$p->get_data();$d['meta_data']=array_map(fn($m)=>$m->get_data(),$d['meta_data']);$out[$id]=$d;
    }return $out;
}
function price_preserved($snapshot,$targets,$parents){
    foreach($snapshot as $id=>&$d){if(!array_key_exists($id,$targets)){continue;}
        foreach(['date_modified','date_modified_gmt','price','regular_price','sale_price'] as $k){unset($d[$k]);}
        $d['meta_data']=array_values(array_filter($d['meta_data'],fn($m)=>!isset($parents[$id]) || !in_array($m['key'],['_pc_pack_builder','_pc_pack_prices'],true)));
    }unset($d);return hash('sha256',wp_json_encode($snapshot));
}
function price_settings(){global $wpdb;return hash('sha256',wp_json_encode([
    $wpdb->get_results("SELECT option_name,option_value FROM {$wpdb->options} WHERE option_name LIKE 'woocommerce_%' AND option_name NOT LIKE '%transient%' ORDER BY option_name",ARRAY_A),
    WC_Shipping_Zones::get_zones(),(new WC_Shipping_Zone(0))->get_shipping_methods(),get_option('blog_public'),
]));}
$mode=$args[0]??'plan';price_check(in_array($mode,['plan','apply','verify'],true),'Use plan/apply/verify.');
if($mode==='plan'){WP_CLI::line(wp_json_encode(['packs'=>$rows,'priced_variations'=>count(array_filter($targets,fn($v)=>is_string($v)&&$v!=='')),'J03'=>$j03_priced?'priced':'unpriced','shipping'=>'unchanged'],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));return;}
$key='sscn_sample_prices_20261020';$state=get_option($key);
global $wpdb;price_check((int)$wpdb->get_var("SELECT GET_LOCK('sscn_sample_prices_20261020',0)")===1,'Pricing locked.');
try{
    if($mode==='apply' && !$state){
        foreach($targets as $id=>$unit){$p=wc_get_product($id);price_check($p->get_price()==='' && $p->get_regular_price()==='' && $p->get_sale_price()==='','Existing price; review before overwrite.');}
        foreach($parents as $id=>$config){price_check(!wc_get_product($id)->get_meta('_pc_pack_prices'),'Existing pack prices; review first.');}
        $before=price_snapshot();$state=['before'=>$before,'preserved'=>price_preserved($before,$targets,$parents),'settings'=>price_settings(),'complete'=>false];
        add_option($key,$state,'',false);
        foreach($targets as $id=>$unit){if($unit===null || $unit===''){continue;}$v=wc_get_product($id);$v->set_regular_price($unit);$v->set_price($unit);$v->save();}
        foreach($parents as $id=>$config){$p=wc_get_product($id);$p->update_meta_data('_pc_pack_builder','yes');$p->update_meta_data('_pc_pack_prices',$config);$p->save();WC_Product_Variable::sync($id);wc_delete_product_transients($id);}
        $state['complete']=true;update_option($key,$state,false);
    }
    price_check($state && $state['complete'],'Incomplete operation; review saved before-state before retry.');
    foreach($parents as $id=>$config){$p=wc_get_product($id);price_check(Pack_Pricing::prices($p)===array_diff_key($config,['currency'=>true]),'Pack total mismatch.');price_check($p->get_meta('_pc_pack_builder')==='yes' && $p->get_status()==='draft' && !$p->is_purchasable(),'Draft guard changed.');}
    foreach($targets as $id=>$unit){if($unit===null){continue;}$v=wc_get_product($id);price_check($v->get_price()===$unit && $v->get_regular_price()===$unit && !$v->is_purchasable(),'Variation price or purchase guard mismatch: '.$id);}
    price_check(price_preserved(price_snapshot(),$targets,$parents)===$state['preserved'],'Non-price product data changed.');
    price_check(price_settings()===$state['settings'],'Shipping/store settings changed.');
    WP_CLI::line(wp_json_encode(['status'=>'verified','packs'=>$rows,'drafts'=>count($parents),'shipping'=>'unchanged','stock'=>'unchanged','J03'=>$j03_priced?'priced':'unpriced'],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
}finally{$wpdb->get_var("SELECT RELEASE_LOCK('sscn_sample_prices_20261020')");}
