<?php
/** Explicit, idempotent model migration. Run with WP-CLI: eval-file FILE [plan|apply|verify]. */
if (!defined('WP_CLI') || !WP_CLI || !in_array(parse_url(home_url(),PHP_URL_HOST),['sockspecs.cn','sockspecs.com','127.0.0.1','localhost'],true)) {throw new RuntimeException('Unexpected site.');}
$mode=$args[0] ?? 'plan';
if(!in_array($mode,['plan','apply','verify'],true)){throw new RuntimeException('Use plan, apply or verify.');}
if(!class_exists('ParameterCommerce\Product_Identity')){throw new RuntimeException('Deploy identity code first.');}
$admin=get_users(['role'=>'administrator','fields'=>'ID','number'=>1]);
if(!$admin){throw new RuntimeException('No administrator.');}wp_set_current_user($admin[0]);
$models=['X109','X112','364','365','X70','323','X31','X59','X72','X65','J02-J03'];
$map=[];foreach($models as $i=>$model){$map[$model]='SS-S'.sprintf('%04d',$i+1);}
function identity_assert($ok,$message){if(!$ok){throw new RuntimeException($message);}}
function identity_guard($p){
 $d=$p->get_data();$out=[];
 foreach(['id','parent_id','slug','status','regular_price','sale_price','price','manage_stock','stock_quantity','stock_status','backorders','tax_status','tax_class','weight','length','width','height','image_id','gallery_image_ids','category_ids','tag_ids','catalog_visibility','date_on_sale_from','date_on_sale_to'] as $key){$out[$key]=$d[$key] ?? null;}
 $out['supplier']=$p->get_meta('_pc_supplier_id');
 $out['attributes']=[];foreach($p->get_attributes() as $key=>$value){if(strtolower($key)==='model'){continue;}$out['attributes'][$key]=$value instanceof WC_Product_Attribute ? $value->get_data() : $value;}
 $out['default_attributes']=$p->get_default_attributes();unset($out['default_attributes']['model']);
 return hash('sha256',wp_json_encode($out));
}
function identity_copy($html,$map){
 $parts=wp_html_split($html);
 foreach($parts as &$part){if(str_starts_with($part,'<'))continue;
  foreach($map as $old=>$new){$part=str_replace('SS-001-'.$old,$new,$part);}
  $part=preg_replace_callback('/(?<![A-Za-z0-9])(?:J02-J03|X109|X112|X70|X31|X59|X72|X65|J02|J03|364|365|323)(?![A-Za-z0-9])/i',static function($m) use($map){$k=strtoupper($m[0]);return $k==='J02' ? $map['J02-J03'].'-A' : ($k==='J03' ? $map['J02-J03'].'-B' : ($map[$k] ?? $m[0]));},$part);
 }unset($part);return implode('',$parts);
}
$key='pc_model_migration_20261024';$state=get_option($key,[]);if($mode==='apply' && !empty($state['complete'])){$mode='verify';}$rows=[];$ids=[];
foreach($map as $model=>$code){$model=(string)$model;
 $id=$state['products'][$model]['id'] ?? wc_get_product_id_by_sku('SS-001-'.$model);
 if(!$id){$id=wc_get_product_id_by_sku($code);}
 $p=wc_get_product($id);
 identity_assert($p && $p->is_type('variable') && !$p->get_meta('_pc_demo_catalog') && $p->get_meta('_pc_selected_supplier_model')===$model,'Product identity mismatch: '.$model);
 identity_assert(in_array($p->get_sku(),['SS-001-'.$model,$code],true),'Unexpected SKU: '.$model);
 identity_assert(!wc_get_product_id_by_sku($code) || wc_get_product_id_by_sku($code)===$id,'Target SKU already in use.');
 $reserved=(int)get_option('pc_model_reserved_'.$code,0);identity_assert(!$reserved || $reserved===$id,'Target number reserved elsewhere.');
 $children=$p->get_children();sort($children,SORT_NUMERIC);$variants=[];
 foreach($children as $i=>$vid){$v=wc_get_product($vid);$target=$code.'-V'.sprintf('%03d',$i+1);identity_assert(!wc_get_product_id_by_sku($target) || wc_get_product_id_by_sku($target)===$vid,'Variation SKU conflict.');$variants[$vid]=$target;}
 $rows[$model]=['id'=>$id,'sku'=>$code,'variants'=>$variants];$ids[]=$id;
}
$visibility=get_option('woocommerce_coming_soon');
if($mode==='plan'){echo wp_json_encode(['mode'=>'plan','products'=>$rows,'visibility'=>$visibility],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);return;}
if($mode==='apply'){
 if(!$state){
  $state=['visibility'=>$visibility,'products'=>[],'before'=>[],'complete'=>false];
  foreach($rows as $model=>$row){$model=(string)$model;$state['products'][$model]=$row;
   foreach(array_merge([$row['id']],array_keys($row['variants'])) as $id){$p=wc_get_product($id);$state['before'][$id]=['sku'=>$p->get_sku(),'name'=>$p->get_name(),'description'=>$p->get_description(),'short_description'=>$p->get_short_description(),'original'=>$p->get_meta('_pc_selected_supplier_model'),'attributes'=>$p->get_attributes(),'defaults'=>$p->get_default_attributes(),'guard'=>identity_guard($p)];}
  }
  identity_assert(add_option($key,$state,'',false),'Migration already started; retry.');
 }
 foreach($rows as $model=>$row){$model=(string)$model;
  $p=wc_get_product($row['id']);$before=$state['before'][$p->get_id()];
  identity_assert(identity_guard($p)===$before['guard'],'Commerce state changed; inspect before continuing.');
  ParameterCommerce\Product_Identity::assign($p,'S',$row['sku']);
  $p->update_meta_data('_pc_legacy_sku',$before['sku']);
  $p->set_name(str_replace($before['sku'],$row['sku'],$before['name']));
  $p->set_description(identity_copy($before['description'],$map));$p->set_short_description(identity_copy($before['short_description'],$map));
  if($model==='J02-J03'){
   $attrs=$p->get_attributes();$attrs['model']->set_options(['A','B']);$p->set_attributes($attrs);
   $defaults=$p->get_default_attributes();if(isset($defaults['model'])){$defaults['model']=['J02'=>'A','J03'=>'B'][$defaults['model']] ?? $defaults['model'];$p->set_default_attributes($defaults);}
  }
  $p->save();
  foreach($row['variants'] as $vid=>$sku){
   $v=wc_get_product($vid);$before=$state['before'][$vid];identity_assert(identity_guard($v)===$before['guard'],'Variation commerce state changed.');
   $v->update_meta_data('_pc_legacy_sku',$before['sku']);$v->set_sku($sku);
   $original=$model;
   if($model==='J02-J03'){$old=$before['attributes']['model'];identity_assert(in_array(strtoupper($old),['J02','J03'],true),'Unknown combined style.');$original=strtoupper($old);$style=$original==='J02'?'A':'B';$attrs=$v->get_attributes();$attrs['model']=$style;$v->set_attributes($attrs);$v->update_meta_data('_pc_legacy_model_attribute',$old);$v->update_meta_data('_pc_public_style',$style);}
   $v->update_meta_data('_pc_selected_supplier_model',$original);
   $v->set_description(identity_copy($before['description'],$map));$v->save();
  }
  wc_delete_product_transients($p->get_id());clean_post_cache($p->get_id());
 }
}
identity_assert((bool)$state,'No migration snapshot.');
foreach($rows as $model=>$row){$model=(string)$model;
 $p=wc_get_product($row['id']);identity_assert($p->get_sku()===$row['sku'] && $p->get_meta('_pc_public_model')===$row['sku'],'Parent number mismatch.');
 identity_assert(str_contains($p->get_name(),$row['sku']),'Title missing model.');
 foreach(array_merge([$row['id']],array_keys($row['variants'])) as $id){$item=wc_get_product($id);identity_assert(identity_guard($item)===$state['before'][$id]['guard'],'Commerce data differs: '.$id);identity_assert($item->get_meta('_pc_legacy_sku')===$state['before'][$id]['sku'],'Legacy SKU missing.');}
 foreach($row['variants'] as $vid=>$sku){$v=wc_get_product($vid);identity_assert($v->get_sku()===$sku && $v->get_meta('_pc_selected_supplier_model'),'Variation reference missing.');}
}
identity_assert(get_option('woocommerce_coming_soon')===$state['visibility'],'Visibility changed.');
if($mode==='apply'){$state['complete']=true;update_option($key,$state,false);}
echo wp_json_encode(['mode'=>$mode,'parents'=>count($rows),'variations'=>array_sum(array_map(static fn($r)=>count($r['variants']),$rows)),'commerce_unchanged'=>true,'visibility_unchanged'=>true,'mapping'=>$map],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
