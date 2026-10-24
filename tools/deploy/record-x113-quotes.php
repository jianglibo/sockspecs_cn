<?php
/** Store user-supplied procurement quotes privately; never change selling prices. */
use ParameterCommerce\Suppliers;
function quote_check($ok,$message){if(!$ok){throw new RuntimeException($message);}}
function quote_products_fingerprint(){
    $rows=[];
    foreach(get_posts(['post_type'=>['product','product_variation'],'post_status'=>'any','numberposts'=>-1,'orderby'=>'ID','order'=>'ASC','fields'=>'ids']) as $id){$p=wc_get_product($id);if($p){$d=$p->get_data();$d['meta_data']=array_map(static fn($m)=>$m->get_data(),$d['meta_data']);$rows[$id]=$d;}}
    return hash('sha256',wp_json_encode($rows));
}
quote_check(defined('WP_CLI') && WP_CLI && current_user_can('manage_options'),'Administrator CLI required.');
$file=__DIR__.'/x113-quotes-20261024.json';$data=json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR);
quote_check($data['site']==='domestic' && DB_NAME==='sockspecs_cn' && in_array(home_url(),['http://127.0.0.1:8089','https://sockspecs.cn'],true),'Wrong domestic site.');
$product=wc_get_product(wc_get_product_id_by_sku('SS-S0012'));
quote_check($product && $product->get_meta('_pc_selected_supplier_model')==='X113','X113 identity mismatch.');
$sid=(int)get_option('pc_supplier_code_001');
quote_check($sid && get_post_type($sid)==='pc_supplier' && get_post_status($sid)==='private' && get_post_meta($sid,'_pc_supplier_identity',true)==='mingjun-catalog','Supplier identity mismatch.');
$mode=$args[0]??'plan';quote_check(in_array($mode,['plan','apply','verify'],true),'Use plan/apply/verify.');
$key='pc_x113_quotes_20261024';$hash=hash_file('sha256',$file);$state=get_option($key);
quote_check(!$state || $state['hash']===$hash,'Quote manifest changed; use a reviewed amendment.');
if($mode==='plan'){WP_CLI::line(wp_json_encode(['supplier_id'=>$sid,'models'=>array_column($data['quotes'],'model'),'currency'=>'CNY','scope'=>'private procurement only'],JSON_PRETTY_PRINT));return;}
global $wpdb;quote_check((int)$wpdb->get_var("SELECT GET_LOCK('pc_x113_quotes_20261024',0)")===1,'Quote update locked.');
try {
    if($mode==='apply' && !$state){
        $fields=[];foreach(Suppliers::fields() as $name=>$label){$fields[$name]=get_post_meta($sid,'_pc_'.$name,true);}
        $new=$fields;$new['remarks']=trim($fields['remarks']."\n\n".$data['display_note']);
        quote_check(mb_strlen($new['remarks'])<=4000,'Private remarks too long; preserve existing notes.');
        $state=['hash'=>$hash,'supplier_id'=>$sid,'before'=>$fields,'after'=>$new,'prior_quote_meta'=>get_post_meta($sid,'_pc_x113_procurement_quotes_20261024',true),'products'=>quote_products_fingerprint(),'complete'=>false];
        add_option($key,$state,'',false);
    }
    quote_check((bool)$state,'Apply first.');
    if($mode==='apply' && !$state['complete']){
        foreach($state['before'] as $k=>$v){$now=get_post_meta($sid,'_pc_'.$k,true);quote_check($now===$v || $now===$state['after'][$k],'Supplier notes changed; review first.');}
        Suppliers::save($sid,$state['after']);
        update_post_meta($sid,'_pc_x113_procurement_quotes_20261024',$data['quotes']);
        quote_check(quote_products_fingerprint()===$state['products'],'Product data changed during quote update.');
        $state['complete']=true;update_option($key,$state,false);
    }
    foreach($state['after'] as $k=>$v){quote_check(get_post_meta($sid,'_pc_'.$k,true)===$v,'Supplier field mismatch.');}
    quote_check(get_post_meta($sid,'_pc_x113_procurement_quotes_20261024',true)===$data['quotes'],'Private quote values mismatch.');
    quote_check(get_post_status($sid)==='private' && !get_post_type_object('pc_supplier')->show_in_rest && !is_post_publicly_viewable($sid),'Supplier must remain private.');
    quote_check(quote_products_fingerprint()===$state['products'],'Product data changed since quote import.');
    WP_CLI::line(wp_json_encode(['status'=>'verified','quotes'=>count($data['quotes']),'supplier_id'=>$sid,'edit_url'=>admin_url('post.php?post='.$sid.'&action=edit'),'retail_prices'=>'unchanged','inventory'=>'unchanged','publication'=>'unchanged'],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
}finally{$wpdb->get_var("SELECT RELEASE_LOCK('pc_x113_quotes_20261024')");}
