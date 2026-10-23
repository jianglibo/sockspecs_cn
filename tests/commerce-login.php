<?php
/** Local WP integration checks; generated identities only, no provider calls. */
if (!defined('WP_CLI') || !WP_CLI || DB_NAME !== 'sockspecs_cn' || home_url() !== 'http://127.0.0.1:8089') { throw new RuntimeException('Local domestic test only'); }
use function SockSpecsCN\Commerce\resolve_customer;
use function SockSpecsCN\Commerce\consume_state;
use function SockSpecsCN\Commerce\safe_next;
use function SockSpecsCN\Commerce\login_button;
use function SockSpecsCN\Commerce\exchange_identity;
function ct_check($ok, $m) { if (!$ok) { throw new RuntimeException($m); } }
function ct_reject($f, $m) { try { $f(); } catch (Throwable $e) { return; } throw new RuntimeException($m); }
$users=[]; $app='test-' . bin2hex(random_bytes(10));
$network_guard=static function () { throw new RuntimeException('External HTTP forbidden in integration test'); };
add_filter('pre_http_request',$network_guard,99);
try {
    ct_check(!SockSpecsCN\Commerce\live_ready(), 'Must be disabled');
    wp_set_current_user(0);
    ob_start(); login_button(); $button=ob_get_clean();
    ct_check(str_contains($button, 'disabled') && str_contains($button, '暂未开放') && !str_contains($button, 'href='), 'Disabled login must show a non-actionable placeholder');
    foreach (['sscn_wechat','sscn_alipay'] as $method) { ct_check(!WC()->payment_gateways()->payment_gateways()[$method]->is_available(), 'Gateway visible without release approval'); }
    $id=resolve_customer($app,'test-openid');$users[]=$id;
    ct_check(resolve_customer($app,'test-openid')===$id,'Duplicate customer');
    ct_check(get_userdata($id)->roles===['customer'],'Wrong privilege');
    $other=resolve_customer($app,'other-openid');$users[]=$other;
    ct_reject(fn()=>resolve_customer($app,'test-openid',$other),'Binding collision accepted');
    ct_reject(fn()=>resolve_customer($app,'replacement',$id),'Identity replacement accepted');
    ct_reject(fn()=>resolve_customer($app,'admin-openid',get_user_by('login','sockspecs_cn')->ID),'Privileged binding accepted');
    $web=resolve_customer($app.'-website','test-openid');$users[]=$web;
    ct_check($web!==$id,'Cross-app identity merged');
    $state=bin2hex(random_bytes(32));$cookie=bin2hex(random_bytes(32));$key='sscn_oauth_'.hash('sha256',$state);
    set_transient($key,['browser'=>hash('sha256',$cookie),'expires'=>time()+300,'kind'=>'official'],300);
    ct_reject(fn()=>consume_state($state,str_repeat('0',64)),'Wrong browser accepted');
    ct_check(consume_state($state,$cookie)['kind']==='official','Valid state failed');
    ct_reject(fn()=>consume_state($state,$cookie),'Replay accepted');
    set_transient($key,['browser'=>hash('sha256',$cookie),'expires'=>time()-1],300);
    ct_reject(fn()=>consume_state($state,$cookie),'Expired state accepted');delete_transient($key);
    ct_check(wp_parse_url(safe_next('https://evil.example/'),PHP_URL_HOST)==='127.0.0.1','Open redirect');
    remove_filter('pre_http_request',$network_guard,99);
    $mock=static fn()=>['headers'=>[],'body'=>json_encode(['access_token'=>'ephemeral','openid'=>'verified-openid']),'response'=>['code'=>200,'message'=>'OK'],'cookies'=>[]];
    add_filter('pre_http_request',$mock,99);
    ct_check(exchange_identity('code',['app_id'=>'fake','secret'=>'fake'])==='verified-openid','OAuth exchange');
    remove_filter('pre_http_request',$mock,99);
    $mock=static fn()=>['headers'=>[],'body'=>'{"errcode":40029}','response'=>['code'=>200,'message'=>'OK'],'cookies'=>[]];
    add_filter('pre_http_request',$mock,99);ct_reject(fn()=>exchange_identity('bad',['app_id'=>'fake','secret'=>'fake']),'Rejected OAuth accepted');remove_filter('pre_http_request',$mock,99);
    echo "PASS: disabled gates, customer creation/reuse, privilege and binding protection, app isolation, browser-bound state, expiry/replay, redirects, mocked OAuth.\n";
} finally {
    require_once ABSPATH.'wp-admin/includes/user.php';foreach($users as $id){wp_delete_user($id);} remove_filter('pre_http_request',$network_guard,99);
}
