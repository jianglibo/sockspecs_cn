<?php
/** Local database integration test; creates and removes only its own fixtures. */
use function SockSpecsCN\Commerce\WearFeedback\{install,register_entry,issue,record,table,update};
if (!defined('WP_CLI') || !WP_CLI || home_url() !== 'http://127.0.0.1:8089' || DB_NAME !== 'sockspecs_cn') { throw new RuntimeException('Local only'); }
function wf_assert($ok, $why) { if (!$ok) { throw new RuntimeException($why); } }
function wf_fails(callable $fn, string $why) { try { $fn(); } catch (Throwable $e) { return; } throw new RuntimeException($why); }
install(); global $wpdb;
add_filter('pre_wp_mail', '__return_false');
add_filter('pre_http_request', static function () { throw new RuntimeException('Network forbidden in local feedback test'); });
$admin = get_current_user_id(); $ids=[]; $coupons=[];
try {
    $order = wc_create_order(['status'=>'pending']); $ids[]=$order->get_id(); $id=$order->get_id();
    $order->set_currency('CNY'); $order->set_billing_email('wear-test@example.invalid'); $order->save();
    $request=wp_generate_uuid4();
    register_entry($id,0,'本地测试：实际穿着三次，脚跟磨损。','pending',$request,['data'=>"%PDF-1.4\nfixture",'name'=>'private.pdf','mime'=>'application/pdf']);
    register_entry($id,0,'重复提交不应追加','pending',$request);
    wf_assert($wpdb->get_var($wpdb->prepare('SELECT attachment FROM '.table('entries').' WHERE request_key=%s',$request))==="%PDF-1.4\nfixture",'Attachment not preserved');
    wf_assert((int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.table('entries').' WHERE order_id=%d',$id))===1,'Duplicate submission');
    wf_fails(fn()=>issue($id,'5',true),'Unpaid accepted');
    wf_fails(fn()=>register_entry($id,999999,'wrong item','pending',wp_generate_uuid4()),'Foreign item accepted');
    $order->set_status('completed'); $order->set_date_paid(time()); $order->save();
    foreach (['1','10.01','NaN','-2','1e1'] as $amount) { wf_fails(fn()=>issue($id,$amount,true),'Invalid amount accepted'); }
    wf_fails(fn()=>issue($id,'5',false),'Review not confirmed');
    wp_set_current_user(0); wf_fails(fn()=>issue($id,'5',true),'Guest issued reward'); wf_fails(fn()=>register_entry($id,0,'bad','pending',wp_generate_uuid4()),'Guest registered'); wp_set_current_user($admin);
    $coupon_id=issue($id,'5',true); $coupons[]=$coupon_id;
    wf_assert(issue($id,'10',true)===$coupon_id,'Duplicate coupon');
    $coupon=new WC_Coupon($coupon_id);
    wf_assert($coupon->get_amount()==5 && $coupon->get_usage_limit()===1 && $coupon->get_email_restrictions()===['wear-test@example.invalid'],'Coupon constraints');
    wf_assert(apply_filters('woocommerce_coupon_is_valid',true,$coupon)===true,'Issued coupon invalid');
    $discounts=new WC_Discounts($order);
    wf_assert($discounts->is_coupon_valid($coupon)===true,'Matching order email rejected');
    $order->set_billing_email('other@example.invalid');
    wf_assert(is_wp_error((new WC_Discounts($order))->is_coupon_valid($coupon)),'Wrong recipient accepted');
    $order->set_billing_email('wear-test@example.invalid');
    $peer=new wpdb(DB_USER,DB_PASSWORD,DB_NAME,DB_HOST);
    $lock_key='sscn_wear_'.md5($wpdb->prefix.':'.$id);
    $peer->get_var($peer->prepare('SELECT GET_LOCK(%s,0)',$lock_key));
    try { wf_fails(fn()=>issue($id,'5',true),'Concurrent issue ignored lock'); } finally { $peer->get_var($peer->prepare('SELECT RELEASE_LOCK(%s)',$lock_key)); $peer->close(); }
    register_entry($id,0,'第五次使用后补充','needs_info',wp_generate_uuid4());
    wf_assert(record($id)->state==='rewarded','Supplement cleared reward');
    // Simulate failure after reservation and partial coupon post insertion.
    $second=wc_create_order(['status'=>'completed']); $ids[]=$second->get_id(); $sid=$second->get_id();
    $second->set_currency('CNY'); $second->set_billing_email('wear-second@example.invalid'); $second->set_date_paid(time()); $second->save();
    register_entry($sid,0,'中断恢复测试','pending',wp_generate_uuid4());
    $code='wear-'.bin2hex(random_bytes(12));
    update($sid,['state'=>'issuing','amount'=>'7','coupon_code'=>$code,'reward_email'=>'wear-second@example.invalid']);
    $partial=wp_insert_post(['post_type'=>'shop_coupon','post_status'=>'publish','post_title'=>$code]); $coupons[]=$partial;
    wf_assert(apply_filters('woocommerce_coupon_is_valid',true,new WC_Coupon($partial))===false,'Incomplete coupon usable');
    wf_assert(issue($sid,'7',true)===$partial,'Recovery created duplicate coupon');
    $fixed=new WC_Coupon($partial); wf_assert($fixed->get_amount()==7 && $fixed->get_usage_limit()===1,'Recovery did not repair coupon');
    // A deleted coupon must not reset the once-per-order entitlement.
    $coupon->delete(true); wf_assert(issue($id,'5',true)===$coupon_id,'Deleted coupon reissued');
    WP_CLI::success('Feedback integration: duplicate registration/reward, bounds, permissions, unpaid orders, private attachment, supplements and interrupted recovery passed.');
} finally {
    wp_set_current_user($admin);
    foreach ($coupons as $c) { $coupon=new WC_Coupon($c); if ($coupon->get_id()) { $coupon->delete(true); } }
    foreach ($ids as $i) { $wpdb->delete(table('entries'),['order_id'=>$i]); $wpdb->delete(table('records'),['order_id'=>$i]); $o=wc_get_order($i); if ($o) { $o->delete(true); } }
}
