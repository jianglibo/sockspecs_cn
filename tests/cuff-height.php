<?php
/** Disposable domestic integration fixture: optional third variation axis. */
use ParameterCommerce\Size_Shape as S;
use ParameterCommerce\Pack_Builder as B;
use ParameterCommerce\Repository as R;
if(!defined('WP_CLI') || !WP_CLI || wp_get_environment_type()!=='local' || DB_NAME!=='sockspecs_cn')throw new RuntimeException('Local tests only');
$mode=$args[0]??'test';$key='sscn_cuff_height_test_fixture';$ids=[];$staff=get_current_user_id();$old_get=$_GET;
function cuff_check($ok,$msg){if(!$ok)throw new RuntimeException($msg);}
function cuff_cleanup($ids){global $wpdb;foreach($ids as $id){$ev=$wpdb->get_col($wpdb->prepare("SELECT evidence_id FROM {$wpdb->prefix}pc_claims WHERE wc_product_id=%d",$id));$wpdb->delete($wpdb->prefix.'pc_claims',['wc_product_id'=>$id]);foreach($ev as $e)$wpdb->delete($wpdb->prefix.'pc_evidence',['id'=>$e]);}foreach(array_reverse($ids) as $id)wp_delete_post($id,true);}
if($mode==='cleanup'){cuff_cleanup(get_option($key,[]));delete_option($key);WP_CLI::success('Disposable fixture removed');return;}
if(get_option($key))throw new RuntimeException('Clean previous fixture first');
try{
 $p=new WC_Product_Variable();$p->set_name('测试专用：袜筒款式选择（非在售商品）');$p->set_status('publish');$p->set_tax_status('none');$attrs=[];
 foreach(['color'=>['黑色','白色'],'size'=>['M','L'],'pa_cuff-height'=>['low','mid','high']] as $name=>$values){$a=new WC_Product_Attribute();$a->set_name($name);if($name==='pa_cuff-height'){$a->set_id(wc_attribute_taxonomy_id_by_name('cuff-height'));$values=array_map(fn($slug)=>(int)get_term_by('slug',$slug,$name)->term_id,$values);}$a->set_options($values);$a->set_variation(true);$a->set_visible(true);$attrs[]=$a;}
 $p->set_attributes($attrs);$p->update_meta_data('_pc_pack_builder','yes');$p->update_meta_data('_pc_pack_prices',['currency'=>'CNY',4=>'40.00',6=>'60.00',12=>'120.00']);$p->save();$ids[]=$p->get_id();$by=[];$claim_ids=[];
 foreach(['low'=>80,'mid'=>130,'high'=>190] as $height=>$mm)foreach(['M','L'] as $size)foreach(['黑色','白色'] as $color){
  $v=new WC_Product_Variation();$v->set_parent_id($p->get_id());$v->set_attributes(['color'=>$color,'size'=>$size,'pa_cuff-height'=>$height]);$v->set_status('publish');$v->set_regular_price('10');$v->set_manage_stock(true);$v->set_stock_quantity(60);$v->set_tax_status('none');$v->save();$ids[]=$v->get_id();$by[$height][$size][]=$v->get_id();
  foreach(['catalog_height_mm'=>[$mm,'mm'],'foot_length_mm'=>[$size==='M'?180:200,'mm'],'pair_weight_g'=>[$mm/10+30,'g']] as $metric=>[$value,$unit]){
   $id=R::save(['wc_product_id'=>(string)$p->get_id(),'wc_variation_id'=>(string)$v->get_id(),'metric_key'=>$metric,'value'=>(string)$value,'unit'=>$unit,'source_type'=>'supplier_declared','size_scope'=>'TEST ONLY '.$height.' '.$size.' '.$color,'measured_at'=>'2026-10-21','note'=>'TEST ONLY synthetic fixture, not actual product measurements','conditions_json'=>'{"packaging":"excluded","quantity":"one pair (operator confirmed)"}'],['visibility'=>'public','public_title'=>'测试数据，非商品参数','source_org'=>'TEST ONLY','source_url'=>'https://example.com/test']);R::transition($id,1,'reviewed');R::transition($id,2,'published');$claim_ids[$v->get_id()][$metric]=$id;
  }
 }
 WC_Product_Variable::sync($p->get_id());$p=wc_get_product($p->get_id());wp_set_current_user(0);
 cuff_check(S::has_cuff_height($p),'Attribute detection');$groups=B::groups($p->get_id());cuff_check(count($groups)===6,'Six height/size groups, not merged by size');
 $keys=[];foreach($groups as $group=>$data){$v=reset($data['items']);$height=$v->get_attribute('pa_cuff-height');$slug=$v->get_attributes()['pa_cuff-height'];$size=$v->get_attribute('size');$keys[$slug][$size]=$group;$_GET=['pc_pack_group'=>$group];$d=S::dimensions($p);cuff_check($d['leg_cm']===(string)(['low'=>8,'mid'=>13,'high'=>19][$slug]),'Correct selected height');cuff_check($d['foot_cm']===($size==='M'?'18':'20'),'Correct selected foot');cuff_check(str_contains($d['size'],$height),'Human-readable style label');$html=S::render($p);cuff_check(str_contains($html,$height) && str_contains($html,'pc-size-shape'),'Selected shape rendered');$weight=S::weight_summary($p);cuff_check(str_contains($weight,(string)(['low'=>38,'mid'=>43,'high'=>49][$slug]).' g'),'Selected net weight');cuff_check(count($data['items'])===2,'Colors grouped within height/size');cuff_check(str_contains(ParameterCommerce\Public_Specifications::scope_label($v),$height),'Specification selector includes cuff style');B::selection($p->get_id(),'4',[$by[$slug][$size][0]=>2,$by[$slug][$size][1]=>2]);}
 foreach([[$by['low']['M'][0],$by['high']['M'][0]],[$by['low']['M'][0],$by['low']['L'][0]]] as [$a,$b]){cuff_check(count(B::selection($p->get_id(),'4',[$a=>2,$b=>2]))===2,'Allow equal-price cross-height or cross-size pack');}
 $_GET=['pc_pack_group'=>['bad']];$default=S::dimensions($p);$_GET=[];cuff_check(S::dimensions($p)===$default,'Malformed selector safely matches default pack');
 // One missing measurement cannot borrow another height or silently cover an unknown color.
 wp_set_current_user($staff);$id=$claim_ids[$by['high']['M'][0]]['catalog_height_mm'];$c=R::find($id);R::transition($id,$c['revision'],'retracted');wp_set_current_user(0);$_GET=['pc_pack_group'=>$keys['high']['M']];cuff_check(S::dimensions($p)===[],'Missing measurement hides selected diagram');$_GET=['pc_pack_group'=>$keys['low']['M']];cuff_check(S::dimensions($p)['leg_cm']==='8','Other height unaffected');
 // Existing products still have no cuff selector; original structured diagram survives.
 $existing=wc_get_product(wc_get_product_id_by_sku('SS-001-X109'));cuff_check(!S::has_cuff_height($existing) && S::dimensions($existing)!==[],'Existing X109 unchanged');
 // Restore the test measurement for visual verification only.
 wp_set_current_user($staff);$c=R::find($id);$new=R::fork($id,$c['revision']);$c=R::find($new);R::transition($new,$c['revision'],'reviewed');$c=R::find($new);R::transition($new,$c['revision'],'published');
 WP_CLI::success('Six height/size groups, color mixing, equal-price cross-group mixing, shape/weight selection, missing-data isolation, malformed request and existing-product regression passed.');
 if($mode==='preview'){add_option($key,$ids,'',false);WP_CLI::log(get_permalink($p->get_id()).'&pc_pack_group='.$keys['high']['M']);$ids=[];}
}finally{wp_set_current_user($staff);$_GET=$old_get;cuff_cleanup($ids);}
