<?php
/** Scoped domestic X59 image and copy refresh; retains originals and previous copy. */
if (!defined('WP_CLI') || !WP_CLI || DB_NAME !== 'sockspecs_cn' || !current_user_can('manage_options') ||
    !in_array(home_url(), ['http://127.0.0.1:8089', 'https://sockspecs.cn'], true)) throw new RuntimeException('Domestic administrator CLI only');
$mode=$args[0]??'verify';
if(!in_array($mode,['apply','verify','rollback'],true))throw new RuntimeException('Use apply, verify or rollback');
$p=wc_get_product(wc_get_product_id_by_sku('SS-001-X59'));
if(!$p || !$p->is_type('variable') || count($p->get_children())!==4)throw new RuntimeException('Unexpected X59 product');
$m=json_decode(file_get_contents(__DIR__.'/x59-gallery-20261021/manifest.json'),true,512,JSON_THROW_ON_ERROR);
$key='sscn_x59_gallery_20261021';$state=get_option($key);
function galleryX59_preserved($p){
 $data=$p->get_data();foreach(['image_id','gallery_image_ids','description','short_description','date_modified'] as $k)unset($data[$k]);
 $data['meta_data']=array_map(static fn($v)=>$v->get_data(),$data['meta_data']);
 $children=[];foreach($p->get_children() as $id){$d=wc_get_product($id)->get_data();$d['meta_data']=array_map(static fn($v)=>$v->get_data(),$d['meta_data']);$children[$id]=$d;}
 global $wpdb;$claims=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}pc_claims WHERE wc_product_id=%d ORDER BY id",$p->get_id()),ARRAY_A);
 return hash('sha256',wp_json_encode([$data,$children,$claims,get_option('woocommerce_coming_soon'),get_woocommerce_currency()]));
}
if($mode==='rollback'){
 if(!$state || galleryX59_preserved($p)!==$state['preserved'])throw new RuntimeException('Missing snapshot or changed product');
 $p->set_description($state['description']);$p->set_short_description($state['short']);$p->set_image_id($state['image']);$p->set_gallery_image_ids($state['gallery']);$p->save();
 $state['rolled_back']=true;update_option($key,$state,false);WP_CLI::success('Previous copy and gallery restored; media retained');return;
}
if($mode==='apply'){
 foreach($m['images'] as $im){$f=__DIR__.'/x59-gallery-20261021/'.$im['file'];if(!is_file($f)||hash_file('sha256',$f)!==$im['sha256']||wp_getimagesize($f)['mime']!=='image/webp')throw new RuntimeException('Reviewed image mismatch');}
 if(!$state){$state=['description'=>$p->get_description(),'short'=>$p->get_short_description(),'image'=>$p->get_image_id(),'gallery'=>$p->get_gallery_image_ids(),'preserved'=>galleryX59_preserved($p),'ids'=>[]];add_option($key,$state,'',false);}
 if(!empty($state['rolled_back'])||galleryX59_preserved($p)!==$state['preserved'])throw new RuntimeException('Product changed; review before applying');
 require_once ABSPATH.'wp-admin/includes/image.php';require_once ABSPATH.'wp-admin/includes/file.php';require_once ABSPATH.'wp-admin/includes/media.php';
 add_filter('intermediate_image_sizes_advanced',static function($sizes){foreach(['woocommerce_thumbnail','woocommerce_gallery_thumbnail'] as $k)if(isset($sizes[$k]))$sizes[$k]['crop']=false;return $sizes;});
 foreach($m['images'] as $i=>$im){if(isset($state['ids'][$i]))continue;$f=__DIR__.'/x59-gallery-20261021/'.$im['file'];$tmp=wp_tempnam($im['file']);if(!copy($f,$tmp))throw new RuntimeException('Copy failed');$id=media_handle_sideload(['name'=>$im['file'],'tmp_name'=>$tmp],$p->get_id(),$im['alt']);if(is_wp_error($id)){@unlink($tmp);throw new RuntimeException($id->get_error_message());}update_post_meta($id,'_wp_attachment_image_alt',$im['alt']);$state['ids'][$i]=$id;update_option($key,$state,false);}
 $p->set_description($m['description']);$p->set_short_description($m['short_description']);$p->set_image_id($state['ids'][0]);$p->set_gallery_image_ids([$state['ids'][1]]);$p->save();wc_delete_product_transients($p->get_id());
}
$p=wc_get_product($p->get_id());
if(!$state || !empty($state['rolled_back']) || galleryX59_preserved($p)!==$state['preserved'] || $p->get_description()!==$m['description'] || $p->get_short_description()!==$m['short_description'] || (int)$p->get_image_id()!==$state['ids'][0] || array_map('intval',$p->get_gallery_image_ids())!==[$state['ids'][1]])throw new RuntimeException('Content or preservation check failed');
foreach($state['ids'] as $i=>$id){if(hash_file('sha256',get_attached_file($id))!==$m['images'][$i]['sha256']||!wp_get_attachment_image_srcset($id,'full'))throw new RuntimeException('Published media check failed');}
WP_CLI::success('X59 copy/gallery verified; variations, prices, inventory, claims and visibility preserved: '.get_permalink($p->get_id()));
