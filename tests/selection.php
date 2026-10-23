<?php
/** Local disposable fixtures: selection quantities, prices, native stock/cart and draft concurrency. */
use SockSpecsCN\Commerce\SelectionCart as S;
use SockSpecsCN\Commerce\SelectionDraft as D;
use ParameterCommerce\Pack_Builder as B;
if(!defined('WP_CLI')||!WP_CLI||wp_get_environment_type()!=='local'||DB_NAME!=='sockspecs_cn')throw new RuntimeException('Local only');
function sel_check($ok,$label){if(!$ok)throw new RuntimeException($label);}
function sel_reject($fn,$label){try{$fn();}catch(Exception $e){return;}throw new RuntimeException('Expected rejection: '.$label);}
$ids=[];$owners=[];$users=[];$oldUser=get_current_user_id();$oldCookie=$_COOKIE['sscn_selection']??null;
try{
 D::install();wp_set_current_user(0);$_COOKIE['sscn_selection']=bin2hex(random_bytes(32));$owners[]=D::owner();
 wc_load_cart();WC()->cart->empty_cart();$vs=[];$parents=[];
 foreach(['39.00','59.00'] as $price){
  $p=new WC_Product_Variable();$p->set_name('Disposable selection '.$price);$p->set_status('publish');$p->set_tax_status('none');
  $a=new WC_Product_Attribute();$a->set_name('color');$a->set_options(['red','blue']);$a->set_variation(true);$p->set_attributes([$a]);
  $p->update_meta_data('_pc_pack_builder','yes');$p->update_meta_data('_pc_pack_prices',['currency'=>'CNY',4=>$price,6=>'77.00',12=>'144.00']);$p->save();$ids[]=$p->get_id();$parents[]=$p->get_id();
  foreach(['red','blue'] as $color){$v=new WC_Product_Variation();$v->set_parent_id($p->get_id());$v->set_status('publish');$v->set_attributes(['color'=>$color]);$v->set_regular_price($price==='39.00'?'9.75':'14.75');$v->set_tax_status('none');$v->set_manage_stock(true);$v->set_stock_quantity(100);$v->save();$ids[]=$v->get_id();$vs[]=$v->get_id();}
  WC_Product_Variable::sync($p->get_id());
 }
 foreach([4,5,7,13,25] as $qty){
  WC()->cart->empty_cart();$items=[$vs[0]=>1,$vs[2]=>$qty-1];$q=S::quote(4,$items,true);$want=975+1475*($qty-1);
  sel_check($q['size']===$qty&&$q['total']===$want,'Arbitrary quantity '.$qty.' uses sum of unit prices');
  $group=wp_generate_uuid4();S::add($q,$group);sel_check(B::cart_error(WC()->cart)==='','Native selection valid');
  S::add($q,$group);sel_check(WC()->cart->get_cart_contents_count()===$qty,'Retry does not duplicate');
  for($i=0;$i<2;$i++){WC()->cart->calculate_totals();sel_check((int)round(WC()->cart->get_cart_contents_total()*100)===$want,'Repeated total unchanged');}
  foreach(WC()->cart->get_cart() as $key=>$line){$item=new WC_Order_Item_Product();B::order_item($item,$key,$line);sel_check($item->get_meta('_pc_pack')['items']===$items,'Order retains exact native variations');}
 }
 $v=wc_get_product($vs[1]);$v->set_regular_price('10.01');$v->save();sel_check(S::quote(4,[$vs[0]=>1,$vs[1]=>3],true)['total']===3978,'Different variant unit prices sum exactly');
 sel_reject(fn()=>S::quote(4,[$vs[0]=>3],true),'Fewer than four');
 foreach([[$vs[0]=>1.5],[$vs[0]=>-1],['bad'=>4],[$vs[0]=>'4e0']] as $bad)sel_reject(fn()=>S::quote(4,$bad,true),'Malformed quantities');
 sel_check(S::quote(4,[$vs[0]=>1])['total']===975,'Partial draft quoted');
 $key=array_key_first(WC()->cart->get_cart());$saved=WC()->cart->cart_contents[$key];WC()->cart->cart_contents[$key]['quantity']++;
 sel_check(B::cart_error(WC()->cart)!=='','Tampered quantity rejected');WC()->cart->cart_contents[$key]=$saved;
 WC()->cart->remove_cart_item($key);sel_check(WC()->cart->is_empty(),'Group removal atomic');WC()->cart->restore_cart_item($key);sel_check(B::cart_error(WC()->cart)==='','Group restore valid');
 $v=wc_get_product($vs[0]);$v->set_regular_price('10');$v->save();sel_check(B::cart_error(WC()->cart)!=='','Price changes require reconfirmation');$v->set_regular_price('9.75');$v->save();
 WC()->cart->empty_cart();$q=S::quote(4,[$vs[0]=>99],true);S::add($q,wp_generate_uuid4());sel_reject(fn()=>S::add(S::quote(4,[$vs[0]=>4],true),wp_generate_uuid4()),'Aggregate cart stock');
 WC()->cart->empty_cart();
 $fail=static fn($ok,$id)=>$id===$parents[1]?false:$ok;add_filter('woocommerce_add_to_cart_validation',$fail,99,2);
 sel_reject(fn()=>S::add(S::quote(4,[$vs[0]=>2,$vs[2]=>2],true),wp_generate_uuid4()),'Rollback after second product refused');remove_filter('woocommerce_add_to_cart_validation',$fail,99);
 sel_check(WC()->cart->is_empty()&&!WC()->cart->removed_cart_contents,'No partial or undoable cart after rollback');
 sel_reject(fn()=>B::guard_add([], $parents[0],$vs[0],1),'Native bypass blocked');
 // Draft quantity is a total, not a size selector; revisions prevent silent stale overwrites.
 $state=D::operate(['op'=>'load']);$first=$state['revision'];
 $state=D::operate(['op'=>'add','revision'=>$first,'items'=>[$vs[0]=>2,$vs[2]=>3]]);
 sel_check($state['count']===5&&$state['minimum']===4&&$state['total']===63.75,'Saved five-pair draft');
 sel_reject(fn()=>D::operate(['op'=>'clear','revision'=>$first]),'Old tab cannot clear newer draft');
 sel_check(D::operate(['op'=>'load'])['count']===5,'Draft restored');
 sel_reject(fn()=>\SockSpecsCN\Commerce\selection_request(['op'=>'clear','revision'=>$state['revision'],'csrf'=>'bad']),'CSRF rejected');
 $token=$_COOKIE['sscn_selection'];$_COOKIE['sscn_selection']=bin2hex(random_bytes(32));$owners[]=D::owner();sel_check(D::operate(['op'=>'load'])['count']===0,'Guests isolated');$_COOKIE['sscn_selection']=$token;
 // A new account acquires the anonymous draft without merging incompatible lists.
 $user=wp_insert_user(['user_login'=>'selection-test-'.wp_generate_password(10,false),'user_pass'=>wp_generate_password(30),'role'=>'customer']);if(is_wp_error($user))throw new RuntimeException($user->get_error_message());$users[]=$user;
 wp_set_current_user($user);$owners[]=D::owner();$state=D::operate(['op'=>'load']);sel_check($state['count']===5&&!$state['conflict'],'Guest draft carried into empty account');
 wp_set_current_user(0);$guest=D::operate(['op'=>'load']);sel_check($guest['count']===0,'Carried guest list cleared');$guest=D::operate(['op'=>'add','revision'=>$guest['revision'],'items'=>[$vs[1]=>4]]);
 wp_set_current_user($user);$state=D::operate(['op'=>'load']);sel_check($state['conflict']['count']===4&&$state['count']===5,'Conflicting drafts both preserved');
 sel_reject(fn()=>D::operate(['op'=>'clear','revision'=>$state['revision']]),'No overwrite during login conflict');
 $state=D::operate(['op'=>'resolve','revision'=>$state['revision'],'guest_revision'=>$guest['revision'],'choice'=>'account']);sel_check(!$state['conflict']&&$state['count']===5,'Explicit conflict resolution');
 WC()->cart->empty_cart();WC()->cart->set_session();WC()->session->save_data();
 $state=D::operate(['op'=>'commit','revision'=>$state['revision'],'signature'=>$state['signature']]);sel_check($state['state']==='committed'&&WC()->cart->get_cart_contents_count()===5,'Draft committed');
 $state=D::operate(['op'=>'commit','revision'=>$state['revision'],'signature'=>$state['signature']]);sel_check(WC()->cart->get_cart_contents_count()===5,'Commit repeated safely');
 $group=reset(WC()->cart->cart_contents)['pc_pack']['id'];$state=D::operate(['op'=>'edit_cart','revision'=>$state['revision'],'group'=>$group]);sel_check($state['count']===5&&WC()->cart->is_empty(),'Edit returns choices without duplicating cart');
 $state=D::operate(['op'=>'save','revision'=>$state['revision'],'items'=>[$vs[0]=>3]]);sel_reject(fn()=>D::operate(['op'=>'commit','revision'=>$state['revision'],'signature'=>$state['signature']]),'Small draft cannot checkout');
 $v=wc_get_product($vs[0]);$v->set_stock_quantity(0);$v->save();sel_reject(fn()=>S::quote(4,[$vs[0]=>4],true),'Unavailable stock excluded');
 WP_CLI::success('Selection: arbitrary totals >=4, fixed-unit arithmetic, stock, tampering, retry/rollback, native order metadata, draft restoration/isolation/concurrency/login and cart editing passed.');
}finally{
 if(WC()->cart){WC()->cart->empty_cart();WC()->cart->set_session();WC()->session->save_data();}
 global $wpdb;foreach($owners as $owner)$wpdb->delete(D::table(),['owner_key'=>$owner]);
 require_once ABSPATH.'wp-admin/includes/user.php';foreach($users as $id)wp_delete_user($id);
 foreach(array_reverse($ids) as $id)wp_delete_post($id,true);
 wp_set_current_user($oldUser);if($oldCookie===null)unset($_COOKIE['sscn_selection']);else $_COOKIE['sscn_selection']=$oldCookie;
}
