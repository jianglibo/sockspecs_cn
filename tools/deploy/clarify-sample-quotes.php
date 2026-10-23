<?php
/** Record operator budget decision and shared-listing clarification; preserve raw quotes. */
use ParameterCommerce\Suppliers;
function quote_check($ok,$message){if(!$ok){throw new RuntimeException($message);}}
function quote_products_fingerprint(){
    $rows=[];
    foreach(get_posts(['post_type'=>['product','product_variation'],'post_status'=>'any','numberposts'=>-1,'orderby'=>'ID','order'=>'ASC','fields'=>'ids']) as $id){$p=wc_get_product($id);if($p){$rows[$id]=$p->get_data();}}
    return hash('sha256',wp_json_encode($rows));
}
quote_check(defined('WP_CLI') && WP_CLI && current_user_can('manage_options'),'Administrator CLI required.');
$file=__DIR__.'/sample-quotes-20261020.json';$data=json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR);
$cn=$data['site']==='domestic';
$data['quotes']=[
 ['model'=>'J02','budget_unit_price'=>'8.42','currency'=>'CNY','unit'=>'pair','basis'=>'Operator selected higher quote for budgeting; actual tier conditions remain unknown','applies_to_J03'=>false],
 ['model'=>'X58','listing_models'=>['X58','X59','X60','X61'],'basis'=>'Operator reports shared listing; exact model-price mapping remains unconfirmed','apply_quote_to_X59'=>false]
];
$data['display_note']=$cn ? '2026-10-20 补充：J02按用户决定，以¥8.42/双作为采购预算价，原始两档报价保留，具体成交条件未知，不套用J03。X58–X61共用详情页，但不能据此认定仅颜色不同或指标相同，原X58报价尚未确认适用于X59。' : '2026-10-20 clarification: Operator chose CNY 8.42/pair as the J02 procurement budget. Both raw quotes remain archived; transaction conditions unknown, not applied to J03. X58–X61 share a listing, but this does not establish identical specifications or color-only differences. X58 quote applicability to X59 remains unconfirmed.';

quote_check(in_array(home_url(),$cn?['http://127.0.0.1:8089','https://sockspecs.cn']:['http://127.0.0.1:8088','https://sockspecs.com'],true),'Wrong site.');
quote_check(!$cn || DB_NAME==='sockspecs_cn','Wrong domestic database.');
$sid=(int)get_option('pc_supplier_code_001');
quote_check($sid && get_post_type($sid)==='pc_supplier' && get_post_status($sid)==='private' && get_post_meta($sid,'_pc_supplier_identity',true)==='mingjun-catalog','Supplier identity mismatch.');
$mode=$args[0]??'plan';quote_check(in_array($mode,['plan','apply','verify'],true),'Use plan/apply/verify.');
$key='pc_sample_quote_decisions_20261020';$hash=hash_file('sha256',$file);$state=get_option($key);
quote_check(!$state || $state['hash']===$hash,'Quote manifest changed; use a reviewed amendment.');
if($mode==='plan'){WP_CLI::line(wp_json_encode(['supplier_id'=>$sid,'models'=>array_column($data['quotes'],'model'),'currency'=>'CNY','scope'=>'private procurement only'],JSON_PRETTY_PRINT));return;}
global $wpdb;quote_check((int)$wpdb->get_var("SELECT GET_LOCK('pc_sample_quote_decisions_20261020',0)")===1,'Quote update locked.');
try {
    if($mode==='apply' && !$state){
        $fields=[];foreach(Suppliers::fields() as $name=>$label){$fields[$name]=get_post_meta($sid,'_pc_'.$name,true);}
        $new=$fields;$new['remarks']=trim($fields['remarks']."\n\n".$data['display_note']);
        quote_check(mb_strlen($new['remarks'])<=4000,'Private remarks too long; preserve existing notes.');
        $state=['hash'=>$hash,'supplier_id'=>$sid,'before'=>$fields,'after'=>$new,'prior_quote_meta'=>get_post_meta($sid,'_pc_procurement_decisions_20261020',true),'products'=>quote_products_fingerprint(),'complete'=>false];
        add_option($key,$state,'',false);
    }
    quote_check((bool)$state,'Apply first.');
    if($mode==='apply' && !$state['complete']){
        foreach($state['before'] as $k=>$v){$now=get_post_meta($sid,'_pc_'.$k,true);quote_check($now===$v || $now===$state['after'][$k],'Supplier notes changed; review first.');}
        Suppliers::save($sid,$state['after']);
        update_post_meta($sid,'_pc_procurement_decisions_20261020',$data['quotes']);
        quote_check(quote_products_fingerprint()===$state['products'],'Product data changed during quote update.');
        $state['complete']=true;update_option($key,$state,false);
    }
    foreach($state['after'] as $k=>$v){quote_check(get_post_meta($sid,'_pc_'.$k,true)===$v,'Supplier field mismatch.');}
    quote_check(get_post_meta($sid,'_pc_procurement_decisions_20261020',true)===$data['quotes'],'Private quote values mismatch.');
    quote_check(get_post_status($sid)==='private' && !get_post_type_object('pc_supplier')->show_in_rest && !is_post_publicly_viewable($sid),'Supplier must remain private.');
    quote_check(quote_products_fingerprint()===$state['products'],'Product data changed since quote import.');
    WP_CLI::line(wp_json_encode(['status'=>'verified','quotes'=>count($data['quotes']),'supplier_id'=>$sid,'edit_url'=>admin_url('post.php?post='.$sid.'&action=edit'),'retail_prices'=>'unchanged','inventory'=>'unchanged','publication'=>'unchanged'],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
}finally{$wpdb->get_var("SELECT RELEASE_LOCK('pc_sample_quote_decisions_20261020')");}
