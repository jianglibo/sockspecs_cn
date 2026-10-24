<?php
/** Real local WordPress integration tests, fixtures deleted in finally. */
if (!defined('WP_CLI') || !WP_CLI || wp_get_environment_type()!=='local' || DB_NAME!=='sockspecs_cn') throw new RuntimeException('Local only.');
use ParameterCommerce\Shape_Editor as E;
use ParameterCommerce\Size_Shape as S;
use ParameterCommerce\Repository as R;
use ParameterCommerce\Failure;
function sh_ok($ok,$msg){if(!$ok)throw new RuntimeException($msg);echo "PASS $msg\n";}
function sh_form($p){$g=E::groups($p);$rows=[];foreach($g as $k=>$r)$rows[$k]=$r['values']+['confirm'=>'1'];return ['pc_shape_nonce'=>wp_create_nonce('pc_shape_edit_'.$p->get_id()),'pc_shape_token'=>E::token($p->get_id(),$g),'pc_shape_rows'=>$rows,'pc_shape_source'=>'platform_measured','pc_shape_date'=>gmdate('Y-m-d'),'pc_shape_count'=>'1','pc_shape_note'=>'FICTIONAL test: white sample; ruler and scale.','pc_shape_protocol'=>'1'];}
function sh_reject($p,$form,$msg){try{E::apply($p,$form);}catch(Failure $e){sh_ok(true,$msg);return;}throw new RuntimeException('Did not reject '.$msg);}
require_once WC_ABSPATH.'includes/admin/wc-meta-box-functions.php';
require_once WC_ABSPATH.'includes/admin/class-wc-admin-meta-boxes.php';
$uid=get_current_user_id();$p=new WC_Product_Variable();$p->set_name('FICTIONAL unified shape test');$p->set_status('draft');$attrs=[];foreach(['Color'=>['白','黑'],'Size'=>['M','L'],'Cuff-height'=>['低筒','高筒']] as $n=>$vals){$a=new WC_Product_Attribute();$a->set_name($n);$a->set_options($vals);$a->set_variation(true);$attrs[]=$a;}$p->set_attributes($attrs);$p->save();$children=[];
try{
 foreach(['M','L'] as $size)foreach(['低筒','高筒'] as $cuff)foreach(['白','黑'] as $color){$v=new WC_Product_Variation();$v->set_parent_id($p->get_id());$v->set_status('publish');$v->set_attributes(['color'=>$color,'size'=>$size,'cuff-height'=>$cuff]);$v->set_manage_stock(true);$v->set_stock_quantity(7);$v->set_regular_price('12.34');$v->save();$children[]=$v->get_id();}
 WC_Product_Variable::sync($p->get_id());$p=wc_get_product($p->get_id());$before=[];foreach($children as $id)$before[$id]=wc_get_product($id)->get_data();
 $groups=E::groups($p);sh_ok(count($groups)===4 && count(reset($groups)['members'])===2,'size and cuff groups separate; two colors per group');
 $form=sh_form($p);$keys=array_keys($form['pc_shape_rows']);$form['pc_shape_rows'][$keys[0]]['leg_height_mm']='12.5';$form['pc_shape_rows'][$keys[0]]['foot_length_mm']='18';$form['pc_shape_rows'][$keys[0]]['pair_weight_g']='52';
 $form['pc_shape_note']='';
 $bad=$form;$bad['pc_shape_source']='';sh_reject($p,$bad,'source remains required with optional note');
 $bad=$form;$bad['pc_shape_date']='';sh_reject($p,$bad,'date remains required with optional note');
 $bad=$form;unset($bad['pc_shape_protocol']);sh_reject($p,$bad,'unconfirmed measurement method rejected');
 $bad=$form;$bad['pc_shape_nonce']='bad';sh_reject($p,$bad,'invalid nonce rejected');
 $bad=$form;unset($bad['pc_shape_rows'][$keys[0]]['confirm']);sh_reject($p,$bad,'unconfirmed color sharing rejected');
 $bad=$form;$bad['pc_shape_rows'][$keys[1]]['foot_length_mm']='-1';sh_reject($p,$bad,'one invalid field rejects complete batch');
 sh_ok(!R::listing($p->get_id(),1),'invalid batch left zero claims');
 sh_ok(E::apply($p,$form)===6,'empty note allows publishing three measurements on two colors');
 sh_reject($p,$form,'stale form rejected after publication');
 $g=E::groups($p);$ids=array_keys($g[$keys[0]]['members']);
 foreach($ids as $id){$c=E::records($p->get_id(),$id);sh_ok((float)$c['leg_height_mm']['value_num']===125.0 && (float)$c['foot_length_mm']['value_num']===180.0 && (float)$c['pair_weight_g']['value_num']===52.0,'cm converted to dictionary mm, grams retained');}
 $dimensions=S::dimensions($p);sh_ok(count($dimensions['sizes'])===1 && $dimensions['leg_cm']==='12.5' && $dimensions['confirmed'],'diagram uses measured leg, not catalog height');
 $weight=S::weight_summary($p);sh_ok(str_contains($weight,'52')&&!str_contains($weight,'白, 黑'),'weight does not list identical colors because other sizes are missing');
 $normal=sh_form($p);$normal['pc_shape_source']='';$normal['pc_shape_note']='';sh_ok(E::apply($p,$normal)===0,'ordinary product save does not recreate claims');
 $change=sh_form($p);$change['pc_shape_rows'][$keys[0]]['pair_weight_g']='53';sh_ok(E::apply($p,$change)===2,'changed weight revises only weight on the two colors');
 $c=E::records($p->get_id(),$ids[0])['pair_weight_g'];$n=R::listing($p->get_id(),1);
 try{R::atomic_scopes($p->get_id(),$ids,static function()use($c){R::transition($c['id'],$c['revision'],'retracted');throw new RuntimeException('forced rollback');});}catch(RuntimeException $e){sh_ok($e->getMessage()==='forced rollback','late batch error propagated');}
 sh_ok(R::find($c['id'])['status']==='published' && count(R::listing($p->get_id(),1))===count($n),'transaction rolled back earlier changes');
 $blank=sh_form($p);$blank['pc_shape_rows'][$keys[0]]['pair_weight_g']='';sh_ok(E::apply($p,$blank)===0,'blank input preserves existing value');
 $g=E::groups($p);$m=$g[$keys[0]]['members'][$ids[0]]['records']['pair_weight_g'];$input=$m;$input['value']='54';R::edit($input,$m['evidence'],$m['id'],$m['revision'],true,$m['id'],$m['revision']);
 $g=E::groups($p);sh_ok($g[$keys[0]]['mixed']['pair_weight_g'] && $g[$keys[0]]['values']['pair_weight_g']==='','existing color weight differences are not silently collapsed');
 $weight=S::weight_summary($p);sh_ok(str_contains($weight,'54')&&str_contains($weight,'53')&&str_contains($weight,'白')&&str_contains($weight,'黑'),'different color weights remain labeled');
 $form=sh_form($p);$form['pc_shape_rows'][$keys[1]]['leg_height_mm']='9';$form['pc_shape_rows'][$keys[1]]['foot_length_mm']='17';$form['pc_shape_source']='supplier_declared';$form['pc_shape_note']='';sh_ok(E::apply($p,$form)===4,'supplier dimensions accept empty notes and retain catalog-height semantics');
 $d=S::dimensions($p);sh_ok(count($d['sizes'])===2,'partial size groups do not hide other valid dimensions');
 $GLOBALS['product_object']=$p;ob_start();E::settings();$html=ob_get_clean();sh_ok(str_contains($html,'每双净重')&&str_contains($html,'pc_shape_rows')&&!str_contains($html,'name="_pc_shape_leg_cm"'),'single native editor replaces legacy fields');
 $invalid=sh_form($p);$invalid['pc_shape_rows'][$keys[0]]['pair_weight_g']='57';$invalid['pc_shape_source']='';$_POST=$invalid;E::save($p);$_POST=[];ob_start();E::settings();$redisplay=ob_get_clean();sh_ok(str_contains($redisplay,'value="57"'),'validation failure retains entered values for correction');
 $native=sh_form($p);$native['pc_shape_rows'][$keys[0]]['pair_weight_g']='55';unset($native['pc_shape_note']);$_POST=$native;do_action('woocommerce_admin_process_product_object',$p);$_POST=[];sh_ok((float)E::records($p->get_id(),$ids[0])['pair_weight_g']['value_num']===55.0,'native product save publishes with absent optional note');
 $missing=E::records($p->get_id(),$ids[1])['pair_weight_g'];R::transition($missing['id'],$missing['revision'],'retracted');
 $weight=S::weight_summary($p);sh_ok(str_contains($weight,'55')&&!str_contains($weight,'白')&&!str_contains($weight,'黑'),'missing color measurement does not add a color list to size weight');
 sh_ok(empty(E::records($p->get_id(),$ids[1])['pair_weight_g']),'display grouping never invents a missing measurement');
 $form=sh_form($p);wp_set_current_user(0);sh_reject($p,$form,'unauthorized editing rejected');wp_set_current_user($uid);
 foreach($children as $id){$v=wc_get_product($id);sh_ok($v->get_regular_price()==='12.34' && $v->get_stock_quantity()===7,'commerce remains unchanged');}
}finally{
 wp_set_current_user($uid);global $wpdb;$claims=$wpdb->get_results($wpdb->prepare("SELECT id,evidence_id FROM {$wpdb->prefix}pc_claims WHERE wc_product_id=%d",$p->get_id()));foreach($claims as $c){$wpdb->delete($wpdb->prefix.'pc_claim_audit',['claim_id'=>$c->id]);$wpdb->delete($wpdb->prefix.'pc_claims',['id'=>$c->id]);$wpdb->delete($wpdb->prefix.'pc_evidence',['id'=>$c->evidence_id]);}foreach($children as $id){$v=wc_get_product($id);if($v)$v->delete(true);}$p->delete(true);wp_cache_flush();
}
