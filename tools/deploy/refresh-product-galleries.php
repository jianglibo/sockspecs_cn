<?php
/** Explicit reviewed gallery refresh; existing products only, no stock initialization. */
function gr_check($ok,$message){if(!$ok){throw new RuntimeException($message);}}
gr_check(defined('WP_CLI') && WP_CLI && current_user_can('manage_options'),'Administrator WP-CLI required.');
[$mode,$path]=array_pad($args??[],2,'');
gr_check(in_array($mode,['plan','prepare','apply','verify','rollback'],true) && is_file($path),'Expected mode and manifest.');
$m=json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
gr_check($m['operation']==='gallery-refresh-20261020' && DB_NAME===$m['database'] && in_array(home_url(),$m['sites'],true),'Wrong site or operation.');
$allowed=['X31','X72','X65','J02-J03','X70','365','364','X112','X109'];$keys=array_map('strval',array_keys($m['products']));sort($allowed);sort($keys);gr_check($allowed===$keys,'Only the nine reviewed products are allowed.');
gr_check($mode!=='prepare' || str_starts_with(home_url(),'http://127.0.0.1:'),'Prepare is local-only.');
$products=[];foreach($m['products'] as $model=>$row){$p=wc_get_product(wc_get_product_id_by_sku($row['sku']));gr_check($row['sku']==='SS-001-'.$model && $p && $p->is_type('variable') && count($p->get_children())===$row['variants'],'Product/variant mismatch: '.$model);$products[$model]=$p;}
function gr_data($p,$parent=false){
 $d=$p->get_data();if($parent){unset($d['image_id'],$d['gallery_image_ids'],$d['date_modified']);}
 $mutable=['_wc_gla_sync_status','_wc_gla_errors','_wc_gla_synced_at','_wc_gla_google_ids','_wc_gla_sync_hash'];
 $d['meta_data']=array_values(array_filter(array_map(static fn($v)=>$v->get_data(),$d['meta_data']),static fn($v)=>!in_array($v['key'],$mutable,true)));
 return $d;
}
function gr_snapshot($products){
 $out=[];foreach($products as $model=>$p){$p=wc_get_product($p->get_id());$out[$model]=gr_data($p,true);foreach($p->get_children() as $id){$out[$model]['children'][$id]=gr_data(wc_get_product($id));}}
 foreach(['X59','323'] as $model){$p=wc_get_product(wc_get_product_id_by_sku('SS-001-'.$model));$out['retained_'.$model]=[(int)$p->get_image_id(),$p->get_gallery_image_ids()];}
 $out['settings']=[get_option('woocommerce_coming_soon'),get_woocommerce_currency()];return $out;
}
// Keep the complete photograph in catalog and gallery thumbnails.
add_filter('intermediate_image_sizes_advanced',static function($sizes){foreach(['woocommerce_thumbnail','woocommerce_gallery_thumbnail'] as $key){if(isset($sizes[$key])){$sizes[$key]['crop']=false;}}return $sizes;});
if($mode==='plan'){foreach($products as $model=>$p){WP_CLI::line($model.': replace parent gallery with '.count($m['products'][$model]['images']).' images');}return;}
$key='ss_gallery_refresh_20261020';global $wpdb;gr_check((int)$wpdb->get_var("SELECT GET_LOCK('ss_gallery_refresh_20261020',0)")===1,'Import locked.');
try{
 $state=get_option($key);
 if($mode==='rollback'){
  gr_check($state && $state['complete'],'Completed import required.');
  foreach($products as $model=>$p){gr_check([(int)$p->get_image_id(),array_map('intval',$p->get_gallery_image_ids())]===[$state['ids'][$model][0],array_slice($state['ids'][$model],1)],'Gallery changed since import.');}
  foreach($products as $model=>$p){$p->set_image_id($state['old'][$model][0]);$p->set_gallery_image_ids($state['old'][$model][1]);$p->save();wc_delete_product_transients($p->get_id());}
  $state['rolled_back']=true;update_option($key,$state,false);WP_CLI::success('Previous galleries restored; media retained.');return;
 }
 if(in_array($mode,['prepare','apply'],true) && !$state){
  foreach($m['products'] as $row){gr_check(count($row['images'])===2,'Two reviewed images required.');foreach($row['images'] as $im){$file=realpath(dirname($path).'/'.$im['file']);gr_check($file && str_starts_with($file,realpath(dirname($path)).'/') && hash_file('sha256',$file)===$im['sha256'],'Source checksum/path mismatch.');$info=wp_getimagesize($file);gr_check($info && ($mode==='prepare' || $info['mime']==='image/webp'),'Reviewed WebP required.');}}
  $before=gr_snapshot($products);$state=['before'=>$before,'preserved'=>hash('sha256',wp_json_encode($before)),'old'=>[],'ids'=>[],'complete'=>false];foreach($products as $model=>$p){$state['old'][$model]=[(int)$p->get_image_id(),$p->get_gallery_image_ids()];}add_option($key,$state,'',false);
  require_once ABSPATH.'wp-admin/includes/image.php';require_once ABSPATH.'wp-admin/includes/file.php';require_once ABSPATH.'wp-admin/includes/media.php';
  foreach($products as $model=>$p){
   foreach($m['products'][$model]['images'] as $im){$source=dirname($path).'/'.$im['file'];$tmp=wp_tempnam(basename($source));gr_check(copy($source,$tmp),'Source copy failed.');$id=media_handle_sideload(['name'=>basename($source),'tmp_name'=>$tmp],$p->get_id(),$im['title']);if(is_wp_error($id)){@unlink($tmp);throw new RuntimeException($id->get_error_message());}update_post_meta($id,'_wp_attachment_image_alt',$im['alt']);$state['ids'][$model][]=$id;update_option($key,$state,false);}
  }
  // Only switch galleries after all media imports finish successfully.
  foreach($products as $model=>$p){$p->set_image_id($state['ids'][$model][0]);$p->set_gallery_image_ids(array_slice($state['ids'][$model],1));$p->save();wc_delete_product_transients($p->get_id());}
  $state['complete']=true;update_option($key,$state,false);
 }
 gr_check($state && $state['complete'] && empty($state['rolled_back']),'Missing/incomplete/rolled-back import; inspect state before retry.');
 gr_check(hash('sha256',wp_json_encode($state['before']))===$state['preserved'],'Before-state integrity mismatch.');
 gr_check(hash('sha256',wp_json_encode(gr_snapshot($products)))===$state['preserved'],'Non-image product data, variants or visibility changed.');
 $report=[];foreach($products as $model=>$p){$p=wc_get_product($p->get_id());gr_check([(int)$p->get_image_id(),array_map('intval',$p->get_gallery_image_ids())]===[$state['ids'][$model][0],array_slice($state['ids'][$model],1)],'Gallery assignment mismatch.');
  $images=[];foreach($state['ids'][$model] as $i=>$id){$im=$m['products'][$model]['images'][$i];$meta=wp_get_attachment_metadata($id);$file=get_attached_file($id);gr_check(is_file($file) && !empty($meta['sizes']['woocommerce_thumbnail']) && wp_get_attachment_image_srcset($id,'full'),'Image/thumbnail/srcset missing.');gr_check(abs($meta['width']/$meta['height']-$meta['sizes']['woocommerce_thumbnail']['width']/$meta['sizes']['woocommerce_thumbnail']['height'])<0.03,'Catalog thumbnail unexpectedly cropped.');gr_check(get_post_meta($id,'_wp_attachment_image_alt',true)===$im['alt'],'Alt text mismatch.');if($mode!=='prepare'){gr_check(get_post_mime_type($id)==='image/webp' && hash_file('sha256',$file)===$im['sha256'],'Image differs from reviewed WebP.');}$images[]=['id'=>$id,'url'=>wp_get_attachment_url($id),'file'=>$file,'width'=>$meta['width'],'height'=>$meta['height'],'bytes'=>filesize($file)];}
  $report[$model]=['id'=>$p->get_id(),'url'=>get_permalink($p->get_id()),'images'=>$images];
 }
 WP_CLI::line(wp_json_encode(['status'=>'verified','products'=>$report,'preserved'=>'product data, variants, prices, stock, visibility, X59 and 323 galleries'],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
}finally{$wpdb->get_var("SELECT RELEASE_LOCK('ss_gallery_refresh_20261020')");}
