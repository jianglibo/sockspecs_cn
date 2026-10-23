<?php
/** Local WooCommerce integration: cart totals, rejection, form selection, editor and draft safety. */
use ParameterCommerce\Pack_Builder;
use ParameterCommerce\Pack_Pricing;
if(!defined('WP_CLI') || !WP_CLI || wp_get_environment_type()!=='local' || DB_NAME!=='sockspecs_cn'){throw new RuntimeException('Local domestic tests only.');}
function pack_assert($ok,$label){if(!$ok){throw new RuntimeException($label);}}
$ids=[];
try{
    wc_load_cart();WC()->cart->empty_cart();
    $product=new WC_Product_Variable();$product->set_name('Disposable domestic pack test');$product->set_status('publish');$product->set_tax_status('none');
    $attr=new WC_Product_Attribute();$attr->set_name('color');$attr->set_options(['red','blue']);$attr->set_variation(true);$product->set_attributes([$attr]);
    $product->update_meta_data('_pc_pack_builder','yes');$product->update_meta_data('_pc_pack_prices',['currency'=>'CNY',4=>'27.60',6=>'41.40',12=>'82.80']);$product->save();$ids[]=$product->get_id();
    $children=[];
    foreach(['red','blue'] as $color){$v=new WC_Product_Variation();$v->set_parent_id($product->get_id());$v->set_attributes(['color'=>$color]);$v->set_status('publish');$v->set_regular_price('6.90');$v->set_price('6.90');$v->set_manage_stock(true);$v->set_stock_quantity(30);$v->set_stock_status('instock');$v->set_tax_status('none');$v->save();$ids[]=$v->get_id();$children[]=$v->get_id();}
    WC_Product_Variable::sync($product->get_id());$product=wc_get_product($product->get_id());
    foreach([4=>'27.60',6=>'41.40',12=>'82.80'] as $size=>$total){
        WC()->cart->empty_cart();Pack_Builder::add_pack($product->get_id(),(string)$size,[$children[0]=>1,$children[1]=>$size-1]);
        WC()->cart->calculate_totals();pack_assert(number_format(WC()->cart->get_cart_contents_total(),2,'.','')===$total,'Cart total '.$size);
        WC()->cart->calculate_totals();pack_assert(number_format(WC()->cart->get_cart_contents_total(),2,'.','')===$total,'Repeated total '.$size);
    }
    foreach([8,10,0,'04',4.0] as $bad){$thrown=false;try{Pack_Builder::selection($product->get_id(),$bad,[$children[0]=>4]);}catch(RuntimeException $e){$thrown=true;}pack_assert($thrown,'Reject invalid pack');}
    $thrown=false;try{Pack_Builder::selection($product->get_id(),4,[$children[0]=>3]);}catch(RuntimeException $e){$thrown=true;}pack_assert($thrown,'Reject wrong quantity');
    $groups=Pack_Builder::groups($product->get_id());$selected=array_key_first($groups);$preview=false;
    foreach([null=>4,'6'=>6,'12'=>12,'8'=>4] as $request=>$expected){$_GET=[];if($request!==''){$_GET['pc_pack_size']=(string)$request;}ob_start();include dirname(__DIR__).'/parameter-commerce/templates/pack-builder.php';$html=ob_get_clean();pack_assert((bool)preg_match('/<option value="'.$expected.'"\s+selected=/',$html),'Default/query selection');pack_assert(str_contains($html,'4 双'),'Chinese 4-pair label');}
    $trial=clone $product;$trial->update_meta_data('_pc_pack_prices',['currency'=>'CNY',6=>'41.40',12=>'82.80']);$thrown=false;try{Pack_Pricing::prices($trial);}catch(RuntimeException $e){$thrown=true;}pack_assert($thrown,'Missing 4-pack fails closed');
    $_POST=['woocommerce_meta_nonce'=>wp_create_nonce('woocommerce_save_data'),'_pc_pack_price_4'=>'28.00','_pc_pack_price_6'=>'42.00','_pc_pack_price_12'=>'84.00'];Pack_Pricing::save($trial);pack_assert(Pack_Pricing::prices($trial)===[4=>'28.00',6=>'42.00',12=>'84.00'],'Editor saves three prices');
    foreach([4,6,12] as $n){$_POST['_pc_pack_price_'.$n]='';}Pack_Pricing::save($trial);pack_assert(Pack_Pricing::prices($trial)===[],'Editor clears all prices');
    foreach(['323','X31','X59','X72','X65','J02-J03'] as $model){$p=wc_get_product(wc_get_product_id_by_sku('SS-001-'.$model));if(!empty(get_option('sscn_publish_stock_20261020')['complete'])){pack_assert($p->get_status()==='publish' && $p->is_purchasable(),'Published pack available');}else{pack_assert($p->get_status()==='draft' && !$p->is_purchasable(),'Real draft remains unavailable');}}
    WP_CLI::success('Domestic 4/6/12 totals, recalculation, invalid selection rejection, Chinese form defaults, editor saves and current publication guards passed.');
}finally{
    if(WC()->cart){WC()->cart->empty_cart();}foreach(array_reverse($ids) as $id){$p=wc_get_product($id);if($p){$p->delete(true);}}$_GET=[];$_POST=[];
}
