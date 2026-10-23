<?php
/** One-time, resumable domestic catalog import. Native WooCommerce + audited claims. */
use ParameterCommerce\Repository as R;
use ParameterCommerce\Validator;
use ParameterCommerce\Domestic_Catalog;

function sscn_check($ok, $message) { if (!$ok) throw new RuntimeException($message); }
sscn_check(defined('WP_CLI') && WP_CLI && DB_NAME === 'sockspecs_cn' && current_user_can('manage_options') && current_user_can('publish_pc_claims'), 'Authorized domestic administrator only.');
sscn_check(wp_get_environment_type() === 'local' || (defined('SSCN_DOMESTIC_SITE') && SSCN_DOMESTIC_SITE && home_url() === 'https://sockspecs.cn'), 'Unexpected domestic environment.');
$mode=$args[0] ?? 'plan';sscn_check(in_array($mode,['plan','apply','verify'],true),'Use plan, apply or verify.');
$file=__DIR__.'/catalog/products.zh-CN.json';$hash=hash_file('sha256',$file);$data=json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR);
sscn_check(count($data['products'])===5 && array_sum(array_map(fn($p)=>count($p['variations']),$data['products']))===52, 'Unexpected catalog shape.');
foreach($data['products'] as $p){$path=__DIR__.'/catalog/'.$p['image']['file'];sscn_check(is_file($path)&&hash_file('sha256',$path)===$p['image']['sha256'],'Image checksum mismatch.');}
$key='sscn_catalog_import_20261019';$state=get_option($key);
if($state)sscn_check($state['hash']===$hash,'Manifest changed after import; review a separate migration.');
if($mode==='plan'){WP_CLI::line(wp_json_encode(['products'=>5,'variations'=>52,'records'=>310,'images'=>5,'price'=>null,'stock_quantity'=>null,'coming_soon'=>'no','previous_import'=>$state['complete']??false],JSON_PRETTY_PRINT));return;}
global $wpdb;
sscn_check('1'===(string)$wpdb->get_var("SELECT GET_LOCK('sscn_catalog_import',10)"),'Import locked.');
try {
 if($mode==='apply' && empty($state['complete'])) {
  if(!$state){$state=['hash'=>$hash,'complete'=>false,'products'=>[],'variations'=>[],'images'=>[],'claims'=>[],'coming_soon_before'=>get_option('woocommerce_coming_soon'),'home_before'=>get_post_field('post_content',(int)get_option('page_on_front'))];add_option($key,$state,'',false);}
  require_once ABSPATH.'wp-admin/includes/image.php';require_once ABSPATH.'wp-admin/includes/file.php';require_once ABSPATH.'wp-admin/includes/media.php';
  foreach($data['products'] as $index=>$p){
   $id=wc_get_product_id_by_sku($p['sku']);$product=$id?wc_get_product($id):new WC_Product_Variable();
   sscn_check(!$id || (int)$product->get_meta('_sscn_source_product_id')===$p['source_id'],'Refusing unrelated SKU collision.');
   if(!isset($state['products'][$p['source_id']])){
    $product->set_name($p['name']);$product->set_slug($p['slug']);$product->set_sku($p['sku']);$product->set_description($p['description']);$product->set_short_description('');$product->set_status('publish');$product->set_catalog_visibility('visible');$product->set_menu_order($index);$product->set_reviews_allowed(true);
    $product->set_manage_stock(false);$product->set_stock_quantity(null);$product->set_stock_status('outofstock');$product->set_regular_price('');$product->set_sale_price('');$product->set_price('');$product->set_weight('');
    $product->update_meta_data('_sscn_catalog_preview','yes');$product->update_meta_data('_sscn_source_product_id',$p['source_id']);$product->update_meta_data('_pc_structured_specs',1);$product->update_meta_data('_pc_selected_supplier_model',$p['model']);
    $attributes=[];foreach(['color','size'] as $position=>$name){$a=new WC_Product_Attribute();$a->set_name($name);$a->set_options(array_values(array_unique(array_column(array_column($p['variations'],'attributes'),$name))));$a->set_position($position);$a->set_visible(true);$a->set_variation(true);$attributes[]=$a;}$product->set_attributes($attributes);
    $id=$product->save();$state['products'][$p['source_id']]=$id;update_option($key,$state,false);
   }
   $id=$state['products'][$p['source_id']];$product=wc_get_product($id);
   if(!isset($state['images'][$p['model']])){
    $matches=get_posts(['post_type'=>'attachment','post_status'=>'inherit','meta_key'=>'_sscn_image_sha256','meta_value'=>$p['image']['sha256'],'fields'=>'ids','numberposts'=>2]);sscn_check(count($matches)<2,'Duplicate imported image.');
    if($matches){$image=$matches[0];}else{
     $source=__DIR__.'/catalog/'.$p['image']['file'];$temp=wp_tempnam(basename($source));sscn_check(copy($source,$temp),'Cannot stage image.');
     $image=media_handle_sideload(['name'=>basename($source),'tmp_name'=>$temp],$id,$p['name']);sscn_check(!is_wp_error($image),is_wp_error($image)?$image->get_error_message():'Image import failed.');update_post_meta($image,'_sscn_image_sha256',$p['image']['sha256']);update_post_meta($image,'_wp_attachment_image_alt',$p['name'].'，颜色及外观示意');
    }
    $state['images'][$p['model']]=$image;update_option($key,$state,false);
   }
   $product->set_image_id($state['images'][$p['model']]);$product->save();
   $tag_names=['everyday'=>'日常','running'=>'跑步','hiking'=>'徒步'];$tags=[];foreach($p['tags'] as $slug){$term=get_term_by('slug',$slug,'product_tag');if(!$term){$term=wp_insert_term($tag_names[$slug],'product_tag',['slug'=>$slug]);sscn_check(!is_wp_error($term),'Tag creation failed.');$tags[]=$term['term_id'];}else{$tags[]=$term->term_id;}}wp_set_object_terms($id,$tags,'product_tag');
   $term=get_term_by('slug','socks','product_cat');if(!$term){$t=wp_insert_term('袜子','product_cat',['slug'=>'socks']);sscn_check(!is_wp_error($t),'Category creation failed.');$category=$t['term_id'];}else{$category=$term->term_id;}wp_set_object_terms($id,[(int)$category],'product_cat');
   foreach($p['variations'] as $v){
    if(isset($state['variations'][$v['source_id']]))continue;
    $vid=wc_get_product_id_by_sku($v['sku']);$variation=$vid?wc_get_product($vid):new WC_Product_Variation();sscn_check(!$vid || ((int)$variation->get_parent_id()===$id && (int)$variation->get_meta('_sscn_source_variation_id')===$v['source_id']),'Variation collision.');
    $variation->set_parent_id($id);$variation->set_sku($v['sku']);$variation->set_attributes($v['attributes']);$variation->set_status('publish');$variation->set_image_id($state['images'][$p['model']]);$variation->set_manage_stock(false);$variation->set_stock_quantity(null);$variation->set_stock_status('outofstock');$variation->set_regular_price('');$variation->set_sale_price('');$variation->set_price('');$variation->set_weight('');$variation->set_length('');$variation->set_width('');$variation->set_height('');$variation->update_meta_data('_sscn_source_variation_id',$v['source_id']);
    $state['variations'][$v['source_id']]=$variation->save();update_option($key,$state,false);
   }
   WC_Product_Variable::sync($id);wc_delete_product_transients($id);
   foreach($p['claims'] as $c){
    $source=$c['source_id'];$input=$c;$input['wc_product_id']=$id;$input['wc_variation_id']=$c['source_variation_id']?$state['variations'][$c['source_variation_id']]:0;
    Validator::publishable(Validator::claim($input),Validator::evidence($c['evidence']));
    if(!isset($state['claims'][$source])){$state['claims'][$source]=R::save($input,$c['evidence']);update_option($key,$state,false);}
    $record=R::find($state['claims'][$source]);if($record['status']==='draft'){R::transition($record['id'],(int)$record['revision'],'reviewed');$record=R::find($record['id']);}if($record['status']==='reviewed')R::transition($record['id'],(int)$record['revision'],'published');
   }
  }
  // Explicit user-authorized preview visibility change; ordinary code sync preserves this setting.
  update_option('woocommerce_coming_soon','no');update_option('blog_public','0');
  $home=(int)get_option('page_on_front');$content=get_post_field('post_content',$home);$old='我们正在整理商品与参数，确定国内售价和配送方式。当前为预览阶段，商品目录暂为空，尚未开放购买。';$new='现已整理5款袜子的图片、材质、工艺及尺码参数，可在目录中查看和对比。国内售价、库存及配送方式仍在准备中，暂未开放购买。';if(str_contains($content,$old))wp_update_post(['ID'=>$home,'post_content'=>str_replace($old,$new,$content)]);
  $state['complete']=true;update_option($key,$state,false);
 }
 sscn_check(!empty($state['complete']),'Import has not completed.');
 $n=0;$weights=0;
 foreach($data['products'] as $p){
  $id=$state['products'][$p['source_id']];$product=wc_get_product($id);sscn_check($product && $product->get_name()===$p['name'] && $product->get_description()===$p['description'] && count($product->get_children())===count($p['variations']),'Product mismatch.');
  $attachment=$state['images'][$p['model']];sscn_check(hash_file('sha256',get_attached_file($attachment))===$p['image']['sha256'],'Imported image differs.');
  foreach(array_merge([$id],$product->get_children()) as $pid){$v=wc_get_product($pid);sscn_check($v->get_regular_price()==='' && $v->get_sale_price()==='' && $v->get_price()==='' && $v->get_stock_quantity()===null && !$v->get_manage_stock() && !$v->is_purchasable() && !$v->is_in_stock(),'Price, stock or sale-safety mismatch.');sscn_check(!$v->get_meta('_pc_pack_enabled'),'International pack setting imported.');}
  foreach($p['variations'] as $v){$variant=wc_get_product($state['variations'][$v['source_id']]);sscn_check($variant->get_attributes()===$v['attributes'],'Variant attributes differ.');}
  foreach($p['claims'] as $c){$r=R::find($state['claims'][$c['source_id']]);sscn_check($r['status']==='published' && $r['metric_key']===$c['metric_key'] && $r['conditions_json']===$c['conditions_json'] && $r['size_scope']===$c['size_scope'] && $r['note']===$c['note'] && ($r['value_num']!==null?(float)$r['value_num']===(float)$c['value']:$r['value_enum']===$c['value']),'Claim mismatch.');$n++;if($c['metric_key']==='pair_weight_g')$weights++;}
 }
 sscn_check($n===310 && $weights===50 && get_option('woocommerce_coming_soon')==='no' && get_option('woocommerce_currency')==='CNY' && get_option('WPLANG')==='zh_CN','Catalog total or locale mismatch.');
 WP_CLI::line(wp_json_encode(['verified'=>true,'products'=>5,'variations'=>52,'images'=>5,'published_records'=>$n,'net_pair_weights'=>$weights,'price_and_stock_quantities'=>'unset','purchasing'=>false,'coming_soon'=>false],JSON_PRETTY_PRINT));
} finally {$wpdb->get_var("SELECT RELEASE_LOCK('sscn_catalog_import')");}
