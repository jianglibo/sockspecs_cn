<?php
/** Runtime checks against the imported domestic catalog; no product mutations. */
use ParameterCommerce\Repository as R;
use ParameterCommerce\Catalog;
use ParameterCommerce\Comparison;
use ParameterCommerce\Public_Specifications as P;
use ParameterCommerce\Domestic_Catalog as D;
if(!defined('WP_CLI')||!WP_CLI||DB_NAME!=='sockspecs_cn')throw new RuntimeException('Domestic CLI only');
$GLOBALS['sscn_test_count']=0;function check_catalog($ok,$message){$GLOBALS['sscn_test_count']++;if(!$ok)throw new RuntimeException($message);}
$state=get_option('sscn_catalog_import_20261019');check_catalog(!empty($state['complete']),'Import complete');
wp_set_current_user(0);
check_catalog(Catalog::search([])['total']===5,'Anonymous catalog has five products');
check_catalog(Comparison::url()===get_permalink(get_page_by_path('compare')),'Domestic compare page resolves');
$weight_count=0;$blank_count=0;$scope_ids=[];
foreach(['X109','X112','364','365','X70'] as $model){
 $p=wc_get_product(wc_get_product_id_by_sku('SS-001-'.$model));check_catalog(P::visible($p->get_id()),'Parent visible');
 foreach($p->get_children() as $vid){
  $v=wc_get_product($vid);check_catalog(P::visible($p->get_id(),$vid),'Preview variation visible despite missing price/stock');
  check_catalog(!$v->is_purchasable() && $v->get_price()==='' && $v->get_stock_quantity()===null && !$v->get_manage_stock(),'No fabricated price/quantity or purchase access');
  check_catalog(!D::purchasable(true,$v),'Preview blocks purchase even if native permits');
  $spec=R::public_specifications($p->get_id(),$vid);$m=$spec['groups']['measurement']['metrics']['pair_weight_g'];
  if($m['value']===null){$blank_count++;check_catalog(($model==='X109' && $v->get_attribute('color')==='青灰')||($model==='364'&&$v->get_attribute('color')==='丁香紫'),'Only undocumented net weights blank');}
  else{$weight_count++;check_catalog(json_decode($m['record']['conditions_json'],true)['packaging']==='excluded','Only unpackaged pair weight');}
  if($model==='X70'){check_catalog((float)$m['value']===37.0,'X70 37g, never 46g');check_catalog((float)$spec['groups']['composition']['metrics']['cotton_pct']['value']===70.0,'Declared model composition inherits');}
  else{check_catalog($spec['groups']['composition']['metrics']['wool_pct']['value']===null,'Sample report never inherited to variants');}
  $html=P::render($p->get_id(),$vid);check_catalog(!str_contains($html,'Stored source SHA-256')&&!str_contains($html,'Migration record:'),'Private source metadata not rendered');
  $scope_ids[]=$vid;
 }
}
check_catalog($weight_count===50 && $blank_count===2,'Expected weight coverage');
$result=Catalog::search(['pc_weight_max'=>'40']);check_catalog($result['total']===4,'Weight filter finds four X70 variants');
check_catalog(Catalog::search(['pc_weight_max'=>'40','pc_weight_basis'=>'measured'])['total']===0,'Declared values never pass measured-only filter');
check_catalog(Catalog::search(['pc_max_price'=>'100'])['total']===0,'Unknown prices excluded from price filters');
$comparison=Comparison::data(implode(',',array_slice($scope_ids,0,2)));check_catalog(count($comparison['items'])===2,'Unpriced preview variants compare');
foreach($comparison['items'] as $item){$direct=R::public_specifications($item['parent_id'],$item['variation_id']);check_catalog($direct===$item['specifications'],'Compare shares exact specification projection');}
check_catalog(!P::visible(wc_get_product($scope_ids[0])->get_parent_id(),end($scope_ids)),'Foreign variation rejected');
$regular=new WC_Product_Simple();check_catalog(D::purchasable(true,$regular) && !D::purchasable(false,$regular),'Unmarked native purchase behavior preserved');
check_catalog(get_option('woocommerce_coming_soon')==='no' && get_option('blog_public')==='0','Preview visible and indexing discouraged');
WP_CLI::success($GLOBALS['sscn_test_count'].' domestic import/public-specification/filter/commerce checks passed.');
