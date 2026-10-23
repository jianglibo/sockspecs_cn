<?php
/** Operator-approved publication and one-time stock initialization, domestic store only. */
use ParameterCommerce\Pack_Builder;
function publish_check($ok,$message){if(!$ok){throw new RuntimeException($message);}}
publish_check(defined('WP_CLI') && WP_CLI && current_user_can('manage_options') && DB_NAME==='sockspecs_cn' && in_array(home_url(),['http://127.0.0.1:8089','https://sockspecs.cn'],true) && get_woocommerce_currency()==='CNY','Domestic administrator only.');
$models=['X109'=>13,'X112'=>12,'364'=>11,'365'=>12,'X70'=>4,'323'=>5,'J02-J03'=>9,'X31'=>6,'X65'=>4,'X72'=>5,'X59'=>4];
$parents=[];$variants=[];$unpriced=[];
foreach($models as $model=>$count){
    $p=wc_get_product(wc_get_product_id_by_sku('SS-001-'.$model));
    publish_check($p && $p->is_type('variable') && in_array($p->get_status(),['publish','draft'],true),'Unexpected product: '.$model);
    publish_check($p->get_meta('_sscn_source_product_id') || $p->get_meta('_pc_sample_drafts_operation')==='sample-drafts-20261019-v1','Unknown product provenance.');
    publish_check(count($p->get_children())===$count,'Variation count changed: '.$model);
    $parents[$p->get_id()]=$model;
    foreach($p->get_children() as $id){$v=wc_get_product($id);publish_check($v && $v->get_parent_id()===$p->get_id() && $v->get_status()==='publish','Unexpected variation.');$variants[$id]=$p->get_id();}
    if($p->get_price()===''){$unpriced[]=$model;}
}
function publish_snapshot(){
    $out=[];foreach(get_posts(['post_type'=>['product','product_variation'],'post_status'=>'any','numberposts'=>-1,'orderby'=>'ID','order'=>'ASC','fields'=>'ids']) as $id){$p=wc_get_product($id);if(!$p){continue;}$d=$p->get_data();$d['meta_data']=array_map(fn($m)=>$m->get_data(),$d['meta_data']);$out[$id]=$d;}return $out;
}
function publish_preserved($snapshot,$parents,$variants){
    foreach($snapshot as $id=>&$d){
        if(!isset($parents[$id]) && !isset($variants[$id])){continue;}
        foreach(['date_modified','date_modified_gmt','stock_status','manage_stock','stock_quantity','backorders'] as $field){unset($d[$field]);}
        if(isset($parents[$id])){
            foreach(['status','catalog_visibility','date_created','date_created_gmt'] as $field){unset($d[$field]);}
            $d['meta_data']=array_values(array_filter($d['meta_data'],fn($m)=>$m['key']!=='_sscn_catalog_preview'));
        }
    }unset($d);return hash('sha256',wp_json_encode($snapshot));
}
function publish_settings(){global $wpdb;return hash('sha256',wp_json_encode([$wpdb->get_results("SELECT option_name,option_value FROM {$wpdb->options} WHERE option_name LIKE 'woocommerce_%' AND option_name NOT LIKE '%transient%' ORDER BY option_name",ARRAY_A),get_option('blog_public'),home_url()]));}
$mode=$args[0]??'plan';publish_check(in_array($mode,['plan','apply','verify'],true),'Use plan/apply/verify.');
$plan=['products'=>count($parents),'variants'=>count($variants),'stock_per_variant'=>60,'unit'=>'pair','total_pair_inventory'=>count($variants)*60,'unpriced_models'=>$unpriced,'shipping_payments_dns'=>'unchanged'];
if($mode==='plan'){WP_CLI::line(wp_json_encode($plan,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));return;}
$key='sscn_publish_stock_20261020';$state=get_option($key);
global $wpdb;publish_check((int)$wpdb->get_var("SELECT GET_LOCK('sscn_publish_stock_20261020',0)")===1,'Publication locked.');
try{
    if($mode==='apply' && !$state){
        // Never reuse this initialization to replenish sold stock or overwrite an existing balance.
        foreach($variants as $id=>$pid){$v=wc_get_product($id);publish_check($v->get_stock_quantity()===null && !$v->get_manage_stock(),'Existing inventory: review before overwrite.');}
        $before=publish_snapshot();$state=['before'=>$before,'preserved'=>publish_preserved($before,$parents,$variants),'settings'=>publish_settings(),'complete'=>false,'started_at'=>gmdate('c')];add_option($key,$state,'',false);
        foreach($variants as $id=>$pid){$v=wc_get_product($id);$v->set_manage_stock(true);$v->set_stock_quantity(60);$v->set_stock_status('instock');$v->set_backorders('no');$v->save();}
        foreach($parents as $id=>$model){$p=wc_get_product($id);$p->set_status('publish');$p->set_catalog_visibility('visible');$p->set_manage_stock(false);$p->set_stock_quantity(null);$p->set_backorders('no');$p->update_meta_data('_sscn_catalog_preview',$p->get_price()===''?'yes':'no');$p->save();WC_Product_Variable::sync($id);wc_delete_product_transients($id);}
        $state['complete']=true;update_option($key,$state,false);
    }
    publish_check($state && $state['complete'],'Incomplete publication: inspect recorded before-state; never reset stocks blindly.');
    foreach($variants as $id=>$pid){$v=wc_get_product($id);publish_check($v->get_manage_stock() && $v->get_stock_managed_by_id()===$id && $v->get_stock_quantity()===60 && $v->get_stock_status()==='instock' && !$v->backorders_allowed(),'Stock differs (possibly a new order); do not reset: '.$id);}
    $staff=get_current_user_id();wp_set_current_user(0);
    try{
        foreach($parents as $id=>$model){$p=wc_get_product($id);publish_check($p->get_status()==='publish' && $p->get_catalog_visibility()==='visible' && $p->get_meta('_sscn_catalog_preview')===($p->get_price()===''?'yes':'no'),'Publication mismatch.');
            if(Pack_Builder::enabled($id)){publish_check($p->is_purchasable() && count(Pack_Builder::groups($id))>0,'Priced pack unavailable: '.$model);}else{publish_check($p->get_price()==='' && !$p->is_purchasable(),'Unexpected price/purchase for unpriced product.');}
        }
    }finally{wp_set_current_user($staff);}
    publish_check(publish_preserved(publish_snapshot(),$parents,$variants)===$state['preserved'],'Non-publication/inventory product data changed.');
    publish_check(publish_settings()===$state['settings'],'Store/settings changed.');
    WP_CLI::line(wp_json_encode(['status'=>'verified']+$plan,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
}finally{$wpdb->get_var("SELECT RELEASE_LOCK('sscn_publish_stock_20261020')");}
