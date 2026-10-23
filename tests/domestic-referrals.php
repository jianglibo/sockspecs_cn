<?php
/** DEMO ONLY: referral money lifecycle on a disposable, non-mailing site. */
use ParameterCommerce\Referral_Store as Store;
use ParameterCommerce\Referrals as Program;
use ParameterCommerce\Pack_Builder as Pack;
if (!defined('WP_CLI') || !WP_CLI || DB_NAME !== 'sockspecs_cn' || home_url() !== 'http://127.0.0.1:8089' || wp_get_environment_type() !== 'local') { throw new RuntimeException('Local domestic test only'); }
global $wpdb;
$testUsers=[];$testOrders=[];$testProducts=[];
$testTag='cn-ref-'.bin2hex(random_bytes(5));
$oldOptions=[];foreach(['woocommerce_coming_soon','woocommerce_calc_taxes','woocommerce_currency','sscn_referrals_enabled'] as $key){$oldOptions[$key]=get_option($key);}
add_filter('pre_wp_mail',static fn()=>false,999);
add_filter('pre_http_request',static function(){throw new RuntimeException('No external HTTP in referral tests');},999);
add_action('user_register',static function($id)use(&$testUsers){$testUsers[]=$id;});
add_action('woocommerce_new_order',static function($id)use(&$testOrders){$testOrders[]=$id;});
try {
$checks=0;
$check=function($ok,$message)use(&$checks){++$checks;if(!$ok){throw new RuntimeException($message);}};
$reject=function($fn,$message)use($check){try{$fn();$caught=false;}catch(Throwable $e){$caught=true;}$check($caught,$message);};
$admin=get_users(['role'=>'administrator','number'=>1])[0]->ID;
wp_set_current_user($admin);Store::install();Store::install();update_option('sscn_referrals_enabled','yes');
update_option('woocommerce_coming_soon','no');update_option('woocommerce_calc_taxes','no');update_option('woocommerce_currency','CNY');
WC()->initialize_session();WC()->initialize_cart();
$user=function($name)use($testTag){$name=$testTag.'-'.$name;$id=wp_create_user('demo-ref-'.$name,'DEMO-ONLY-password-2026!','demo-ref-'.$name.'@example.invalid');if(is_wp_error($id))throw new RuntimeException($id->get_error_message());(new WP_User($id))->set_role('customer');return $id;};
$a=$user('advocate');$b=$user('second');$c=$user('spender');
$p=new WC_Product_Simple();$p->set_name('DEMO ONLY referral test item');$p->set_status('publish');$p->set_regular_price('20');$p->set_virtual(true);$p->save();$testProducts[]=$p->get_id();
$cart=function($uid,$email='')use($p){wp_set_current_user($uid);WC()->session->__unset('order_awaiting_payment');WC()->session->__unset('store_api_draft_order');WC()->session->__unset('sscn_referral_code');WC()->cart->empty_cart();WC()->customer=new WC_Customer($uid);WC()->customer->set_billing_email($email?:($uid?get_userdata($uid)->user_email:''));WC()->customer->set_billing_country('CN');WC()->customer->set_shipping_country('CN');WC()->cart->add_to_cart($p->get_id());WC()->cart->calculate_totals();wc_clear_notices();};
$native=function($email,$uid=0){return WC()->checkout()->create_order(['billing_email'=>$email,'customer_id'=>$uid,'payment_method'=>'']);};
$manual=function($email,$referrer,$uid=0,$credit=0)use($p){$o=wc_create_order(['customer_id'=>$uid]);$o->set_billing_email($email);$o->set_currency('CNY');$o->add_product($p,1);$o->calculate_totals();$o->save();Store::reserve($o,$credit,$referrer);return $o;};
$cart(0,($testTag.'-friend-1@example.invalid'));
$check(WC()->cart->apply_coupon(Program::code($a)),'Genuine share code applies');WC()->cart->calculate_totals();
$check(abs((float)WC()->cart->get_total('edit')-15)<.001,'First friend gets exactly five yuan');
$id=$native(($testTag.'-friend-1@example.invalid'));$check(!is_wp_error($id),'Classic native checkout reserves referral');$o=wc_get_order($id);
$check(Store::balance($a)===0,'No reward merely for order creation');
$o->payment_complete();$check(Store::balance($a)===0,'Processing/payment alone does not release reward');
$o->update_status('completed');$check(Store::balance($a)===500,'Completed first order earns five yuan');
Program::changed($id);Program::changed($id);$check(Store::balance($a)===500,'Repeated completion/payment notifications are idempotent');
$check(!Store::first_order(($testTag.'-friend-1@example.invalid'),0),'Guest billing history prevents repeat first orders');
$reject(fn()=>$manual(($testTag.'-friend-1@example.invalid'),$b),'Different referral cannot reuse a previous buyer');
$cart($a);$check(!WC()->cart->apply_coupon(Program::code($a)),'Own account referral blocked');
$reject(fn()=>$manual(get_userdata($a)->user_email,$a),'Guest using referrer email blocked');
$cart(0,($testTag.'-friend-2@example.invalid'));$check(!WC()->cart->apply_coupon(Program::code($a).'bad'),'Forged referral code blocked');
$check(WC()->cart->apply_coupon(Program::code($a)),'First code allowed');$check(!WC()->cart->apply_coupon(Program::code($b)),'Two referrers cannot stack');
$cart($a);$check(WC()->cart->apply_coupon('sscn-credit'),'Logged-in customer can use balance');WC()->cart->calculate_totals();
$check(abs((float)WC()->cart->get_total('edit')-15)<.001,'Credit uses native product discount');
$spend=$native(get_userdata($a)->user_email,$a);$check(!is_wp_error($spend),'Native credit checkout succeeds');
$check(Store::balance($a)===0,'Credit reserved before payment');$spent=wc_get_order($spend);Program::reserve($spent);
WC()->session->set('order_awaiting_payment',$spend);$check(Program::available()===500,'Classic same-cart retry can reuse its own reservation');
$key=array_key_first(WC()->cart->get_cart());WC()->cart->set_quantity($key,2,false);$check(Program::available()===0,'Changed cart cannot reuse another order reservation');WC()->cart->set_quantity($key,1,false);
WC()->session->__unset('order_awaiting_payment');WC()->session->set('store_api_draft_order',$spend);$check(Program::available()===500,'Blocks same-cart retry can reuse its reservation');WC()->session->__unset('store_api_draft_order');

$check(Store::balance($a)===0,'Repeat reservation does not debit twice');
$reject(fn()=>$manual(($testTag.'-overspend@example.invalid'),0,$a,500),'A second checkout cannot overspend');
$spent->update_status('failed');$check(Store::balance($a)===500,'Failed payment releases credit');
$spent->update_status('pending');Program::reserve($spent);$check(Store::balance($a)===0,'Retry reserves credit again');
$spent->payment_complete();$spent->update_status('completed');$check(Store::balance($a)===0,'Payment and completion do not double debit');
$refund=wc_create_refund(['order_id'=>$spend,'amount'=>7.5,'refund_payment'=>false]);
$check(!is_wp_error($refund) && Store::balance($a)===250,'Half cash refund restores half spent credit');Program::changed($spend);$check(Store::balance($a)===250,'Partial refund retry is idempotent');
wc_create_refund(['order_id'=>$spend,'amount'=>7.5,'refund_payment'=>false]);$check(Store::balance($a)===500,'Full cash refund restores remaining credit');
$refunded=wc_create_refund(['order_id'=>$id,'amount'=>15,'refund_payment'=>false]);$check(!is_wp_error($refunded) && Store::balance($a)===0,'Refunding original referral reverses its reward');Program::changed($id);$check(Store::balance($a)===0,'Reward reversal is idempotent');
$check(!Store::first_order(($testTag.'-friend-1@example.invalid'),0),'Refund does not make a past buyer new again');
for($i=0;$i<12;$i++){$earned=$manual('demo-many-'.$i.'@example.invalid',$a);$earned->payment_complete();$earned->update_status('completed');}
$check(Store::balance($a)===6000,'Rewards accumulate across twelve real qualifying test orders');
$p->set_regular_price('10');$p->save();$cart($a);WC()->cart->set_quantity(array_key_first(WC()->cart->get_cart()),2);WC()->cart->apply_coupon('sscn-credit');WC()->cart->calculate_totals();$check((float)WC()->cart->get_total('edit')===0.0,'Credit can cover the entire product total');
$zero=$native(get_userdata($a)->user_email,$a);$check(!is_wp_error($zero),'Zero-cash checkout reserves successfully');$zo=wc_get_order($zero);$zo->payment_complete();
$check(!$zo->needs_payment() && Store::balance($a)===4000,'Only actual applied credit is spent; unused balance retained');
$line=current($zo->get_items());$refund=wc_create_refund(['order_id'=>$zero,'amount'=>0,'line_items'=>[$line->get_id()=>['qty'=>1,'refund_total'=>0]],'refund_payment'=>false]);
$check(!is_wp_error($refund) && Store::balance($a)===5000,'Partial zero-cash quantity refund returns proportional credit');
$refund=wc_create_refund(['order_id'=>$zero,'amount'=>0,'line_items'=>[$line->get_id()=>['qty'=>1,'refund_total'=>0]],'refund_payment'=>false]);
$check(!is_wp_error($refund) && Store::balance($a)===6000,'Final zero-cash quantity refund returns remaining credit');
$p->set_regular_price('20');$p->save();
$cart(0,($testTag.'-guest-credit@example.invalid'));$check(!WC()->cart->apply_coupon('sscn-credit'),'Anonymous balance redemption blocked');
// Existing buyers with no referral history cannot qualify, including changed email on same account.
$prior=$manual(($testTag.'-existing@example.invalid'),0,$b);$prior->payment_complete();
$check(!Store::first_order(($testTag.'-existing@example.invalid'),0),'Native historic guest order disqualifies');
$check(!Store::first_order(($testTag.'-new-email@example.invalid'),$b),'Customer ID prevents changed billing-email bypass');
$pending=$manual(($testTag.'-cancel-first@example.invalid'),$a);$check(!Store::first_order(($testTag.'-cancel-first@example.invalid'),0),'Reserved first order blocks concurrent checkout');
$pending->update_status('cancelled');$check(Store::first_order(($testTag.'-cancel-first@example.invalid'),0),'Unpaid cancelled attempt releases first-order claim');
// Negative balance is retained, never cashed out or silently forgiven.
$last=$manual(($testTag.'-negative@example.invalid'),$c);$last->payment_complete();$last->update_status('completed');
$debit=$manual(get_userdata($c)->user_email,0,$c,500);$debit->payment_complete();$debit->update_status('completed');
$last->update_status('refunded');$check(Store::balance($c)===-500,'Spent reward reversal records deficit');
wp_set_current_user($c);$check(Program::available()===0,'Deficit is not spendable');
$offset=$manual(($testTag.'-offset@example.invalid'),$c);$offset->payment_complete();$offset->update_status('completed');$check(Store::balance($c)===0,'Future reward offsets previous reversal');
// A late successful payment after cancellation reclaims the original credit.
$late=$manual(get_userdata($a)->user_email,0,$a,500);$late->update_status('cancelled');$before=Store::balance($a);$late->payment_complete();
$check(Store::balance($a)===$before-500,'Late payment reclaims previously released credit');
// Partial refund preserves the fixed referral reward while a purchase remains.
$partial=$manual(($testTag.'-partial-ref@example.invalid'),$b);$partial->payment_complete();$partial->update_status('completed');$before=Store::balance($b);wc_create_refund(['order_id'=>$partial->get_id(),'amount'=>1,'refund_payment'=>false]);$check(Store::balance($b)===$before,'Partial refund keeps first-order reward');
// Currency and disabled state fail closed, but historic refunds continue to reconcile.
update_option('woocommerce_currency','EUR');$check(!Program::enabled(),'Currency mismatch disables new rewards');update_option('woocommerce_currency','CNY');
update_option('sscn_referrals_enabled','no');$check(!Program::enabled(),'Program can pause');$partial->update_status('refunded');$check(Store::balance($b)===$before-500,'Refunds reconcile while paused');update_option('sscn_referrals_enabled','yes');
// Native pack pricing plus native referral coupon and zero shipping stay intact.
$cart(0,($testTag.'-pack-ref@example.invalid'));WC()->cart->empty_cart();
$model=wc_get_product(wc_get_product_id_by_sku('SS-001-X70'));$children=$model->get_children();
Pack::add_pack($model->get_id(),4,[$children[0]=>4]);WC()->cart->calculate_totals();$beforepack=WC()->cart->get_cart_contents_total();
WC()->cart->apply_coupon(Program::code($a));WC()->cart->calculate_totals();
$check(abs(WC()->cart->get_cart_contents_total()-($beforepack-5))<.001,'Four-pair domestic pack receives five-yuan discount');
$check(Pack::cart_error(WC()->cart)==='','Referral discount preserves native pack integrity');
// Signed-in account rendering never exposes another customer's email/order data.
wp_set_current_user($a);ob_start();Program::account();$html=ob_get_clean();$check(str_contains($html,Program::code($a)) && !str_contains($html,($testTag.'-friend-1@example.invalid')),'Private balance UI contains only own referral details');
$check(str_contains($html,'不可提现') && str_contains($html,'不过期'),'Account terms show noncash cumulative credit');
// Real separate processes compete for the same balance and first-order identity.
$raceuser=$user('race');$seed=$manual(($testTag.'-race-seed@example.invalid'),$raceuser);$seed->payment_complete();$seed->update_status('completed');
$runrace=function($orders,$owner,$credit,$referrer)use($check){
 $folder=sys_get_temp_dir().'/pc-ref-race-'.bin2hex(random_bytes(6));mkdir($folder,0700);$processes=[];
 foreach($orders as $n=>$order){$cmd=[PHP_BINARY,dirname(ABSPATH,3).'/bin/wp-cli.phar'];$cli=dirname(__DIR__).'/.local/bin/wp-cli.phar';$command=[PHP_BINARY,$cli,'--path='.ABSPATH,'eval-file',dirname(__DIR__).'/tests/domestic-referral-worker.php',(string)$order->get_id(),(string)$credit,(string)$referrer,$folder,(string)$n];$pipes=[];$proc=proc_open($command,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fclose($pipes[0]);$processes[]=[$proc,$pipes];}
 $end=microtime(true)+30;while(count(glob($folder.'/ready-*'))<2 && microtime(true)<$end){usleep(20000);}file_put_contents($folder.'/go','go');$out=[];
 foreach($processes as [$proc,$pipes]){$out[]=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$check(proc_close($proc)===0,'Race worker exits cleanly: '.$err);}
 $check(count(array_filter($out,fn($s)=>str_contains($s,'RESERVED')))===1,'Exactly one competing reservation succeeds');foreach(glob($folder.'/*')as$file)unlink($file);rmdir($folder);
};
$neworder=function($email,$uid)use($p){$o=wc_create_order(['customer_id'=>$uid]);$o->set_billing_email($email);$o->add_product($p,1);$o->calculate_totals();$o->save();return $o;};
$runrace([$neworder(get_userdata($raceuser)->user_email,$raceuser),$neworder(get_userdata($raceuser)->user_email,$raceuser)],$raceuser,500,0);
$check(Store::balance($raceuser)===0,'Concurrent spend never makes balance negative');
$runrace([$neworder(($testTag.'-race-friend@example.invalid'),0),$neworder(($testTag.'-race-friend@example.invalid'),0)],0,0,$a);
// Database failures roll back the claim and debit together.
$rollbackuser=$user('rollback');$seed=$manual(($testTag.'-rollback-seed@example.invalid'),$rollbackuser);$seed->payment_complete();$seed->update_status('completed');
$rollbackorder=$neworder(get_userdata($rollbackuser)->user_email,$rollbackuser);
$fail=function($query)use($wpdb){return str_starts_with($query,'INSERT INTO `'.$wpdb->prefix.'sscn_referral_ledger`')?'DEMO INVALID QUERY FOR ROLLBACK':$query;};
$old_errors=$wpdb->suppress_errors(true);add_filter('query',$fail);
$reject(fn()=>Store::reserve($rollbackorder,500,$b),'A ledger write failure rejects the reservation');remove_filter('query',$fail);$wpdb->suppress_errors($old_errors);
$check(Store::balance($rollbackuser)===500 && Store::record($rollbackorder->get_id())===null,'Failed transaction cannot consume funds or leave an order target');
$check(Store::first_order(get_userdata($rollbackuser)->user_email,$rollbackuser,$rollbackorder->get_id()),'Failed transaction releases its first-order identity claim');

wp_set_current_user($admin);$saved_balance=Store::balance($a);Store::install();Store::install();
$check(Store::balance($a)===$saved_balance,'Repeated schema install preserves balances');
$check(Program::referrer('ss-ref-'.$a.'-'.substr(hash_hmac('sha256','referral:'.$a,wp_salt('auth')),0,20))===0,'International code rejected even with same salt');
$check(Program::referrer(Program::code($a))===$a,'Domestic signed code resolves');
$usd=$neworder(($testTag.'-wrong-currency@example.invalid'),0);$usd->set_currency('USD');$usd->save();
$reject(fn()=>Store::reserve($usd,0,$a),'USD order rejected');
wp_set_current_user(0);$guest=ParameterCommerce\Referral_UI::panel();
$check(str_contains($guest,'登录或注册')&&!str_contains($guest,Program::code($a)),'Guest sees Chinese CTA without private link');
wp_set_current_user($a);$panel=ParameterCommerce\Referral_UI::panel();
$check(str_contains($panel,Program::code($a))&&str_contains($panel,'¥5')&&!str_contains($panel,'$5'),'Private panel uses own CNY link');
WP_CLI::success($checks.' domestic referral lifecycle and concurrency assertions passed.');

} finally {
 if(WC()->cart)WC()->cart->empty_cart();
 foreach(array_unique($testOrders)as$id){$order=wc_get_order($id);if($order){foreach($order->get_refunds()as$r)$r->delete(true);$order->delete(true);}
 foreach(['ledger','orders','claims']as$suffix)$wpdb->delete($wpdb->prefix.'sscn_referral_'.$suffix,['order_id'=>$id],['%d']);}
 foreach($testProducts as$id){$product=wc_get_product($id);if($product)$product->delete(true);}
 require_once ABSPATH.'wp-admin/includes/user.php';foreach($testUsers as$id)wp_delete_user($id);
 foreach($oldOptions as$key=>$value)update_option($key,$value);
}
