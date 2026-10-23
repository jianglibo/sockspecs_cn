<?php
/** Read-only, narrowly scoped source export; never exports commerce or customers. */
if (!defined('WP_CLI') || !WP_CLI || !current_user_can('manage_woocommerce') || DB_NAME === 'sockspecs_cn') throw new RuntimeException('International source only.');
use ParameterCommerce\Repository as R;
global $wpdb;
$out=['products'=>[], 'images'=>[]];
foreach (['X109','X112','364','365','X70'] as $model) {
 $p=wc_get_product(wc_get_product_id_by_sku('SS-001-'.$model));
 if (!$p || !$p->get_meta('_pc_structured_specs')) throw new RuntimeException('Missing structured model');
 $row=['id'=>$p->get_id(),'model'=>$model,'sku'=>$p->get_sku(),'slug'=>$p->get_slug(),'name'=>$p->get_name(),'description'=>$p->get_description(),'short_description'=>$p->get_short_description(),'image_id'=>$p->get_image_id(),'gallery'=>$p->get_gallery_image_ids(),'variations'=>[],'claims'=>[]];
 $images=array_merge([$p->get_image_id()],$p->get_gallery_image_ids());
 foreach($p->get_children() as $id){$v=wc_get_product($id);$row['variations'][]=['id'=>$id,'sku'=>$v->get_sku(),'attributes'=>$v->get_attributes(),'image_id'=>$v->get_image_id()];$images[]=$v->get_image_id();}
 $ids=$wpdb->get_col($wpdb->prepare('SELECT id FROM %i WHERE wc_product_id=%d AND status=%s ORDER BY id',$wpdb->prefix.'pc_claims',$p->get_id(),'published'));
 foreach($ids as $id){$c=R::find($id);$r=array_intersect_key($c,array_flip(['id','wc_variation_id','metric_key','value_num','value_enum','unit','source_type','size_scope','method_code','method_version','conditions_json','sample_count','sample_batch','measured_at','note']));$r['evidence']=array_intersect_key($c['evidence'],array_flip(['public_title','source_org','note','source_url','public_url','visibility','attachment_id']));$row['claims'][]=$r;}
 foreach(array_unique(array_filter($images)) as $id){$out['images'][$id]=['url'=>wp_get_attachment_url($id),'file'=>basename(get_attached_file($id)),'sha256'=>hash_file('sha256',get_attached_file($id))];}
 $out['products'][]=$row;
}
WP_CLI::line(wp_json_encode($out,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
