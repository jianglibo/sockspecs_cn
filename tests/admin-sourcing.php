<?php
/** Staff sourcing lookup tests in real WordPress. Fixtures are rolled back. */
if (!defined('WP_CLI') || !WP_CLI || wp_get_environment_type() !== 'local') {throw new RuntimeException('Local tests only.');}
use ParameterCommerce\Product_Identity as I;
use ParameterCommerce\Repository as R;
function sourcing_ok($ok,$message){if(!$ok)throw new RuntimeException($message);echo "PASS $message\n";}
require_once ABSPATH.'wp-admin/includes/admin.php';
require_once WC_ABSPATH.'includes/admin/list-tables/class-wc-admin-list-table-products.php';
$admin=get_users(['role'=>'administrator','fields'=>'ID','number'=>1]);wp_set_current_user($admin[0]);
set_current_screen('edit-product');$GLOBALS['pagenow']='edit.php';$GLOBALS['typenow']='product';
$native=new WC_Admin_List_Table_Products();
global $wpdb;$wpdb->query('START TRANSACTION');
try{
 $p=new WC_Product_Simple();$p->set_name('FICTIONAL catalog fixture');$p->set_status('draft');$p->set_sku('DEMO-SOURCING-TEST');$p->update_meta_data('_pc_selected_supplier_model','QZ-007');$p->save();
 $q=new WC_Product_Variable();$q->set_name('FICTIONAL combined fixture');$q->set_status('draft');$q->save();
 $v=new WC_Product_Variation();$v->set_parent_id($q->get_id());$v->update_meta_data('_pc_selected_supplier_model','CHILD-01');$v->save();
 sourcing_ok(in_array($p->get_id(),I::manufacturer_ids('qz-007'),true),'case-insensitive original-model lookup');
 sourcing_ok(in_array($p->get_id(),I::manufacturer_ids('QZ-'),true),'partial model lookup');
 sourcing_ok(in_array($q->get_id(),I::manufacturer_ids('CHILD-01'),true),'variation original model finds parent');
 sourcing_ok(!in_array($v->get_id(),I::manufacturer_ids('CHILD-01'),true),'parent search does not return child IDs');
 sourcing_ok(!I::manufacturer_ids('%') && !I::manufacturer_ids("' OR 1=1 --"),'wildcards and SQL text remain literal');
 $_GET=['s'=>'QZ-007'];$request=apply_filters('request',['post_type'=>'product','s'=>'QZ-007']);
 $query=new WP_Query(array_merge($request,['post_status'=>'draft','fields'=>'ids']));
 sourcing_ok(in_array($p->get_id(),$query->posts,true),'native WooCommerce product-list search matches private model');
 $_GET=['s'=>'DEMO-SOURCING-TEST'];$request=apply_filters('request',['post_type'=>'product','s'=>'DEMO-SOURCING-TEST']);
 sourcing_ok(in_array($p->get_id(),$request['post__in'],true),'native SKU search still works');
 $GLOBALS['pagenow']='index.php';$input=['product_search'=>true,'post__in'=>[0],'product_search_term'=>'QZ-007'];sourcing_ok(I::product_search($input)===$input,'other screens unaffected');$GLOBALS['pagenow']='edit.php';
 $columns=I::columns(['name'=>'Name','sku'=>'SKU','taxonomy-product_brand'=>'Brand','date'=>'Date']);
 sourcing_ok(isset($columns['pc_supplier'],$columns['pc_original_model'])&&!isset($columns['taxonomy-product_brand']),'supplier replaces brand and original column added');
 ob_start();I::column('pc_original_model',$p->get_id());$html=ob_get_clean();sourcing_ok($html==='QZ-007','original column shows stored private model');
 $sid=wp_insert_post(['post_type'=>'pc_supplier','post_title'=>'FICTIONAL & supplier','post_status'=>'private']);update_post_meta($sid,'_pc_supplier_code','999');update_post_meta($p->get_id(),'_pc_supplier_id',$sid);
 ob_start();I::column('pc_supplier',$p->get_id());$html=ob_get_clean();sourcing_ok(str_contains($html,'999')&&str_contains(html_entity_decode(strip_tags($html)), 'FICTIONAL & supplier')&&str_contains($html,'post='.$sid),'supplier cell links to private supplier editor and escapes text');
 sourcing_ok(str_contains(ParameterCommerce\Product_Picker::label($p),'QZ-007'),'staff product picker labels contain original model');
 $parents=I::manufacturer_ids('X31');sourcing_ok(count($parents)===1,'known local catalog fixture exists');
 $rows=R::listing(0,1,'','X31');sourcing_ok(count($rows)>0&&array_unique(array_column($rows,'wc_product_id'))==[(string)$parents[0]],'parameter filter uses model across whole product');
 $metric=$rows[0]['metric_key'];$rows=R::listing($parents[0],1,$metric,'X31');sourcing_ok(count($rows)>0&&count(array_unique(array_column($rows,'metric_key')))===1,'model combines with product and metric filters');
 sourcing_ok(R::listing(0,1,'','NONEXISTENT-SOURCE')===[],'unknown model returns no claims');
 sourcing_ok(R::listing($p->get_id(),1,'','X31')===[],'conflicting product/model filters return no claims');
 $_GET=['original_model'=>'X31','page'=>'pc-specs'];ob_start();ParameterCommerce\Admin::page();$html=ob_get_clean();
 sourcing_ok(str_contains($html,'id="pc-original-model-filter"')&&str_contains($html,'value="X31"')&&str_contains($html,'原厂型号'),'Chinese filter renders current value');
 sourcing_ok(str_contains($html,'original_model=X31'),'claim and pagination links preserve original-model filter');
 wp_set_current_user(0);sourcing_ok(array_filter(I::manufacturer_ids('QZ-007'))===[],'anonymous search cannot read private models');
 ob_start();I::column('pc_original_model',$p->get_id());sourcing_ok(ob_get_clean()==='','private columns require staff permission');
 sourcing_ok(!str_contains(ParameterCommerce\Product_Picker::label($p),'QZ-007'),'private label hidden without staff authority');
 try{R::listing(0,1,'','X31');throw new RuntimeException('Missing permission guard');}catch(ParameterCommerce\Failure $e){sourcing_ok($e->status===403,'parameter filter enforces staff authority');}
}finally{$wpdb->query('ROLLBACK');wp_cache_flush();$_GET=[];}
