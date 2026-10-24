<?php
if (!defined('WP_CLI') || !WP_CLI || wp_get_environment_type()!=='local')throw new RuntimeException('Local tests only.');
use ParameterCommerce\Product_Identity as I;
function ident_ok($ok,$msg){if(!$ok)throw new RuntimeException($msg);echo "PASS $msg\n";}
function ident_throws($fn,$msg){try{$fn();}catch(WC_Data_Exception $e){ident_ok(true,$msg);return;}throw new RuntimeException($msg);}
$admin=get_users(['role'=>'administrator','fields'=>'ID','number'=>1]);wp_set_current_user($admin[0]);
global $wpdb;$wpdb->query('START TRANSACTION');
try{
 $p=new WC_Product_Simple();$p->set_name('FICTIONAL identity test');$p->set_status('draft');$p->set_regular_price('7.00');$p->set_manage_stock(true);$p->set_stock_quantity(9);$p->save();
 $a=I::assign($p,'S');ident_ok((bool)preg_match('/^SS-S[0-9]{4}$/',$a),'four-digit socks code');
 $fresh=wc_get_product($p->get_id());ident_ok(I::assign($fresh,'S')===$a,'retry before save retains pending reservation');$p->save();
 ident_ok(I::assign($p,'S')===$a,'saved allocation is idempotent');
 ident_throws(fn()=>I::assign($p,'T'),'cannot change category after assignment');
 $p->set_sku('EDITED');ident_throws(fn()=>$p->save(),'cannot edit public model SKU');$p->set_sku($a);
 $q=new WC_Product_Simple();$q->set_name('FICTIONAL identity test 2');$q->set_status('draft');$q->save();
 ident_throws(fn()=>I::assign($q,'S',$a),'collision rejected');
 $pid=$p->get_id();$p->delete(true);ident_throws(fn()=>I::assign($q,'S',$a),'deleted product code never reused');
 $q->set_sku($a);ident_throws(fn()=>$q->save(),'manual SKU cannot reuse a reserved deleted number');$q->set_sku('');
 $b=I::assign($q,'S');$q->save();ident_ok($b!==$a,'next product gets a new number');
 $t=new WC_Product_Simple();$t->set_name('FICTIONAL towel');$t->save();$c=I::assign($t,'T');$t->save();ident_ok(str_starts_with($c,'SS-T'),'independent towels namespace');
 ident_throws(fn()=>I::original(['bad']),'non-scalar original rejected');ident_ok(I::original(' J02 <b>test</b> ')==='J02 test','original sanitized');
 $_POST=['pc_identity_nonce'=>wp_create_nonce('pc_product_identity'),'pc_original_model'=>'X99'];I::save($q);$q->save();ident_ok($q->get_meta('_pc_selected_supplier_model')==='X99','private original editor saves');
 wp_set_current_user(0);$_POST['pc_original_model']='unauthorized';I::save($q);ident_ok($q->get_meta('_pc_selected_supplier_model')==='X99','unauthorized save ignored');ident_throws(fn()=>I::assign($q,'S'),'unauthorized allocation rejected');wp_set_current_user($admin[0]);
 ob_start();I::box(get_post($q->get_id()));$html=ob_get_clean();ident_ok(str_contains($html,'厂家原始型号') && str_contains($html,'value="X99"') && str_contains($html,'readonly'),'Chinese backend shows private original and read-only public model');
 $vp=new WC_Product_Variable();$vp->set_name('FICTIONAL variation editor');$vp->set_status('draft');$vp->save();$vv=new WC_Product_Variation();$vv->set_parent_id($vp->get_id());$vv->save();
 $_POST=['pc_variation_identity_nonce'=>[0=>wp_create_nonce('pc_variation_identity')],'pc_original_variation'=>[0=>'J03']];I::save_variation($vv->get_id(),0);ident_ok(wc_get_product($vv->get_id())->get_meta('_pc_selected_supplier_model')==='J03','variation private original editor saves');
 $keys=I::duplicate_exclusions([]);ident_ok(in_array('_pc_public_model',$keys,true) && in_array('_pc_legacy_sku',$keys,true),'duplicate excludes original identity');
 $v=new WC_Product_Variation();$v->set_parent_id($q->get_id());$v->set_attributes(['model'=>'A','color'=>'black']);$v->update_meta_data('_pc_legacy_model_attribute','J02');$v->update_meta_data('_pc_public_style','A');
 ident_ok(I::pack_group(['model'=>'A'],$v)===md5(wp_json_encode(['model'=>'J02'])),'old pack group links retained');
 ident_ok(I::cart_style(['data'=>$v,'variation'=>['attribute_model'=>'J02']])['variation']['attribute_model']==='A','old cart style spelling upgraded');
 ident_ok(I::cart_style(['data'=>$v,'variation'=>['attribute_model'=>'B']])['variation']['attribute_model']==='B','unrelated cart style preserved');
 update_option('pc_model_sequence_T',9999,false);$u=new WC_Product_Simple();$u->set_name('FICTIONAL limit');$u->save();ident_throws(fn()=>I::assign($u,'T'),'exhaustion does not recycle numbers');
}finally{$wpdb->query('ROLLBACK');wp_cache_flush();$_POST=[];}
