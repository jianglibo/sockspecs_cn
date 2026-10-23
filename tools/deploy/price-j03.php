<?php
/** Confirm J03 shares J02 pricing; preserve model, color, claims and draft inventory. */
use ParameterCommerce\Suppliers;
$requested_mode=$args[0]??'plan';
// Reuse the domestic identity guards and read-only snapshot functions.
$args=['plan'];ob_start();require __DIR__.'/price-domestic-samples.php';ob_end_clean();
price_check(in_array($requested_mode,['plan','apply','verify'],true),'Use plan/apply/verify.');
$parent=wc_get_product(wc_get_product_id_by_sku('SS-001-J02-J03'));
$changes=[];$models=[];
foreach($parent->get_children() as $id){$v=wc_get_product($id);$model=strtoupper($v->get_attribute('model'));$models[$model][]=$v->get_attribute('color');if($model==='J03'){$changes[$id]='11.90';}else{price_check($model==='J02' && $v->get_regular_price()==='11.90','Unexpected model or J02 price.');}}
price_check(count($changes)===5 && count($models['J02'])===4,'Expected four J02 and five J03 colors.');
price_check($parent->get_meta('_pc_pack_prices')===['currency'=>'CNY',4=>'47.60',6=>'71.40',12=>'142.80'],'Parent pack totals changed.');
$affected=$changes+[$parent->get_id()=>null];
$confirmation=['date'=>'2026-10-20','source'=>'operator confirmation','models'=>['J02','J03'],'same_supplier_price'=>true,'budget_unit_price'=>'8.42','currency'=>'CNY','unit'=>'pair','retail_pair_allocation'=>'11.90','options'=>$models,'note'=>'J02速干款、J03抗起球款为商家款式名称；同价不表示材料和功能相同或经过测试。采购成交档位仍未确认。'];
$sid=(int)get_option('pc_supplier_code_001');price_check($sid && get_post_status($sid)==='private' && get_post_meta($sid,'_pc_supplier_identity',true)==='mingjun-catalog','Private supplier mismatch.');
$key='sscn_j03_prices_20261020';$meta='_pc_j02_j03_price_confirmation_20261020';$state=get_option($key);
if($requested_mode==='plan'){WP_CLI::line(wp_json_encode(['J03_colors'=>$models['J03'],'retail_pair'=>'11.90','packs'=>$parent->get_meta('_pc_pack_prices'),'status'=>'draft'],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));return;}
global $wpdb;price_check((int)$wpdb->get_var("SELECT GET_LOCK('sscn_sample_prices_20261020',0)")===1,'Pricing locked.');
try{
    if($requested_mode==='apply' && !$state){
        foreach($changes as $id=>$value){$v=wc_get_product($id);price_check($v->get_price()==='' && $v->get_regular_price()==='' && $v->get_sale_price()==='','J03 already priced; review first.');}
        $before=price_snapshot();$fields=[];foreach(Suppliers::fields() as $field=>$label){$fields[$field]=get_post_meta($sid,'_pc_'.$field,true);}
        $after=$fields;$after['remarks']=trim($fields['remarks']."\n\n2026-10-20 最新确认：用户确认J02与J03采购价格相同，两者均按¥8.42/双预算。J02为速干款（黑、蓝、珊瑚、灰），J03为抗起球款（黑、深蓝、白、浅蓝、灰）；名称为商家声明，不新增已验证功能。国内零售价两款均为4双¥47.60、6双¥71.40、12双¥142.80；仍为草稿。此前不套用J03的记录已被本确认取代。");
        price_check(mb_strlen($after['remarks'])<=4000,'Supplier notes too long.');
        $state=['before'=>$before,'preserved'=>price_preserved($before,$affected,[]),'settings'=>price_settings(),'supplier_before'=>$fields,'supplier_after'=>$after,'prior_confirmation'=>get_post_meta($sid,$meta,true),'complete'=>false];add_option($key,$state,'',false);
        foreach($changes as $id=>$value){$v=wc_get_product($id);$v->set_regular_price($value);$v->set_price($value);$v->save();}
        WC_Product_Variable::sync($parent->get_id());wc_delete_product_transients($parent->get_id());
        Suppliers::save($sid,$after);update_post_meta($sid,$meta,$confirmation);
        $state['complete']=true;update_option($key,$state,false);
    }
    price_check($state && $state['complete'],'Incomplete amendment: inspect saved before-state before retry.');
    foreach($changes as $id=>$value){$v=wc_get_product($id);price_check($v->get_price()===$value && $v->get_regular_price()===$value && !$v->is_purchasable(),'J03 price or purchase guard mismatch.');}
    price_check(price_preserved(price_snapshot(),$affected,[])===$state['preserved'],'Unrelated product/model/color/stock data changed.');
    price_check(price_settings()===$state['settings'],'Store settings changed.');
    foreach($state['supplier_after'] as $field=>$value){price_check(get_post_meta($sid,'_pc_'.$field,true)===$value,'Supplier field mismatch.');}
    price_check(get_post_meta($sid,$meta,true)===$confirmation,'Confirmation mismatch.');
    WP_CLI::success('J03 five colors match J02: CNY 47.60 / 71.40 / 142.80. Drafts, stock, model/color identity and shipping preserved.');
}finally{$wpdb->get_var("SELECT RELEASE_LOCK('sscn_sample_prices_20261020')");}
