<?php
/** Scoped, repeatable model 323 image import. prepare is local-only; apply requires reviewed WebP. */
function image323_check($ok,$msg){if(!$ok){throw new RuntimeException($msg);}}
image323_check(defined('WP_CLI') && WP_CLI && current_user_can('manage_options'),'Administrator WP-CLI required.');
[$mode,$manifest_path]=array_pad($args??[],2,'');
image323_check(in_array($mode,['prepare','apply','verify','rollback'],true) && is_file($manifest_path),'Expected prepare|apply|verify|rollback and manifest.');
$m=json_decode(file_get_contents($manifest_path),true,512,JSON_THROW_ON_ERROR);
image323_check(DB_NAME===$m['database'] && in_array(home_url(),$m['sites'],true),'Wrong store.');
image323_check($mode!=='prepare' || str_starts_with(home_url(),'http://127.0.0.1:'),'Master preparation is local-only.');
$p=wc_get_product(wc_get_product_id_by_sku('SS-001-323'));
image323_check($p && $p->is_type('variable') && count($p->get_children())===5,'Expected model 323 and five variations.');
function image323_meta($data,$id){
 $meta=array_map(static fn($v)=>is_object($v)?$v->get_data():$v,$data['meta_data']);
 $baseline=$GLOBALS['image323_google_before'][$id]??null;
 if($baseline!==null){
  $mutable=['_wc_gla_sync_status','_wc_gla_errors','_wc_gla_synced_at','_wc_gla_google_ids','_wc_gla_sync_hash'];
  $meta=array_values(array_filter($meta,static fn($v)=>!in_array($v['key'],$mutable,true)));
  $meta=array_merge($meta,$baseline);usort($meta,static fn($a,$b)=>$a['id']<=>$b['id']);
 }
 $data['meta_data']=$meta;return $data;
}
function image323_preserved($p){
 $data=image323_meta($p->get_data(),$p->get_id());unset($data['image_id'],$data['gallery_image_ids'],$data['date_modified']);
 $data['meta_data']=array_values(array_filter($data['meta_data'],static fn($v)=>$v['key']!=='_wc_gla_sync_status'));
 $variants=[];foreach($p->get_children() as $id){$variants[$id]=image323_meta(wc_get_product($id)->get_data(),$id);}
 return hash('sha256',wp_json_encode([$data,$variants,get_option('woocommerce_coming_soon'),get_woocommerce_currency()]));
}
$key='ss_323_images_20261020';global $wpdb;
image323_check((int)$wpdb->get_var("SELECT GET_LOCK('ss_323_images_20261020',0)")===1,'Image import locked.');
try{
 $state=get_option($key);$GLOBALS['image323_google_before']=$state['google_before']??[];
 if($mode==='rollback'){
  image323_check($state && $state['complete'],'No completed import to roll back.');
  image323_check(image323_preserved($p)===$state['preserved'],'Product changed after import; review rollback first.');
  $p->set_image_id($state['image']);$p->set_gallery_image_ids($state['gallery']);$p->save();delete_option($key);
  WP_CLI::success('Original gallery restored; media retained.');return;
 }
 if(in_array($mode,['prepare','apply'],true) && !$state){
  image323_check(count($m['images'])===2 && !$p->get_image_id() && !$p->get_gallery_image_ids(),'Expected two source images and an empty gallery.');
  foreach($m['images'] as $im){$f=dirname($manifest_path).'/'.$im['file'];image323_check(is_file($f) && hash_file('sha256',$f)===$im['sha256'],'Source checksum mismatch.');$info=wp_getimagesize($f);image323_check($info && ($mode==='prepare' || $info['mime']==='image/webp'),'Reviewed WebP required.');}
  $state=['image'=>$p->get_image_id(),'gallery'=>$p->get_gallery_image_ids(),'preserved'=>image323_preserved($p),'ids'=>[],'complete'=>false];add_option($key,$state,'',false);
  require_once ABSPATH.'wp-admin/includes/image.php';require_once ABSPATH.'wp-admin/includes/file.php';require_once ABSPATH.'wp-admin/includes/media.php';
  foreach($m['images'] as $im){
   $f=dirname($manifest_path).'/'.$im['file'];$tmp=wp_tempnam(basename($f));image323_check(copy($f,$tmp),'Copy failed.');
   $id=media_handle_sideload(['name'=>basename($f),'tmp_name'=>$tmp],$p->get_id(),$im['title']);
   if(is_wp_error($id)){@unlink($tmp);throw new RuntimeException($id->get_error_message());}
   update_post_meta($id,'_wp_attachment_image_alt',$im['alt']);$state['ids'][]=$id;update_option($key,$state,false);
  }
  $p->set_image_id($state['ids'][0]);$p->set_gallery_image_ids([$state['ids'][1]]);$p->save();wc_delete_product_transients($p->get_id());
  $state['complete']=true;update_option($key,$state,false);
 }
 image323_check($state && $state['complete'],'Incomplete or missing import; inspect saved state.');
 $p=wc_get_product($p->get_id());
 image323_check(image323_preserved($p)===$state['preserved'],'Non-image product data, variants or settings changed.');
 image323_check((int)$p->get_image_id()===$state['ids'][0] && array_map('intval',$p->get_gallery_image_ids())===[$state['ids'][1]],'Gallery assignment mismatch.');
 $report=[];
 foreach($state['ids'] as $i=>$id){
  $meta=wp_get_attachment_metadata($id);$file=get_attached_file($id);
  image323_check(is_file($file) && !empty($meta['sizes']) && wp_get_attachment_image_srcset($id,'full'),'Missing image or responsive sizes.');
  image323_check(get_post_meta($id,'_wp_attachment_image_alt',true)===$m['images'][$i]['alt'],'Wrong alt text.');
  if($mode!=='prepare'){image323_check(get_post_mime_type($id)==='image/webp' && hash_file('sha256',$file)===$m['images'][$i]['sha256'],'Published asset differs from reviewed WebP.');}
  $report[]=['id'=>$id,'file'=>$file,'url'=>wp_get_attachment_url($id),'width'=>$meta['width'],'height'=>$meta['height'],'bytes'=>filesize($file),'sizes'=>count($meta['sizes'])];
 }
 WP_CLI::line(wp_json_encode(['product'=>$p->get_id(),'status'=>'verified','preserved'=>'prices, stock, variants, specifications and visibility','images'=>$report],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
}finally{$wpdb->get_var("SELECT RELEASE_LOCK('ss_323_images_20261020')");}
