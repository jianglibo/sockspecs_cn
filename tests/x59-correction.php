<?php
/** Local, read-only regression for the corrected X59 catalog data. */
use ParameterCommerce\Repository;
use ParameterCommerce\Comparison;
// No orders, emails or fixture writes.
if(!defined('WP_CLI') || !WP_CLI || wp_get_environment_type()!=='local' || !in_array(DB_NAME,['sockspecs','sockspecs_cn'],true))throw new RuntimeException('Local stores only.');
$n=0;$check=function($ok,$why)use(&$n){if(!$ok)throw new RuntimeException($why);++$n;};
$p=wc_get_product(wc_get_product_id_by_sku('SS-001-X59'));$cn=DB_NAME==='sockspecs_cn';$state=get_option('pc_x59_correction_20261021');
$expected=$cn?['白灰','白蓝','白绿','白紫']:['White Gray','White Blue','White Green','White Purple'];
$check($p->get_attributes()['color']->get_options()===$expected,'Parent palette');
$check(count($p->get_children())===4,'Exactly four variants');
foreach($p->get_children() as $id){$v=wc_get_product($id);$old=$state['children'][$id];$check(in_array($v->get_attribute('color'),$expected,true),'New child palette');$check(str_contains($v->get_sku(),'-WHITE-'),'New SKU');foreach(['price','regular_price','sale_price','manage_stock','stock_quantity','image_id','status'] as $key)$check($v->get_data()[$key]===$old[$key],'Preserve '.$key);}
$check($p->get_image_id()===$state['parent']['image_id'],'Existing photo retained');
$check($p->get_price()===$state['parent']['price'],'Parent price preserved');
wp_set_current_user(0);
$s=Repository::public_specifications($p->get_id());$check($s['groups']['material']['metrics']['material_trade_name']['value']==='COOLMAX','Public material');
foreach($s['groups']['composition']['metrics'] as $m)$check($m['value']===null,'No invented fibre percentage');
foreach($p->get_children() as $id)$check(Repository::public_specifications($p->get_id(),$id)['groups']['material']['metrics']['material_trade_name']['value']==='COOLMAX','Explicit model declaration inheritance');
$x70=wc_get_product_id_by_sku('SS-001-X70');$check(Repository::public_specifications($x70)['groups']['material']['metrics']['material_trade_name']['value']===null,'No material spread to X70');
$_GET['pc_compare']=$p->get_id().','.$x70;$html=Comparison::render();foreach($expected as $color)$check(str_contains($html,$color),'Compare colour');$check(str_contains($html,'COOLMAX'),'Compare material');$check(!str_contains($html,'Stored source SHA-256'),'Private source hidden');
WP_CLI::success("$n X59 checks passed.");
