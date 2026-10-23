<?php
/** Domestic local-only observation workflow regression with disposable fixtures. */
use ParameterCommerce\Validator as V;
use ParameterCommerce\Repository as R;
use ParameterCommerce\Wear_Profile as W;
if(!defined('WP_CLI')||!WP_CLI||DB_NAME!=='sockspecs_cn'||wp_get_environment_type()!=='local')throw new RuntimeException('Local domestic only');
$staff=get_users(['role'=>'administrator','number'=>1])[0];wp_set_current_user($staff->ID);
$n=0;$check=function($ok,$msg)use(&$n){if(!$ok)throw new RuntimeException($msg);++$n;};
$reject=function($fn,$field)use($check){try{$fn();}catch(ParameterCommerce\Failure $e){$check(isset($e->fields[$field]),'Expected field '.$field);return;}$check(false,'Accepted invalid '.$field);};
$p=new WC_Product_Variable();$p->set_name('DEMO ONLY sample observation '.uniqid());$p->set_status('publish');$p->save();
$v=new WC_Product_Variation();$v->set_parent_id($p->get_id());$v->set_status('publish');$v->set_regular_price('10');$v->save();WC_Product_Variable::sync($p->get_id());
$visibility=get_option('woocommerce_coming_soon','no');update_option('woocommerce_coming_soon','no');
try{
 $base=['wc_product_id'=>$p->get_id(),'wc_variation_id'=>0,'metric_key'=>'cushion_location','value'=>'partial-terry','unit'=>'','source_type'=>'platform_observed','size_scope'=>'','measured_at'=>'2026-10-21','sample_count'=>'','note'=>'DEMO ONLY visually observed terry at forefoot and heel; none at arch.'];
 $e=['visibility'=>'private','public_title'=>'DEMO ONLY visual observation','source_org'=>'DEMO ONLY store'];
 $claim=V::claim($base);$evidence=V::evidence($e);V::publishable($claim,$evidence);$check(true,'No URL, method, version, batch or conditions required');
 V::publishable(array_replace($claim,['size_scope'=>'DEMO ONLY white / M','sample_count'=>2]),$evidence);$check(true,'Optional details still accepted');
 foreach(['thickness_grade'=>'thin','footwear_fit'=>'roomier-shoes'] as $key=>$value){V::publishable(V::claim(array_replace($base,['metric_key'=>$key,'value'=>$value])),$evidence);$check(true,'Allowed '.$key);}
 foreach(['measured_at'=>null,'note'=>''] as $key=>$value)$reject(fn()=>V::publishable(array_replace($claim,[$key=>$value]),$evidence),$key);
 foreach(['cotton_pct'=>['70','%'],'pair_weight_g'=>['28','g'],'air_permeability'=>['100',ParameterCommerce\Metrics::defaults()['air_permeability']['unit']]] as $key=>[$value,$unit])$reject(fn()=>V::claim(array_replace($base,['metric_key'=>$key,'value'=>$value,'unit'=>$unit])),'source_type');
 $reject(fn()=>V::publishable(array_replace($claim,['metric_key'=>'cotton_pct','value_enum'=>null,'value_num'=>'70','unit'=>'%']),$evidence),'source_type');
 $reject(fn()=>V::publishable(V::claim(array_replace($base,['source_type'=>'platform_measured'])),$evidence),'method_code');
 $reject(fn()=>V::publishable(V::claim(array_replace($base,['source_type'=>'third_party_lab'])),$evidence),'method_code');
 $reject(fn()=>V::publishable(V::claim(array_replace($base,['source_type'=>'supplier_declared'])),$evidence),'evidence.source_url');
 $reject(fn()=>V::claim(array_replace($base,['unit'=>'%'])),'unit');
 $id=R::save($base,['visibility'=>'private']);$row=R::find($id);$check($row['evidence']['public_title']!==''&&$row['evidence']['source_org']!=='','Own record metadata identified');
 $check($row['measured_at']===$base['measured_at']&&$row['sample_count']===null&&$row['size_scope']==='','No invented observation facts');
 R::transition($id,1,'reviewed');R::transition($id,2,'published');
 wp_set_current_user(0);$m=W::metrics($p->get_id());$check($m['cushion_location']['value']==='partial-terry','Observation visible through public projection');
 $check($m['cushion_location']['source_label']==='本站样品观察','Honest localized source');
 $check(!isset(W::metrics($p->get_id(),$v->get_id())['cushion_location']),'Observed sample never inherits to variants');
 $html=ParameterCommerce\Public_Specifications::render($p->get_id());$check(str_contains($html,'不代表外部检测报告')&&str_contains($html,'DEMO ONLY visually'),'Observed evidence and notes render');
 $check(W::matches($p->get_id(),0,['cushion_location'=>'partial-terry']),'Observation filter shares projection');
 $summary=W::render(wc_get_product($p->get_id()));$check(str_contains($summary,'class="pc-wear-note"')&&str_contains($summary,$base['note']),'Product summary includes public observation note');
 $check(!str_contains($summary,'<p>'),'Terry alone has no thickness or shoe disclaimer');

 $reject(fn()=>R::save($base,$e),'permission');
 wp_set_current_user($staff->ID);R::transition($id,3,'retracted');wp_set_current_user(0);$check(W::render($p)==='','Retraction removes observation');
 WP_CLI::success($n.' sample-observation checks passed.');
}finally{
 wp_set_current_user($staff->ID);update_option('woocommerce_coming_soon',$visibility);
 global $wpdb;$ev=$wpdb->get_col($wpdb->prepare("SELECT evidence_id FROM {$wpdb->prefix}pc_claims WHERE wc_product_id=%d",$p->get_id()));$wpdb->delete($wpdb->prefix.'pc_claims',['wc_product_id'=>$p->get_id()]);foreach($ev as $id)$wpdb->delete($wpdb->prefix.'pc_evidence',['id'=>$id]);wp_delete_post($v->get_id(),true);wp_delete_post($p->get_id(),true);
}
