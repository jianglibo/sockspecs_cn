<?php
/** Fill original five domestic pack prices from operator-supplied cost estimates. */
use ParameterCommerce\Pack_Pricing;
use ParameterCommerce\Pack_Builder;
use ParameterCommerce\Suppliers;
$requested=$args[0]??'plan';
$args=['plan'];ob_start();require __DIR__.'/publish-domestic-catalog.php';ob_end_clean();
publish_check(in_array($requested,['plan','apply','verify'],true),'Use plan/apply/verify.');
$units=['X70'=>'10.90','X109'=>'14.90','X112'=>'15.90','364'=>'20.90','365'=>'22.90'];
$costs=['X70'=>'7.00','X109'=>'10.80','X112'=>'11.62','364'=>'15.52','365'=>'16.74'];
$parents=[];$targets=[];$rows=[];
foreach($units as $model=>$unit){
 $p=wc_get_product(wc_get_product_id_by_sku('SS-001-'.$model));publish_check($p->get_status()==='publish' && $p->get_meta('_sscn_source_product_id'),'Unexpected original product.');
 $config=['currency'=>'CNY'];foreach([4,6,12] as $size){$config[$size]=number_format((float)$unit*$size,2,'.','');}
 $parents[$p->get_id()]=$config;$targets[$p->get_id()]=null;$rows[$model]=$config;
 foreach($p->get_children() as $id){$targets[$id]=$unit;}
}
function original_price_preserved($snapshot,$targets,$parents){
 foreach($snapshot as $id=>&$d){if(!array_key_exists($id,$targets)){continue;}
  foreach(['date_modified','date_modified_gmt','price','regular_price','sale_price'] as $field){unset($d[$field]);}
  if(isset($parents[$id])){$d['meta_data']=array_values(array_filter($d['meta_data'],fn($m)=>!in_array($m['key'],['_pc_pack_prices','_pc_pack_builder','_sscn_catalog_preview'],true)));}
 }unset($d);return hash('sha256',wp_json_encode($snapshot));
}
$source=['date'=>'2026-10-20','source'=>'earlier operator-pasted 1688 quotations in this conversation','budget_costs_cny_per_pair'=>$costs,'retail_cny_per_pair'=>$units,'cost_basis'=>['X70'=>'5-499 pairs quoted 7.00','X109'=>'Upper bound of quoted 9.83-10.80 range; no quantity tier stated','X112'=>'5-299 pairs quoted 11.62','364'=>'5-299 pairs quoted 15.52','365'=>'5-299 pairs quoted 16.74'],'tax_and_supplier_shipping'=>'unknown','terms'=>'Planning costs from earlier quotes; current transaction eligibility and mixed-order aggregation unconfirmed'];
$sid=(int)get_option('pc_supplier_code_001');publish_check($sid && get_post_status($sid)==='private' && get_post_meta($sid,'_pc_supplier_identity',true)==='mingjun-catalog','Supplier mismatch.');
$key='sscn_original_prices_20261020';$state=get_option($key);$meta='_pc_original_domestic_costs_20261020';
if($requested==='plan'){WP_CLI::line(wp_json_encode(['packs'=>$rows,'variants'=>count($targets)-count($parents),'stock'=>'preserved'],JSON_PRETTY_PRINT));return;}
global $wpdb;publish_check((int)$wpdb->get_var("SELECT GET_LOCK('sscn_original_prices_20261020',0)")===1,'Pricing locked.');
try{
 if($requested==='apply' && !$state){
  foreach($targets as $id=>$unit){$p=wc_get_product($id);publish_check($p->get_price()==='' && $p->get_regular_price()==='' && $p->get_sale_price()==='','Existing price; review first.');}
  foreach($parents as $id=>$config){publish_check(!wc_get_product($id)->get_meta('_pc_pack_prices'),'Existing pack price; review first.');}
  $fields=[];foreach(Suppliers::fields() as $field=>$label){$fields[$field]=get_post_meta($sid,'_pc_'.$field,true);}
  $after=$fields;$after['remarks']=trim($fields['remarks']."\n\n2026-10-20 原五款国内定价：采购预算/双为X70 ¥7.00（5-499双）、X109 ¥10.80（原报价9.83-10.80的上限）、X112 ¥11.62（5-299双）、364 ¥15.52（5-299双）、365 ¥16.74（5-299双），依据用户此前提供的1688报价，含税、运费及当前成交条件未确认。国内单双折算售价分别为¥10.90、¥14.90、¥15.90、¥20.90、¥22.90，按4/6/12双装销售。采购预算不作公开商品价格或材料成本声明。");
  publish_check(mb_strlen($after['remarks'])<=4000,'Supplier remarks too long.');
  $before=publish_snapshot();$state=['before'=>$before,'preserved'=>original_price_preserved($before,$targets,$parents),'settings'=>publish_settings(),'supplier_before'=>$fields,'supplier_after'=>$after,'prior_source'=>get_post_meta($sid,$meta,true),'complete'=>false];add_option($key,$state,'',false);
  foreach($targets as $id=>$unit){if($unit===null){continue;}$v=wc_get_product($id);$v->set_regular_price($unit);$v->set_price($unit);$v->save();}
  foreach($parents as $id=>$config){$p=wc_get_product($id);$p->update_meta_data('_pc_pack_prices',$config);$p->update_meta_data('_pc_pack_builder','yes');$p->update_meta_data('_sscn_catalog_preview','no');$p->save();WC_Product_Variable::sync($id);wc_delete_product_transients($id);}
  Suppliers::save($sid,$after);update_post_meta($sid,$meta,$source);
  $state['complete']=true;update_option($key,$state,false);
 }
 publish_check($state && $state['complete'],'Incomplete operation; review saved before-state, do not overwrite.');
 foreach($targets as $id=>$unit){if($unit!==null){$v=wc_get_product($id);publish_check($v->get_price()===$unit && $v->get_regular_price()===$unit,'Wrong pair allocation.');}}
 $staff=get_current_user_id();wp_set_current_user(0);
 try{foreach($parents as $id=>$config){$p=wc_get_product($id);publish_check(Pack_Pricing::prices($p)===array_diff_key($config,['currency'=>true]),'Pack total mismatch.');publish_check($p->is_purchasable() && count(Pack_Builder::groups($id))>0,'Pack unavailable.');}}finally{wp_set_current_user($staff);}
 publish_check(original_price_preserved(publish_snapshot(),$targets,$parents)===$state['preserved'],'Stock or unrelated product fields changed.');
 publish_check(publish_settings()===$state['settings'],'Store settings changed.');
 foreach($state['supplier_after'] as $field=>$value){publish_check(get_post_meta($sid,'_pc_'.$field,true)===$value,'Supplier field mismatch.');}
 publish_check(get_post_meta($sid,$meta,true)===$source,'Private cost record mismatch.');
 WP_CLI::line(wp_json_encode(['status'=>'verified','packs'=>$rows,'variants'=>52,'stock'=>'preserved','shipping_payments'=>'unchanged'],JSON_PRETTY_PRINT));
}finally{$wpdb->get_var("SELECT RELEASE_LOCK('sscn_original_prices_20261020')");}
