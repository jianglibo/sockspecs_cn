<?php
/** Scoped category repair; private before-state supports review/rollback. */
require_once __DIR__.'/sample-categories.php';
function pc_cat_check($ok,$message) { if(!$ok) { throw new RuntimeException($message); } }
pc_cat_check(defined('WP_CLI') && WP_CLI && current_user_can('manage_options'),'Administrator CLI required.');
$input=json_decode(file_get_contents(__DIR__.'/sample-drafts-20261019/products.json'),true,512,JSON_THROW_ON_ERROR);
$cn=$input['site']==='domestic';
pc_cat_check(in_array(home_url(),$cn?['http://127.0.0.1:8089','https://sockspecs.cn']:['http://127.0.0.1:8088','https://sockspecs.com'],true),'Wrong site.');
pc_cat_check(!$cn || DB_NAME==='sockspecs_cn','Wrong domestic DB.');
$mode=$args[0]??'plan';pc_cat_check(in_array($mode,['plan','apply','verify'],true),'Use plan/apply/verify.');
$key='pc_sample_categories_20261019';$state=get_option($key);$rows=[];
foreach($input['products'] as $r){
    $p=wc_get_product(wc_get_product_id_by_sku($r['sku']));
    pc_cat_check($p && $p->get_status()==='draft' && $p->get_meta('_pc_sample_drafts_operation')===$input['operation'],'Expected imported draft: '.$r['sku']);
    $rows[$r['model']]=$p;
}
if($mode==='plan'){foreach($rows as $m=>$p){WP_CLI::line($p->get_sku().' => '.implode(', ',pc_sample_category_slugs($m)));}return;}
global $wpdb;
pc_cat_check((int)$wpdb->get_var("SELECT GET_LOCK('pc_sample_categories_20261019',0)")===1,'Repair is locked.');
try {
    if($mode==='apply' && !$state){
        $state=['before'=>[],'after'=>[],'preserved'=>[],'complete'=>false];
        foreach($rows as $m=>$p){$state['before'][$m]=$p->get_category_ids();$d=$p->get_data();unset($d['category_ids'],$d['date_modified']);$state['preserved'][$m]=hash('sha256',wp_json_encode($d));}
        add_option($key,$state,'',false);
    }
    pc_cat_check((bool)$state,'Apply first.');
    if($mode==='apply' && !$state['complete']){
        foreach($rows as $m=>$p){
            $want=pc_sample_category_ids($m,$cn,true);$have=$p->get_category_ids();sort($have);
            $before=$state['before'][$m];sort($before);
            pc_cat_check($have===$before || $have===$want,'Categories were edited; review before replacing.');
            $p->set_category_ids($want);$p->save();wc_delete_product_transients($p->get_id());$state['after'][$m]=$want;update_option($key,$state,false);
        }
        $state['complete']=true;update_option($key,$state,false);
    }
    $result=[];
    foreach($rows as $m=>$old){
        $p=wc_get_product($old->get_id());$have=$p->get_category_ids();sort($have);
        pc_cat_check($have===pc_sample_category_ids($m,$cn),'Category verification failed.');
        $d=$p->get_data();unset($d['category_ids'],$d['date_modified']);
        pc_cat_check(hash('sha256',wp_json_encode($d))===$state['preserved'][$m],'Other product data changed.');
        $result[]=['sku'=>$p->get_sku(),'status'=>$p->get_status(),'categories'=>wp_get_object_terms($p->get_id(),'product_cat',['fields'=>'names'])];
    }
    WP_CLI::line(wp_json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
}finally{$wpdb->get_var("SELECT RELEASE_LOCK('pc_sample_categories_20261019')");}
