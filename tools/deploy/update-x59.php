<?php
/** Correct X59 native colour variants and publish its documented material name. */
use ParameterCommerce\Repository;
use ParameterCommerce\Metrics;
function x59_require($ok,$message){if(!$ok)throw new RuntimeException($message);}
$cn=DB_NAME==='sockspecs_cn';
x59_require(defined('WP_CLI') && WP_CLI && current_user_can('manage_options'),'Staff CLI required.');
x59_require(DB_NAME===($cn?'sockspecs_cn':'sockspecs') && in_array(home_url(),$cn?['http://127.0.0.1:8089','https://sockspecs.cn']:['http://127.0.0.1:8088','https://sockspecs.com'],true) && get_woocommerce_currency()===($cn?'CNY':'USD'),'Wrong store.');
$mode=$args[0]??'verify';x59_require(in_array($mode,['apply','verify'],true),'Use apply or verify.');
$source=__DIR__.'/x59-update-20261021.json';$manifest=json_decode(file_get_contents($source),true,512,JSON_THROW_ON_ERROR);
x59_require($manifest['site']===($cn?'domestic':'international') && $manifest['model']==='X59' && str_contains(strtolower(wp_json_encode($manifest['material_source'])),'coolmax'),'Unexpected source.');
x59_require(isset(Metrics::defaults()['material_trade_name']),'Install reviewed dictionary first.');
$p=wc_get_product(wc_get_product_id_by_sku('SS-001-X59'));
x59_require($p && $p->is_type('variable') && count($p->get_children())===4,'Unexpected X59.');
$map=[
 'BLACK-GRAY'=>['WHITE-GRAY',$cn?'黑灰':'Black Gray',$cn?'白灰':'White Gray'],
 'BLACK-BLUE'=>['WHITE-BLUE',$cn?'黑蓝':'Black Blue',$cn?'白蓝':'White Blue'],
 'BLACK-GREEN'=>['WHITE-GREEN',$cn?'黑绿':'Black Green',$cn?'白绿':'White Green'],
 'BLACK-PINK'=>['WHITE-PURPLE',$cn?'黑粉':'Black Pink',$cn?'白紫':'White Purple'],
];
function x59_preserved($pid){
 $result=[];
 foreach(wc_get_products(['limit'=>-1,'status'=>['publish','draft','private'],'type'=>['simple','variable','variation']]) as $obj){
  $data=$obj->get_data();
  if($obj->get_id()===$pid || $obj->get_parent_id()===$pid){
   unset($data['attributes'],$data['default_attributes'],$data['date_modified']);
   if($obj->get_parent_id()===$pid){unset($data['sku'],$data['name'],$data['attribute_summary']);}
   $data['meta_data']=array_values(array_filter(array_map(fn($m)=>$m->get_data(),$data['meta_data']),fn($m)=>!in_array($m['key'],['_wc_gla_sync_status','_wc_gla_errors','_wc_gla_synced_at','_wc_gla_google_ids','_wc_gla_sync_hash'],true)));
  }
  $result[$obj->get_id()]=$data;
 }
 return hash('sha256',serialize([$result,get_option('woocommerce_coming_soon'),get_woocommerce_currency()]));
}
$key='pc_x59_correction_20261021';$state=get_option($key);$before=x59_preserved($p->get_id());
if(!$state){
 x59_require($mode==='apply','Apply first.');$ids=[];$old=[];
 foreach($map as $suffix=>[$next,$oldcolor,$color]){
  $id=wc_get_product_id_by_sku('SS-001-X59-'.$suffix);$v=wc_get_product($id);
  x59_require($v && $v->get_parent_id()===$p->get_id() && $v->get_attribute('color')===$oldcolor,'Unexpected old colour.');
  x59_require(!wc_get_product_id_by_sku('SS-001-X59-'.$next),'Target SKU already exists.');
  $ids[$suffix]=$id;$old[$id]=$v->get_data();
 }
 $state=['hash'=>hash_file('sha256',$source),'parent'=>$p->get_data(),'children'=>$old,'ids'=>$ids,'complete'=>false,'claim_id'=>0];
 add_option($key,$state,'',false);
}
x59_require($state['hash']===hash_file('sha256',$source),'Source changed.');
if($mode==='apply'){
 foreach($map as $suffix=>[$next,$oldcolor,$color]){
  $v=wc_get_product($state['ids'][$suffix]);
  x59_require($v && $v->get_parent_id()===$p->get_id() && in_array($v->get_attribute('color'),[$oldcolor,$color],true) && in_array($v->get_sku(),['SS-001-X59-'.$suffix,'SS-001-X59-'.$next],true),'Variation changed; review before retry.');
  if($v->get_attribute('color')!==$color || $v->get_sku()!=='SS-001-X59-'.$next){
   $attrs=$v->get_attributes();$attrs['color']=$color;$v->set_attributes($attrs);$v->set_sku('SS-001-X59-'.$next);$v->save();
  }
 }
 $attrs=$p->get_attributes();x59_require(isset($attrs['color']) && !$attrs['color']->is_taxonomy(),'Expected native colour attribute.');
 $colors=array_column(array_values($map),2);
 if($attrs['color']->get_options()!==$colors){$attrs['color']=clone $attrs['color'];$attrs['color']->set_options($colors);$p->set_attributes($attrs);$defaults=$p->get_default_attributes();foreach($map as [$next,$oldcolor,$color])if(($defaults['color']??null)===$oldcolor)$defaults['color']=$color;$p->set_default_attributes($defaults);$p->save();}
 WC_Product_Variable::sync($p->get_id());wc_delete_product_transients($p->get_id());
 // Resume by exact claim identity; never create duplicate published slots.
 if(!$state['claim_id']){
  $existing=[];for($page=1;;++$page){$rows=Repository::listing($p->get_id(),$page);foreach(array_slice($rows,0,20) as $r)if($r['metric_key']==='material_trade_name')$existing[]=$r;if(count($rows)<=20)break;}
  x59_require(count($existing)<=1,'Multiple material-name claims require review.');
  if($existing){$state['claim_id']=(int)$existing[0]['id'];}
  else{
   $scope=$cn?'X59 型号的材料商品名称；适用于已列颜色':'Material trade name declared for model X59 and its listed colors';
   $note=$cn?'产品手册标注 COOLMAX；完整纤维成分及比例待确认。未独立核验商标授权或功能，不代表精梳棉，也不套用 X60／X61 的材料。':'The manufacturer workbook names COOLMAX. Full fibre composition and percentages are unconfirmed. Trademark licensing and performance have not been independently verified. Do not infer combed cotton or apply X60/X61 materials.';
   $state['claim_id']=Repository::save(['wc_product_id'=>(string)$p->get_id(),'wc_variation_id'=>'0','metric_key'=>'material_trade_name','value'=>'COOLMAX','unit'=>'','source_type'=>'supplier_declared','size_scope'=>$scope,'measured_at'=>$manifest['material_source']['operator_context_date'],'conditions_json'=>wp_json_encode(['applicability'=>'all_variations','area'=>'whole','material_component'=>'manufacturer-named fabric; fibre composition unconfirmed']),'note'=>$note],['visibility'=>'private','public_title'=>$cn?'产品手册材料标注':'Manufacturer workbook material declaration','source_org'=>'Mingjun','note'=>'Stored source SHA-256: '.hash_file('sha256',$source).'. Archived source x59-update-20261021.json; date is source receipt, not lab testing.']);
  }
  update_option($key,$state,false);
 }
 $claim=Repository::find($state['claim_id']);
 x59_require($claim['value_enum']==='COOLMAX' && (int)$claim['wc_product_id']===$p->get_id() && !(int)$claim['wc_variation_id'],'Unexpected material claim.');
 if($claim['status']==='draft'){Repository::transition((int)$claim['id'],(int)$claim['revision'],'reviewed');$claim=Repository::find((int)$claim['id']);}
 if($claim['status']==='reviewed'){Repository::transition((int)$claim['id'],(int)$claim['revision'],'published');}
 $state['complete']=true;update_option($key,$state,false);
}
x59_require($state['complete'],'Incomplete update.');
foreach($map as $suffix=>[$next,$oldcolor,$color]){$v=wc_get_product($state['ids'][$suffix]);x59_require($v->get_sku()==='SS-001-X59-'.$next && $v->get_attribute('color')===$color,'Colour/SKU mismatch.');x59_require(str_contains($v->get_data()['attribute_summary'],$color),'Derived colour summary mismatch.');}
foreach($state['children'] as $id=>$old){$v=wc_get_product($id);foreach(['price','regular_price','sale_price','manage_stock','stock_quantity','stock_status','image_id','status'] as $field)x59_require($v->get_data()[$field]===$old[$field],'Changed protected variation field: '.$field);}
$p=wc_get_product($p->get_id());x59_require($p->get_attributes()['color']->get_options()===array_column(array_values($map),2),'Parent colour mismatch.');
$spec=Repository::public_specifications($p->get_id());x59_require($spec['groups']['material']['metrics']['material_trade_name']['value']==='COOLMAX','Material not public.');
foreach($spec['groups']['composition']['metrics'] as $m)x59_require($m['value']===null,'Unexpected X59 fibre percentage.');
x59_require($before===x59_preserved($p->get_id()),'Unrelated product data, prices, stock, images or settings changed.');
WP_CLI::success('X59 verified: four white-base colours, COOLMAX declaration, no invented composition; IDs, prices, stock and images preserved.');
