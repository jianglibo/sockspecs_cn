<?php
/** Local real-catalog cart validation without creating orders or changing inventory. */
use ParameterCommerce\Pack_Builder;
use ParameterCommerce\Pack_Pricing;
if(!defined('WP_CLI') || !WP_CLI || DB_NAME!=='sockspecs_cn' || wp_get_environment_type()!=='local'){throw new RuntimeException('Local domestic integration only.');}
function published_assert($ok,$message){if(!$ok){throw new RuntimeException($message);}}
wc_load_cart();$staff=get_current_user_id();wp_set_current_user(0);$tests=0;
try{
 foreach(['323','X31','X59','X72','X65','J02-J03','X70','X109','X112','364','365'] as $model){
  $p=wc_get_product(wc_get_product_id_by_sku('SS-001-'.$model));$groups=Pack_Builder::groups($p->get_id());
  foreach($groups as $group){$ids=array_keys($group['items']);
   foreach(Pack_Pricing::prices($p) as $size=>$price){
    WC()->cart->empty_cart();Pack_Builder::add_pack($p->get_id(),$size,count($ids)>1?[$ids[0]=>1,$ids[1]=>$size-1]:[$ids[0]=>$size]);
    published_assert(number_format(WC()->cart->get_cart_contents_total(),2,'.','')===$price,'Incorrect cart total '.$model);
    foreach(WC()->cart->get_cart() as $item){published_assert($item['pc_pack']['size']===$size,'Incorrect native pack lines');}$tests++;
   }
  }
 }
 WP_CLI::success('Published catalog: '.$tests.' guest model/size pack/cart cases passed. No orders placed.');
}finally{WC()->cart->empty_cart();wp_set_current_user($staff);}
