<?php
/** Local-only exact-scope replacement regression. No production fixtures. */
use ParameterCommerce\Repository as R;
if(!defined('WP_CLI')||!WP_CLI||DB_NAME!=='sockspecs_cn'||wp_get_environment_type()!=='local')throw new RuntimeException('Local domestic only');
$staff=get_users(['role'=>'administrator','number'=>1])[0];wp_set_current_user($staff->ID);
$n=0;$check=function($ok,$msg)use(&$n){if(!$ok)throw new RuntimeException($msg);++$n;};
$reject=function($fn)use($check){try{$fn();}catch(ParameterCommerce\Failure $e){$check(true,'Rejected');return;}$check(false,'Unexpected success');};
$p=new WC_Product_Variable();$p->set_name('DEMO ONLY claim revisions '.uniqid());$p->set_status('publish');$p->save();
$v=new WC_Product_Variation();$v->set_parent_id($p->get_id());$v->set_status('publish');$v->save();
$base=['wc_product_id'=>$p->get_id(),'wc_variation_id'=>0,'metric_key'=>'cushion_location','value'=>'terry-sole','unit'=>'','source_type'=>'platform_observed','measured_at'=>'2026-10-21','note'=>'DEMO ONLY observed sole'];$ev=['visibility'=>'private'];
$publish=function($id){$r=R::find($id);if($r['status']==='draft'){R::transition($id,(int)$r['revision'],'reviewed');$r=R::find($id);}R::transition($id,(int)$r['revision'],'published');};
try{
 // Create the legacy unlinked draft before any active value exists.
 $legacy=R::save(array_replace($base,['value'=>'partial-terry']),$ev);R::transition($legacy,1,'reviewed');
 $old=R::save($base,$ev);$publish($old);
 $new=R::save(array_replace($base,['value'=>'partial-terry']),$ev);$row=R::find($new);
 $check((int)$row['supersedes_id']===$old && (int)$row['supersedes_revision']===3,'Duplicate automatically linked');
 $check(R::find($old)['status']==='published','Saving does not publish');
 $other=R::save(array_replace($base,['wc_variation_id'=>$v->get_id()]),$ev);$check(!R::find($other)['supersedes_id'],'Variation is separate scope');$publish($other);
 $thick=R::save(array_replace($base,['metric_key'=>'thickness_grade','value'=>'thin']),$ev);$check(!R::find($thick)['supersedes_id'],'Other metric is separate');$publish($thick);
 // Legacy publication requires an explicit matching target, never silently replaces.
 $reject(fn()=>R::transition($legacy,2,'published'));
 $reject(fn()=>R::transition($legacy,2,'published',$other,3));
 $reject(fn()=>R::transition($legacy,2,'published',$old,2));
 $check(R::find($old)['status']==='published' && R::find($legacy)['status']==='reviewed','Conflict rollback leaves both records intact');
 $_GET['claim_id']=$legacy;ob_start();ParameterCommerce\Admin::page();$html=ob_get_clean();unset($_GET['claim_id']);
 $check(str_contains($html,'发布并替换当前值')&&str_contains($html,'局部毛圈')&&str_contains($html,'毛圈袜底'),'Editor shows replacement and before/after');
 $check(str_contains($html,'name="replace_id" value="'.$old.'"')&&str_contains($html,'name="replace_revision" value="3"'),'Form pins displayed target');
 R::transition($legacy,2,'published',$old,3);
 $check(R::find($old)['status']==='retracted'&&R::find($legacy)['status']==='published','Legacy replaces atomically');
 $check((int)R::find($legacy)['supersedes_id']===$old,'Legacy link stored');
 $history=R::history($legacy);$before=json_decode($history[0]['before_json'],true);$check($before['supersedes_id']===null,'Audit before image preserved');
 $check(R::history($old)[0]['action']==='superseded','Old record retains replacement history');
 $publishNew=function()use($new,$publish){$publish($new);};$reject($publishNew);
 $check(R::find($legacy)['status']==='published','Stale draft cannot replace newer value');
 R::transition($new,2,'draft');R::save(array_replace($base,['value'=>'full-terry']),$ev,$new,3);
 $check((int)R::find($new)['supersedes_id']===$old,'Saving stale draft never silently rebases');
 $latest=R::save(array_replace($base,['value'=>'full-terry']),$ev);$publish($latest);
 $check(R::find($legacy)['status']==='retracted'&&R::find($latest)['status']==='published','New duplicate follows normal replacement');
 $check(R::find($other)['status']==='published'&&R::find($thick)['status']==='published','Other scopes untouched');
 global $wpdb;$count=$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}pc_claims WHERE published_slot=%s",$p->get_id().':0:cushion_location'));$check((int)$count===1,'Exactly one live value');
 wp_set_current_user(0);$reject(fn()=>R::transition($latest,3,'retracted'));wp_set_current_user($staff->ID);
 WP_CLI::success($n.' claim revision checks passed.');
}finally{
 wp_set_current_user($staff->ID);global $wpdb;$claims=$wpdb->get_results($wpdb->prepare("SELECT id,evidence_id FROM {$wpdb->prefix}pc_claims WHERE wc_product_id=%d",$p->get_id()),ARRAY_A);foreach($claims as $c){$wpdb->delete($wpdb->prefix.'pc_claim_audit',['claim_id'=>$c['id']]);$wpdb->delete($wpdb->prefix.'pc_evidence',['id'=>$c['evidence_id']]);}$wpdb->delete($wpdb->prefix.'pc_claims',['wc_product_id'=>$p->get_id()]);wp_delete_post($v->get_id(),true);wp_delete_post($p->get_id(),true);
}
