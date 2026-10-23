<?php
/** Local-only direct edit integration; temporary product and no external effects. */
use ParameterCommerce\Repository as R;
if(!defined('WP_CLI')||!WP_CLI||DB_NAME!=='sockspecs_cn'||wp_get_environment_type()!=='local')throw new RuntimeException('Local domestic only');
$staff=get_users(['role'=>'administrator','number'=>1])[0];wp_set_current_user($staff->ID);
$n=0;$check=function($ok,$msg)use(&$n){if(!$ok)throw new RuntimeException($msg);++$n;};
$reject=function($fn)use($check){try{$fn();}catch(ParameterCommerce\Failure $e){$check(true,'Rejected');return;}$check(false,'Unexpected success');};
$p=new WC_Product_Simple();$p->set_name('DEMO ONLY direct edit '.uniqid());$p->set_status('publish');$p->set_regular_price('10');$p->save();
$base=['wc_product_id'=>$p->get_id(),'wc_variation_id'=>0,'metric_key'=>'cushion_location','value'=>'terry-sole','unit'=>'','source_type'=>'platform_observed','measured_at'=>'2026-10-21','note'=>'DEMO ONLY original'];$ev=['visibility'=>'private'];
try{
 $old=R::edit($base,$ev,0,0,true);$r=R::find($old);$check($r['status']==='published','New claim publishes in one operation');
 $_GET=['claim_id'=>$old];ob_start();ParameterCommerce\Admin::page();$html=ob_get_clean();$_GET=[];
 $check(str_contains($html,'name="pc_operation" value="edit"')&&str_contains($html,'name="value" value="terry-sole"'),'Published record has populated edit form');
 $check(str_contains($html,'保存并发布')&&str_contains($html,'value="draft"'),'Clear actions');
 $check(str_contains($html,'name="current_id" value="'.$old.'"'),'Form pins current value');
 global $wpdb;$snapshot=function()use($wpdb,$p){return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}pc_claims WHERE wc_product_id=%d ORDER BY id",$p->get_id()),ARRAY_A);};$before=$snapshot();$audits=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}pc_claim_audit");$evidences=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}pc_evidence");
 // Fails after automatic fork/save, during review: entire operation must roll back.
 $reject(fn()=>R::edit(array_replace($base,['note'=>'']),$ev,$old,(int)$r['revision'],true,$old,(int)$r['revision']));
 $check($snapshot()===$before,'Failed publish rolls back claim fork and changes');
 $check((int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}pc_evidence")===$evidences&&(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}pc_claim_audit")===$audits,'Failed publish rolls back evidence and audits');
 $reject(fn()=>R::edit($base,$ev,$old,(int)$r['revision']-1,true,$old,(int)$r['revision']));
 $new=R::edit(array_replace($base,['value'=>'partial-terry','note'=>'DEMO ONLY forefoot and heel']),$ev,$old,(int)$r['revision'],true,$old,(int)$r['revision']);
 $check($new!==$old&&R::find($old)['status']==='retracted'&&R::find($new)['status']==='published','Published edit creates history and replaces live atomically');
 $check(R::find($old)['value_enum']==='terry-sole','Old content preserved');
 $check(R::specifications($p->get_id(),0)['groups']['construction']['metrics']['cushion_location']['value']==='partial-terry','Canonical public consumers get new value');
 $r=R::find($new);$draft=R::edit(array_replace($base,['value'=>'full-terry']),$ev,$new,(int)$r['revision'],false,$new,(int)$r['revision']);
 $check(R::find($draft)['status']==='draft'&&R::find($new)['status']==='published','Save draft leaves public value intact');
 $dr=R::find($draft);R::transition($draft,(int)$dr['revision'],'reviewed');$dr=R::find($draft);
 $_GET=['claim_id'=>$draft];ob_start();ParameterCommerce\Admin::page();$html=ob_get_clean();$_GET=[];$check(str_contains($html,'name="value" value="full-terry"'),'Reviewed record has edit form');
 $done=R::edit(array_replace($base,['value'=>'no-terry']),$ev,$draft,(int)$dr['revision'],true,$new,(int)$r['revision']);
 $check($done===$draft&&R::find($done)['status']==='published'&&R::find($new)['status']==='retracted','Reviewed edit saves and publishes without manual draft step');
 $reject(fn()=>R::edit($base,$ev,$new,(int)$r['revision'],true,$new,(int)$r['revision']));
 $dr=R::find($done);$reject(fn()=>R::edit(array_replace($base,['metric_key'=>'thickness_grade','value'=>'thin']),$ev,$done,(int)$dr['revision'],true,$done,(int)$dr['revision']));
 $unlinked=R::save($base,$ev);$reject(fn()=>R::edit($base,$ev,$unlinked,1,true,$new,(int)$r['revision']));
 $reject(fn()=>R::edit($base,$ev,0,0,true));
 wp_set_current_user(0);$reject(fn()=>R::edit($base,$ev,0,0,false));wp_set_current_user($staff->ID);
 // Error redisplay retains original optimistic tokens and unsaved text.
 $submitted=array_replace($base,['claim_id'=>$done,'revision'=>1,'current_id'=>$old,'current_revision'=>1,'value'=>'full-terry','evidence'=>$ev]);ob_start();ParameterCommerce\Admin::page(['revision'=>'DEMO conflict'],$submitted);$html=ob_get_clean();
 $check(str_contains($html,'name="revision" value="1"')&&str_contains($html,'name="current_id" value="'.$old.'"')&&str_contains($html,'name="value" value="full-terry"'),'Conflict redisplay preserves unsaved values and old tokens');
 WP_CLI::success($n.' direct-edit checks passed.');
}finally{
 wp_set_current_user($staff->ID);$_GET=[];global $wpdb;$claims=$snapshot();foreach($claims as $c){$wpdb->delete($wpdb->prefix.'pc_claim_audit',['claim_id'=>$c['id']]);$wpdb->delete($wpdb->prefix.'pc_evidence',['id'=>$c['evidence_id']]);}$wpdb->delete($wpdb->prefix.'pc_claims',['wc_product_id'=>$p->get_id()]);wp_delete_post($p->get_id(),true);
}
