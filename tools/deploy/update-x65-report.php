<?php
/** Supersede the unqualified X65 30% declaration with scoped supplier report data. */
use ParameterCommerce\Repository;
function x65r_require($ok,$message){if(!$ok)throw new RuntimeException($message);}
x65r_require(defined('WP_CLI') && WP_CLI && current_user_can('manage_options') && DB_NAME==='sockspecs_cn' && get_woocommerce_currency()==='CNY' && in_array(home_url(),['http://127.0.0.1:8089','https://sockspecs.cn'],true),'Domestic administrator CLI only.');
$mode=$args[0]??'verify';x65r_require(in_array($mode,['apply','verify'],true),'Use apply or verify.');
$file=__DIR__.'/x65-report-20261021.json';$m=json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR);$digest=hash_file('sha256',$file);
$p=wc_get_product(wc_get_product_id_by_sku($m['sku']));x65r_require($m['sku']==='SS-001-X65' && $p && $p->is_type('variable') && count($p->get_children())===4,'Unexpected product.');
function x65r_rows($pid){global $wpdb;return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}pc_claims WHERE wc_product_id=%d ORDER BY id",$pid),ARRAY_A);}
function x65r_preserved($p,$keys){
 $products=[];foreach(array_merge([$p->get_id()],$p->get_children()) as $id){$d=wc_get_product($id)->get_data();$d['meta_data']=array_map(static fn($v)=>$v->get_data(),$d['meta_data']);$products[]=$d;}
 $other=array_values(array_filter(x65r_rows($p->get_id()),static fn($r)=>!in_array($r['metric_key'],$keys,true)));
 return hash('sha256',wp_json_encode([$products,$other,get_option('woocommerce_coming_soon'),get_woocommerce_currency()]));
}
$before=x65r_preserved($p,array_keys($m['values']));$key='sscn_x65_report_20261021';$state=get_option($key);
if(!$state){
 x65r_require($mode==='apply','Apply first.');
 $previous=get_option('sscn_x65_wool_20261021');$old=$previous?Repository::find((int)$previous['claim_id']):null;
 x65r_require($old && (int)$old['wc_product_id']===$p->get_id() && $old['metric_key']==='wool_pct' && (float)$old['value_num']===30.0 && $old['source_type']==='supplier_declared' && $old['status']==='published','Expected prior declaration missing.');
 foreach(x65r_rows($p->get_id()) as $r)if(in_array($r['metric_key'],array_keys($m['values']),true))x65r_require((int)$r['id']===(int)$old['id'],'Existing composition requires review.');
 $state=['digest'=>$digest,'old'=>$old,'ids'=>[]];add_option($key,$state,'',false);
}
x65r_require($state['digest']===$digest,'Report source changed.');
if($mode==='apply'){
 // Stage and review every record before replacing the existing public wool value.
 foreach($m['values'] as $metric=>$value){
  if(!isset($state['ids'][$metric])){
   $matches=array_values(array_filter(x65r_rows($p->get_id()),static fn($r)=>$r['metric_key']===$metric && (int)$r['id']!==(int)$state['old']['id']));
   x65r_require(count($matches)<=1,'Duplicate report records.');
   if($matches){$c=Repository::find((int)$matches[0]['id']);x65r_require(str_contains($c['evidence']['note']??'',$m['sha256']),'Unrelated report claim.');$id=(int)$c['id'];}
   elseif($metric==='wool_pct'){$old=Repository::find((int)$state['old']['id']);$id=Repository::fork((int)$old['id'],(int)$old['revision']);}
   else{$id=0;}
   $input=['wc_product_id'=>(string)$p->get_id(),'wc_variation_id'=>'0','metric_key'=>$metric,'value'=>(string)$value,'unit'=>'%','source_type'=>'supplier_report','size_scope'=>$m['sample'],'method_code'=>$m['method_code'],'method_version'=>$m['method_version'],'measured_at'=>$m['report_date'],'sample_batch'=>'','sample_count'=>'','conditions_json'=>wp_json_encode(['area'=>'sole','tested_area'=>'sole excluding toe and heel','sample_color'=>'gray/black','sample_quantity'=>'not stated','date_basis'=>'report issue date; separate test date not stated']),'note'=>'供应商提供的样品报告 '.$m['report'].'。'.$m['limitations']];
   $evidence=['visibility'=>'private','public_title'=>'X65 样品成分检测报告 '.$m['report'],'source_org'=>$m['lab'],'note'=>'Stored source SHA-256: '.$m['sha256'].'. Privately archived x65-report-20261021/x65-report.pdf; received 2026-10-21 via Outlook. Receipt manifest SHA-256: '.$digest.'.'];
   if(!$id || ($c=Repository::find($id))['status']==='draft'){$id=Repository::save($input,$evidence,$id,$id?(int)$c['revision']:null);}
   $state['ids'][$metric]=$id;update_option($key,$state,false);
  }
  $c=Repository::find((int)$state['ids'][$metric]);
  x65r_require($c['metric_key']===$metric && (float)$c['value_num']===(float)$value && $c['source_type']==='supplier_report' && $c['size_scope']===$m['sample'],'Report claim changed.');
  if($c['status']==='draft')Repository::transition((int)$c['id'],(int)$c['revision'],'reviewed');
 }
 // Wool revision publication retracts its predecessor atomically through Repository.
 foreach($state['ids'] as $id){$c=Repository::find((int)$id);if($c['status']==='reviewed')Repository::transition((int)$c['id'],(int)$c['revision'],'published');}
}
$old=Repository::find((int)$state['old']['id']);x65r_require($old['status']==='retracted','Old 30% declaration remains public.');
$projection=Repository::public_specifications($p->get_id());$sum=0;
foreach($m['values'] as $metric=>$value){
 $c=Repository::find((int)($state['ids'][$metric]??0));
 x65r_require($c['status']==='published' && $c['source_type']==='supplier_report' && $c['size_scope']===$m['sample'] && (float)$projection['groups']['composition']['metrics'][$metric]['value']===(float)$value,'Scoped composition projection mismatch.');
 $sum+=$value;
}
x65r_require(abs($sum-100)<0.001,'Report composition total mismatch.');
foreach($p->get_children() as $vid){$spec=Repository::public_specifications($p->get_id(),$vid);foreach($m['values'] as $metric=>$value)x65r_require($spec['groups']['composition']['metrics'][$metric]['value']===null,'Sample report improperly inherited by variant.');}
x65r_require(x65r_preserved($p,array_keys($m['values']))===$before,'Commerce/copy/other claims changed.');
WP_CLI::success('X65: 30% retracted; five supplier-report values total 100%, restricted to tested sole sample, not inherited by variants. Commerce/copy/images preserved.');
