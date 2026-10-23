<?php
/** Domestic X31 correction from the operator's physical sample observation, 2026-10-21. */
use ParameterCommerce\Repository;
function x31_require($ok,$message){if(!$ok)throw new RuntimeException($message);}
x31_require(defined('WP_CLI') && WP_CLI && current_user_can('manage_options') && DB_NAME==='sockspecs_cn' && get_woocommerce_currency()==='CNY' && in_array(home_url(),['http://127.0.0.1:8089','https://sockspecs.cn'],true),'Domestic administrator CLI only.');
$mode=$args[0]??'verify';x31_require(in_array($mode,['apply','verify'],true),'Use apply or verify.');
$p=wc_get_product(wc_get_product_id_by_sku('SS-001-X31'));
x31_require($p && $p->is_type('variable') && count($p->get_children())===6,'Unexpected X31 product.');
$name='经典日常棉质中筒袜 — SS-001-X31';
$short='经典日常款棉混纺中筒袜，整体厚度较均匀，内侧较平整，没有明显毛圈或局部加厚区。';
$description='<p>一款经典的日常棉混纺中筒袜，适合通勤、居家和日常休闲穿着。</p><p>袜身不同部位采用不同编织结构，但整体厚度较均匀。翻看内侧，织面较平整，没有明显毛圈，也没有脚掌、后跟突出加厚的缓冲区。适合喜欢袜底平整、不偏好局部厚垫脚感的人。</p>';
$note='实物翻看与触摸观察：各部位编织结构有所不同，但整体厚度较均匀；内侧较平整，未见明显毛圈或脚掌、后跟局部加厚区。';
function x31_rows($pid){global $wpdb;return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}pc_claims WHERE wc_product_id=%d ORDER BY id",$pid),ARRAY_A);}
function x31_preserved($p){
 $products=[];
 foreach(array_merge([$p->get_id()],$p->get_children()) as $id){
  $d=wc_get_product($id)->get_data();
  foreach($id===$p->get_id()?['name','description','short_description','category_ids','date_modified']:['name'] as $k)unset($d[$k]);
  $d['meta_data']=array_map(static fn($v)=>$v->get_data(),$d['meta_data']);$products[]=$d;
 }
 $other=array_values(array_filter(x31_rows($p->get_id()),static fn($r)=>$r['metric_key']!=='cushion_location'));
 return hash('sha256',wp_json_encode([$products,$other,get_option('woocommerce_coming_soon'),get_woocommerce_currency()]));
}
$cats=[];foreach(['socks','everyday-socks'] as $slug){$t=get_term_by('slug',$slug,'product_cat');x31_require((bool)$t,'Missing category');$cats[]=(int)$t->term_id;}
$key='sscn_x31_daily_20261021';$state=get_option($key);
if(!$state){
 x31_require($mode==='apply','Apply first.');
 $rows=array_values(array_filter(x31_rows($p->get_id()),static fn($r)=>$r['metric_key']==='cushion_location'));
 x31_require(count($rows)===1 && $rows[0]['status']==='published' && $rows[0]['value_enum']==='terry-cushioning' && $rows[0]['source_type']==='supplier_declared','Existing terry records require review.');
 $state=['old'=>Repository::find((int)$rows[0]['id']),'name'=>$p->get_name(),'description'=>$p->get_description(),'short'=>$p->get_short_description(),'categories'=>$p->get_category_ids(),'preserved'=>x31_preserved($p),'new_id'=>0];
 add_option($key,$state,'',false);
}
x31_require(x31_preserved($p)===$state['preserved'],'Commerce, images or unrelated specifications changed; review before applying.');
if($mode==='apply'){
 if(!$state['new_id']){
  $old=Repository::find((int)$state['old']['id']);
  $matches=array_values(array_filter(x31_rows($p->get_id()),static fn($r)=>(int)$r['supersedes_id']===(int)$old['id']));
  x31_require(count($matches)<=1,'Conflicting revisions.');
  $state['new_id']=$matches?(int)$matches[0]['id']:Repository::fork((int)$old['id'],(int)$old['revision']);
  update_option($key,$state,false);
 }
 $c=Repository::find((int)$state['new_id']);
 if($c['status']==='draft'){
  $input=['wc_product_id'=>(string)$p->get_id(),'wc_variation_id'=>'0','metric_key'=>'cushion_location','value'=>'no-terry','unit'=>'','source_type'=>'platform_observed','size_scope'=>'本次收到的 X31 样品；颜色和尺码未记录','measured_at'=>'2026-10-21','conditions_json'=>'{"date_basis":"operator observation supplied on this date"}','note'=>$note];
  Repository::save($input,['visibility'=>'public','public_title'=>'X31 实物结构观察','source_org'=>'SockSpecs','note'=>'店主于 2026-10-21 提供的样品翻面及触摸观察；非实验室性能测试。'],(int)$c['id'],(int)$c['revision']);
  $c=Repository::find((int)$c['id']);Repository::transition((int)$c['id'],(int)$c['revision'],'reviewed');
 }
 $c=Repository::find((int)$state['new_id']);
 x31_require($c['source_type']==='platform_observed' && $c['value_enum']==='no-terry' && $c['note']===$note,'Observation changed.');
 if($c['status']==='reviewed')Repository::transition((int)$c['id'],(int)$c['revision'],'published');
 $p->set_name($name);$p->set_description($description);$p->set_short_description($short);$p->set_category_ids($cats);$p->save();wc_delete_product_transients($p->get_id());
}
$p=wc_get_product($p->get_id());$c=Repository::find((int)$state['new_id']);
x31_require($p->get_name()===$name && $p->get_description()===$description && $p->get_short_description()===$short,'Product copy mismatch.');
$actual=$p->get_category_ids();sort($actual);sort($cats);x31_require($actual===$cats,'Category mismatch.');
x31_require($c['status']==='published' && $c['value_enum']==='no-terry' && $c['source_type']==='platform_observed' && $c['note']===$note && Repository::find((int)$state['old']['id'])['status']==='retracted','Claim revision mismatch.');
$live=array_filter(x31_rows($p->get_id()),static fn($r)=>$r['metric_key']==='cushion_location' && $r['status']==='published');x31_require(count($live)===1,'Duplicate public terry claim.');
$spec=Repository::public_specifications($p->get_id());x31_require(($spec['groups']['construction']['metrics']['cushion_location']['value']??null)==='no-terry','Public projection mismatch.');
x31_require(x31_preserved($p)===$state['preserved'],'Preservation check failed.');
WP_CLI::success('X31 daily copy/category and observed no-terry revision verified; commerce, images, other specifications and visibility preserved. '.get_permalink($p->get_id()));
