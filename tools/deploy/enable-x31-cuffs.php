<?php
/** Domestic X31: confirmed six colors in three cuff styles, new stock unknown. */
use ParameterCommerce\Repository as R;
use ParameterCommerce\Pack_Builder as B;
use ParameterCommerce\Size_Shape as S;
function x31c_check($ok,$why){if(!$ok)throw new RuntimeException($why);}
x31c_check(defined('WP_CLI') && WP_CLI && current_user_can('manage_options') && DB_NAME==='sockspecs_cn' && get_woocommerce_currency()==='CNY' && in_array(home_url(),['http://127.0.0.1:8089','https://sockspecs.cn'],true),'Domestic administrator CLI only');
$mode=$args[0]??'verify';x31c_check(in_array($mode,['apply','verify'],true),'Use apply or verify');
$p=wc_get_product(wc_get_product_id_by_sku('SS-001-X31'));x31c_check($p && $p->is_type('variable'),'Missing X31');
$source='https://cbu01.alicdn.com/img/ibank/O1CN01yoMwO026fcA02I0qr_!!3447167689-0-cib.jpg';
$key='sscn_x31_cuffs_20261021';$state=get_option($key);
function x31c_original($v){$d=$v->get_data();foreach(['name','attributes','attribute_summary','date_modified'] as $k)unset($d[$k]);$d['meta_data']=array_map(fn($m)=>$m->get_data(),$d['meta_data']);return hash('sha256',wp_json_encode($d));}
function x31c_others($pid){global $wpdb;return hash('sha256',wp_json_encode($wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}pc_claims WHERE wc_product_id=%d AND metric_key NOT IN ('catalog_height_mm','foot_length_mm') ORDER BY id",$pid),ARRAY_A)));}
if(!$state){
 x31c_check($mode==='apply' && count($p->get_children())===6,'Expected six original colors');
 $state=['original'=>[],'new'=>[],'claims'=>[],'old_dimensions'=>[],'name'=>$p->get_name(),'description'=>$p->get_description(),'short'=>$p->get_short_description(),'prices'=>$p->get_meta('_pc_pack_prices'),'images'=>[$p->get_image_id(),$p->get_gallery_image_ids()],'other_claims'=>x31c_others($p->get_id()),'visibility'=>get_option('woocommerce_coming_soon'),'product_status'=>$p->get_status()];
 foreach($p->get_children() as $id){$v=wc_get_product($id);x31c_check(count($v->get_attributes())===1 && isset($v->get_attributes()['color']),'Unexpected original attributes');$state['original'][$id]=['sku'=>$v->get_sku(),'color'=>$v->get_attribute('color'),'hash'=>x31c_original($v)];}
 global $wpdb;foreach($wpdb->get_results($wpdb->prepare("SELECT id FROM {$wpdb->prefix}pc_claims WHERE wc_product_id=%d AND metric_key IN ('catalog_height_mm','foot_length_mm') AND status='published'",$p->get_id())) as $row){$c=R::find((int)$row->id);x31c_check(!(int)$c['wc_variation_id'],'Existing variation dimensions require review');$state['old_dimensions'][]=$c;}
 add_option($key,$state,'',false);
}
$styles=['high'=>['label'=>'高筒款','mm'=>190],'mid'=>['label'=>'中筒款','mm'=>130],'low'=>['label'=>'低筒款','mm'=>80]];
if($mode==='apply'){
 $taxid=wc_attribute_taxonomy_id_by_name('cuff-height');x31c_check($taxid>0,'Run optional attribute setup first');
 $attrs=$p->get_attributes();$a=new WC_Product_Attribute();$a->set_id($taxid);$a->set_name('pa_cuff-height');$a->set_options(array_map(fn($s)=>(int)get_term_by('slug',$s,'pa_cuff-height')->term_id,array_keys($styles)));$a->set_position(1);$a->set_visible(true);$a->set_variation(true);$attrs['pa_cuff-height']=$a;
 $a=new WC_Product_Attribute();$a->set_name('size');$a->set_options(['EU 39-44']);$a->set_position(2);$a->set_visible(true);$a->set_variation(true);$attrs['size']=$a;
 $p->set_attributes($attrs);$p->set_name('经典日常棉质袜 — SS-001-X31');$p->set_short_description('经典日常款棉混纺袜，提供高筒、中筒和低筒款，适用鞋码 EU 39–44。高筒样品整体厚度较均匀，内侧较平整。');$p->set_description('<p>一款适合通勤、居家和日常休闲穿着的经典棉混纺袜。可按鞋型和穿搭选择高筒、中筒或低筒款。</p><p>已收到的高筒样品各部位编织结构有所不同，但整体厚度较均匀；翻看内侧，织面较平整，没有明显毛圈或脚掌、后跟局部加厚区。</p><p>厂家尺寸图标注：高筒约 19 厘米、中筒约 13 厘米、低筒约 8 厘米，平铺袜底均约 18 厘米；图示公差为 ±0.5 厘米，适用鞋码 EU 39–44。中、低筒的穿着感受尚未进行样品确认。</p>');$p->update_meta_data('_pc_pack_show_unavailable','yes');$p->save();
 foreach($state['original'] as $id=>$original){$v=wc_get_product($id);x31c_check(x31c_original($v)===$original['hash'],'Original stock/price or variant changed');$v->set_attributes(['color'=>$original['color'],'pa_cuff-height'=>'high','size'=>'EU 39-44']);$v->save();
  foreach(['mid','low'] as $style){$sku=$original['sku'].'-'.strtoupper($style);$existing=wc_get_product_id_by_sku($sku);if(!$existing){$n=new WC_Product_Variation();$n->set_parent_id($p->get_id());$n->set_sku($sku);$n->set_status('publish');$n->set_attributes(['color'=>$original['color'],'pa_cuff-height'=>$style,'size'=>'EU 39-44']);$n->set_regular_price($v->get_regular_price());$n->set_tax_status($v->get_tax_status());$n->set_tax_class($v->get_tax_class());$n->set_manage_stock(true);$n->set_stock_quantity('');$n->set_stock_status('outofstock');$n->set_backorders('no');$n->set_menu_order($style==='mid'?10:20);$n->save();$existing=$n->get_id();}else{x31c_check(isset($state['new'][$sku]) && $state['new'][$sku]===$existing,'Unmanaged SKU collision');}$state['new'][$sku]=$existing;update_option($key,$state,false);}
 }
 WC_Product_Variable::sync($p->get_id());$p=wc_get_product($p->get_id());
 foreach($p->get_children() as $id){$v=wc_get_product($id);$style=$v->get_attributes()['pa_cuff-height'];$scope='X31 '.$styles[$style]['label'].'；EU 39–44；'.$v->get_attribute('color').'；厂家平铺尺寸图';
  foreach(['catalog_height_mm'=>$styles[$style]['mm'],'foot_length_mm'=>180] as $metric=>$value){$ck=$id.':'.$metric;
   if(!isset($state['claims'][$ck])){global $wpdb;$found=$wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}pc_claims WHERE wc_product_id=%d AND wc_variation_id=%d AND metric_key=%s",$p->get_id(),$id,$metric));x31c_check(!$found,'Unmanaged dimension exists');$cid=R::save(['wc_product_id'=>(string)$p->get_id(),'wc_variation_id'=>(string)$id,'metric_key'=>$metric,'value'=>(string)$value,'unit'=>'mm','source_type'=>'supplier_declared','size_scope'=>$scope,'measured_at'=>'2026-10-21','conditions_json'=>wp_json_encode(['basis'=>'supplier_dimension_diagram','date_basis'=>'source supplied by operator','cuff_style'=>$style,'tolerance_mm'=>5]),'note'=>'厂家尺寸图标注，公差 ±0.5 厘米；袜底为平铺长度，适用鞋码以 EU 39–44 为准。用户已确认该图属于 X31，且三种筒高均提供六色。'],['visibility'=>'public','public_title'=>'X31 高、中、低筒尺寸图','source_org'=>'供应商商品详情页','source_url'=>$source]);$state['claims'][$ck]=$cid;update_option($key,$state,false);}
   $c=R::find($state['claims'][$ck]);x31c_check((int)$c['wc_variation_id']===$id && (float)$c['value_num']===$value*1.0,'Dimension changed');if($c['status']==='draft')R::transition($c['id'],$c['revision'],'reviewed');$c=R::find($c['id']);if($c['status']==='reviewed')R::transition($c['id'],$c['revision'],'published');
  }
 }
 foreach($state['old_dimensions'] as $old){$c=R::find($old['id']);if($c['status']==='published')R::transition($c['id'],$c['revision'],'retracted');}
 wc_delete_product_transients($p->get_id());
}
$p=wc_get_product($p->get_id());x31c_check(count($p->get_children())===18 && count($state['new'])===12,'Expected eighteen variants');
foreach($state['original'] as $id=>$original)x31c_check(x31c_original(wc_get_product($id))===$original['hash'],'Original inventory/commerce changed');
foreach($state['new'] as $id){$v=wc_get_product($id);x31c_check($v->get_stock_quantity()===null && !$v->is_in_stock() && !$v->backorders_allowed(),'New inventory must remain unknown and unpurchasable');}
x31c_check($p->get_meta('_pc_pack_prices')===$state['prices'] && [$p->get_image_id(),$p->get_gallery_image_ids()]===$state['images'] && x31c_others($p->get_id())===$state['other_claims'] && get_option('woocommerce_coming_soon')===$state['visibility'] && $p->get_status()===$state['product_status'],'Preservation failure');
$staff=get_current_user_id();$get=$_GET;wp_set_current_user(0);
try{$all=B::groups($p->get_id(),false,true);x31c_check(count($all)===3 && count(B::groups($p->get_id()))===1,'Display versus purchase groups');foreach($all as $g=>$group){$v=reset($group['items']);$style=$v->get_attributes()['pa_cuff-height'];$_GET=['pc_pack_group'=>$g];$d=S::dimensions($p);x31c_check(count($group['items'])===6 && $d['leg_cm']===(string)($styles[$style]['mm']/10) && $d['foot_cm']==='18','Guest diagram mismatch');if($style!=='high'){$failed=false;try{B::selection($p->get_id(),'4',[$v->get_id()=>4]);}catch(RuntimeException $e){$failed=true;}x31c_check($failed,'Unconfirmed stock accepted');}WP_CLI::log($styles[$style]['label'].' '.get_permalink($p->get_id()).'&pc_pack_group='.$g);}}finally{wp_set_current_user($staff);$_GET=$get;}
WP_CLI::success('X31: 18 variants, three visible cuff groups; high stock preserved, twelve new stocks blank and unavailable; 19/13/8 × 18 cm diagrams verified.');
