<?php
/** Local disposable native-cart integration for mixed style, color, size and cuff packs. */
use ParameterCommerce\Pack_Builder as B;
if(!defined('WP_CLI')||!WP_CLI||wp_get_environment_type()!=='local'||DB_NAME!=='sockspecs_cn')throw new RuntimeException('Local only');
function mixed_check($ok,$label){if(!$ok)throw new RuntimeException($label);}
function mixed_reject($fn,$label){try{$fn();}catch(RuntimeException $e){return;}throw new RuntimeException('Expected rejection: '.$label);}
$ids=[];
try{
 wc_load_cart();WC()->cart->empty_cart();
 $p=new WC_Product_Variable();$p->set_name('Disposable mixed pack fixture');$p->set_status('publish');$p->set_tax_status('none');$attrs=[];
 foreach(['color'=>['red','blue'],'size'=>['M','L'],'cuff-height'=>['low','high'],'model'=>['A','B']] as $key=>$values){$a=new WC_Product_Attribute();$a->set_name($key);$a->set_options($values);$a->set_variation(true);$attrs[]=$a;}
 $p->set_attributes($attrs);$p->update_meta_data('_pc_pack_builder','yes');$p->update_meta_data('_pc_pack_show_unavailable','yes');$p->update_meta_data('_pc_pack_prices',['currency'=>'CNY',4=>'39.99',6=>'59.99',12=>'119.99']);$p->save();$ids[]=$p->get_id();$vs=[];
 foreach([['red','M','low','A','10',60],['blue','L','high','A','10.00',60],['red','L','high','A','10',60],['blue','M','low','A','11',60],['red','M','high','B','10',60],['blue','L','low','A','10',null]] as [$color,$size,$height,$model,$price,$stock]){
  $v=new WC_Product_Variation();$v->set_parent_id($p->get_id());$v->set_status('publish');$v->set_attributes(['color'=>$color,'size'=>$size,'cuff-height'=>$height,'model'=>$model]);$v->set_tax_status('none');$v->set_regular_price($price);$v->set_manage_stock(true);$v->set_stock_quantity($stock===null?'':$stock);$v->set_stock_status($stock===null?'outofstock':'instock');$v->save();$ids[]=$v->get_id();$vs[]=$v->get_id();
 }
 WC_Product_Variable::sync($p->get_id());
 foreach([4=>'39.99',6=>'59.99',12=>'119.99'] as $n=>$price){
  WC()->cart->empty_cart();$selection=[$vs[0]=>1,$vs[1]=>1,$vs[4]=>$n-2];B::add_pack($p->get_id(),$n,$selection);
  mixed_check(count(WC()->cart->get_cart())===3,'Three independently stocked variant lines');mixed_check(B::cart_error(WC()->cart)==='','Mixed cart accepted');
  for($i=0;$i<2;$i++){WC()->cart->calculate_totals();mixed_check(number_format(WC()->cart->get_cart_contents_total(),2,'.','')===$price,'Exact rounded pack total '.$n);}
  foreach(WC()->cart->get_cart() as $key=>$line){mixed_check($line['quantity']===$selection[$line['variation_id']],'Native line quantity');mixed_check($line['variation']===wc_get_product($line['variation_id'])->get_variation_attributes(),'Native attributes preserved');$item=new WC_Order_Item_Product();B::order_item($item,$key,$line);mixed_check($item->get_meta('_pc_pack')['items']===$selection,'Order metadata preserves all selections');}
 }
 // Model is a normal variation axis; price, tax, parent and stock remain boundaries.
 foreach([3=>'different price',5=>'unknown stock'] as $i=>$reason)mixed_reject(fn()=>B::selection($p->get_id(),4,[$vs[0]=>2,$vs[$i]=>2]),$reason);
 $base=wc_get_product($vs[0]);$style=wc_get_product($vs[4]);
 mixed_check(B::mix_key($base)===B::mix_key($style),'Different styles are compatible');
 $foreign=clone $style;$foreign->set_parent_id($p->get_id()+999999);mixed_check(B::mix_key($base)!==B::mix_key($foreign),'Different parent isolated');
 $taxStatus=clone $style;$taxStatus->set_tax_status('taxable');mixed_check(B::mix_key($base)!==B::mix_key($taxStatus),'Tax status participates in compatibility');
 // WooCommerce inherits tax status from the parent; tax class is a persisted variation setting.
 $style->set_tax_class('reduced-rate');$style->save();
 mixed_reject(fn()=>B::selection($p->get_id(),4,[$vs[0]=>2,$vs[4]=>2]),'Different tax class');
 mixed_check(B::cart_error(WC()->cart)!=='','Tax change invalidates existing mixed cart');
 $style->set_tax_class('');$style->save();
 mixed_reject(fn()=>B::selection($p->get_id(),4,[$vs[0]=>2,99999999=>2]),'Foreign variant');
 mixed_reject(fn()=>B::selection($p->get_id(),4,[$vs[0]=>3]),'Wrong total');
 $v=wc_get_product($vs[1]);$v->set_regular_price('12');$v->save();mixed_check(B::cart_error(WC()->cart)!=='','Price change invalidates existing mixed cart');$v->set_regular_price('10');$v->save();
 WC()->cart->empty_cart();$v->set_stock_quantity(1);$v->save();mixed_reject(fn()=>B::add_pack($p->get_id(),4,[$vs[0]=>2,$vs[1]=>2]),'Insufficient stock');mixed_check(WC()->cart->is_empty(),'No partial pack after failure');$v->set_stock_quantity(60);$v->save();
 B::add_pack($p->get_id(),4,[$vs[0]=>2,$vs[1]=>2]);$key=array_key_first(WC()->cart->get_cart());WC()->cart->remove_cart_item($key);mixed_check(WC()->cart->is_empty(),'Remove one line removes whole mixed pack');WC()->cart->restore_cart_item($key);mixed_check(count(WC()->cart->get_cart())===2&&B::cart_error(WC()->cart)==='','Restore whole mixed pack');
 // All native size/cuff groups render together; read-only dimension query cannot exclude purchasable rows.
 $product=wc_get_product($p->get_id());$groups=B::groups($p->get_id(),false,true);$selected=array_key_last($groups);$preview=false;ob_start();include dirname(__DIR__).'/parameter-commerce/templates/pack-builder.php';$html=ob_get_clean();foreach($vs as $id)mixed_check(str_contains($html,'name="pc_colors['.$id.']"'),'All options rendered');mixed_check(!str_contains($html,'class="pc-pack-options"'),'No quantity-losing group navigation');
 mixed_check(wc_get_product($vs[5])->get_stock_quantity()===null,'Unknown stock stays null');
 WP_CLI::success('Mixed 4/6/12 native cart totals, mixed models, parent/price/tax/stock boundaries, price-change checkout rejection, metadata, removal/restoration and all-group form passed.');
}finally{if(WC()->cart)WC()->cart->empty_cart();foreach(array_reverse($ids) as $id)wp_delete_post($id,true);}
