<?php
/** Local-only disposable product fixtures; never run on production. */
use ParameterCommerce\Wear_Profile as W;
use ParameterCommerce\Repository as R;
use ParameterCommerce\Catalog as C;
use ParameterCommerce\Comparison;
use ParameterCommerce\Public_Specifications as P;
use ParameterCommerce\Metrics;
if (!defined('WP_CLI') || !WP_CLI || wp_get_environment_type()!=='local' || !in_array(DB_NAME,['sockspecs','sockspecs_cn'],true)) throw new RuntimeException('Local only');
$staff=get_users(['role'=>'administrator','number'=>1])[0];wp_set_current_user($staff->ID);
$checks=0;$ids=[];$claims=[];$evidence=[];$before=get_option('woocommerce_coming_soon','no');
$check=function($ok,$why)use(&$checks){if(!$ok)throw new RuntimeException($why);++$checks;};
$make=function($type)use(&$ids){$p=$type==='variable'?new WC_Product_Variable():new WC_Product_Simple();$p->set_name('DEMO ONLY wear test '.uniqid());$p->set_status('publish');if($type!=='variable')$p->set_regular_price('10');$p->save();$ids[]=$p->get_id();return $p;};
$save=function($p,$v,$key,$value,$publish=true)use(&$claims,&$evidence){$id=R::save(['wc_product_id'=>$p->get_id(),'wc_variation_id'=>$v,'metric_key'=>$key,'value'=>$value,'unit'=>'','source_type'=>'supplier_declared','size_scope'=>$v?'DEMO ONLY option '.$v:'DEMO ONLY all listed options','measured_at'=>'2026-10-21','note'=>'DEMO ONLY not actual sock facts','conditions_json'=>'{"applicability":"all_variations","assessment":"DEMO ONLY"}'],['visibility'=>'private','public_title'=>'DEMO ONLY wearing guide','source_org'=>'DEMO ONLY','source_url'=>'https://example.com/private-wear-test']);$claims[]=$id;if($publish){R::transition($id,1,'reviewed');R::transition($id,2,'published');}return $id;};
try{
 update_option('woocommerce_coming_soon','no');
 $check((int)get_option('pc_dictionary_version')===6,'Dictionary upgraded');
 foreach(wc_get_products(['status'=>'publish','type'=>['simple','variable'],'limit'=>-1]) as $real){if(str_starts_with($real->get_sku(),'SS-')){$m=W::metrics($real->get_id());$check(!isset($m['thickness_grade'])&&!isset($m['footwear_fit']),'Real unknowns left blank');}}
 $p=$make('variable');$empty=$make('simple');$a=new WC_Product_Variation();$a->set_parent_id($p->get_id());$a->set_status('publish');$a->set_regular_price('10');$a->save();$ids[]=$a->get_id();$b=new WC_Product_Variation();$b->set_parent_id($p->get_id());$b->set_status('publish');$b->set_regular_price('10');$b->save();$ids[]=$b->get_id();WC_Product_Variable::sync($p->get_id());$p=wc_get_product($p->get_id());
 $check(W::render($empty)==='' && W::render($empty,true)==='','Empty summary/card hidden');
 $draft=$save($empty,0,'thickness_grade','thick',false);$check(W::render($empty)==='','Draft hidden');
 $save($p,0,'thickness_grade','moderate');$save($p,0,'cushion_location','terry-sole');
 $thin=$save($p,$a->get_id(),'thickness_grade','thin');$save($p,$a->get_id(),'footwear_fit','roomier-shoes');
 $save($p,$b->get_id(),'thickness_grade','thick');$save($p,$b->get_id(),'footwear_fit','close-fitting-shoes');
 wp_set_current_user(0);
 $check(W::metrics($p->get_id(),$a->get_id())['thickness_grade']['value']==='thin','Exact option beats parent');
 $check(W::metrics($p->get_id(),$a->get_id())['cushion_location']['value']==='terry-sole','Explicit manufacturer coverage inherits');
 $check(W::metrics($p->get_id(),$b->get_id())['thickness_grade']['value']==='thick','Other option independent');
 $check(W::metrics($p->get_id())['thickness_grade']['value']==='moderate','Parent unchanged');
 $html=W::render($p);$check(str_contains($html,'data-wear-metric="thickness_grade"')&&str_contains($html,'data-wear-metric="footwear_fit"'),'Purchase summary contains data');
 $check(str_contains($html,'DEMO ONLY option '.$a->get_id())&&!str_contains($html,'private-wear-test'),'Scopes preserved, private source not leaked');
 $check(substr_count($html,'class="pc-wear-note"')===6,'Parent and child notes all appear in summary');
 $check(!str_contains(W::render($p,true),'class="pc-wear-note"'),'Compact cards do not repeat notes');
 $check(str_contains($html,'厚薄为相对判断')&&str_contains($html,'鞋型搭配仅供参考'),'Only present guidance gets its explanation');

 $check(str_contains(W::render($p,true),'pc-wear-card')&&!str_contains(W::render($p,true),'data-wear-metric="footwear_fit"'),'Card focuses on thickness');
 $check(str_contains(P::render($p->get_id(),$a->get_id()),'data-metric="thickness_grade"'),'Detailed table shares record');
 $_GET['pc_compare']=$p->get_id().','.$empty->get_id();$comp=Comparison::render();$check(str_contains($comp,'data-metric="thickness_grade"')&&str_contains($comp,'data-metric="footwear_fit"'),'Compare includes exact option guidance');$_GET=[];
 $check(W::matches($p->get_id(),$a->get_id(),['thickness_grade'=>'thin','footwear_fit'=>'roomier-shoes']),'Combined correct choice');
 $check(!W::matches($p->get_id(),$a->get_id(),['thickness_grade'=>'thin','footwear_fit'=>'close-fitting-shoes']),'No sibling mixing');
 $query=C::search(['pc_thickness_grade'=>'thin','pc_footwear_fit'=>'close-fitting-shoes']);$check(!in_array($a->get_id(),array_map(fn($i)=>$i['product']->get_id(),$query['items']),true),'Catalog rejects sibling mix');
 $query=C::search(['pc_thickness_grade'=>'thin','pc_footwear_fit'=>'roomier-shoes']);$check(in_array($a->get_id(),array_map(fn($i)=>$i['product']->get_id(),$query['items']),true),'Catalog includes correct option');
 $query=C::search(['pc_thickness_grade'=>'moderate']);$check(!in_array($a->get_id(),array_map(fn($i)=>$i['product']->get_id(),$query['items']),true),'Parent cannot override exact choice in filter');
 foreach(['premium','<script>','0'] as $value){$failed=false;try{C::filters(['pc_thickness_grade'=>$value]);}catch(ParameterCommerce\Failure $e){$failed=true;}$check($failed,'Reject invalid enum');}
 $failed=false;try{C::filters(['pc_footwear_fit'=>['roomier-shoes']]);}catch(ParameterCommerce\Failure $e){$failed=true;}$check($failed,'Reject array input');
 $check(!W::matches($empty->get_id(),0,['cushion_location'=>'no-terry']),'Unknown is not no-terry');
 ob_start();W::filter_controls(C::filters([]));$controls=ob_get_clean();foreach(W::KEYS as $k)$check(str_contains($controls,'name="pc_'.$k.'"'),'Filter control '.$k);
 wp_set_current_user($staff->ID);R::transition($thin,3,'retracted');wp_set_current_user(0);$check(W::metrics($p->get_id(),$a->get_id())['thickness_grade']['value']==='moderate','Retraction returns explicit model declaration');
 update_option('woocommerce_coming_soon','yes');$check(W::render($p)==='','Coming soon preserved');update_option('woocommerce_coming_soon','no');
 $empty->set_status('draft');$empty->save();$check(W::render($empty)==='','Draft product hidden');
 WP_CLI::success($checks.' wearing profile checks passed.');
}finally{
 wp_set_current_user($staff->ID);update_option('woocommerce_coming_soon',$before);
 global $wpdb;
 foreach($ids as $id){$ev=$wpdb->get_col($wpdb->prepare("SELECT evidence_id FROM {$wpdb->prefix}pc_claims WHERE wc_product_id=%d",$id));$evidence=array_merge($evidence,$ev);$wpdb->delete($wpdb->prefix.'pc_claims',['wc_product_id'=>$id]);}
 foreach(array_unique($evidence) as $id)$wpdb->delete($wpdb->prefix.'pc_evidence',['id'=>$id]);
 foreach(array_reverse($ids) as $id)wp_delete_post($id,true);
 $_GET=[];
}
