<?php
/** Offline tests with ephemeral keys; never contacts a payment provider. */
declare(strict_types=1);
require dirname(__DIR__) . '/commerce-libraries/vendor/autoload.php';
require dirname(__DIR__) . '/sscn-commerce/includes/providers.php';
use SockSpecsCN\Commerce\Providers;
use WeChatPay\Crypto\AesGcm;
use function SockSpecsCN\Commerce\minor_units;
use function SockSpecsCN\Commerce\validate_event;

function check(bool $ok, string $message): void { if (!$ok) { throw new RuntimeException($message); } }
function reject(callable $fn, string $message): void {
    try { $fn(); } catch (Throwable $e) { return; }
    throw new RuntimeException('Accepted invalid input: ' . $message);
}

$dir = sys_get_temp_dir() . '/sscn-payment-test-' . bin2hex(random_bytes(8));
mkdir($dir, 0700);
try {
    $key = openssl_pkey_new(['private_key_bits' => 2048]);
    openssl_pkey_export($key, $pem);
    $csr = openssl_csr_new(['commonName' => 'payments.example.test'], $key);
    $cert = openssl_csr_sign($csr, null, $key, 1, ['digest_alg' => 'sha256'], 123);
    openssl_x509_export($cert, $certPem);
    file_put_contents($dir . '/private.pem', $pem);
    file_put_contents($dir . '/cert.pem', $certPem);
    $config = ['alipay' => ['app_id' => 'test-app', 'private_key' => $dir . '/private.pem',
        'app_cert' => $dir . '/cert.pem', 'public_cert' => $dir . '/cert.pem', 'root_cert' => $dir . '/cert.pem'],
        'wechat' => ['app_id' => 'test-app', 'mch_id' => 'test-merchant', 'platform_serial' => 'TEST_SERIAL',
            'serial' => 'TEST_MERCHANT', 'private_key' => $dir . '/private.pem', 'platform_cert' => $dir . '/cert.pem', 'api_v3_key' => str_repeat('x', 32)]];
    $providers = new Providers($config);
    check(minor_units('0.01') === 1 && minor_units('12.3') === 1230 && minor_units('10.00') === 1000, 'Money parsing');
    foreach (['-1', '1e3', '1.001', 'NaN', '01.00', '1000000000', '1.00junk'] as $bad) {
        reject(fn() => minor_units($bad), 'amount');
    }
    $trade = 'SCN42X0123456789abcdef';
    $a = ['app_id' => 'test-app', 'sign_type' => 'SCNA2', 'out_trade_no' => $trade,
        'trade_no' => 'ALIPAYTEST1234', 'trade_status' => 'TRADE_SUCCESS', 'total_amount' => '12.34'];
    $sign = static function (array $params) use ($key): array {
        $content = (new \Alipay\EasySDK\Kernel\Util\Signer())->getSignContent($params);
        openssl_sign($content, $signature, $key, OPENSSL_ALGO_SHA256);
        $params['sign'] = base64_encode($signature);
        return $params;
    };
    $a = $sign($a);
    $event = $providers->alipay_notification($a);
    check($event['amount'] === 1234 && $event['trade'] === $trade, 'Signed Alipay callback');
    $tampered = $a; $tampered['total_amount'] = '0.01';
    reject(fn() => $providers->alipay_notification($tampered), 'Alipay tampering');
    $wrongApp = $a; $wrongApp['app_id'] = 'other-app';
    reject(fn() => $providers->alipay_notification($sign($wrongApp)), 'Alipay wrong app');
    $pending = $a; $pending['trade_status'] = 'WAIT_BUYER_PAY';
    check($providers->alipay_notification($sign($pending)) === null, 'Unpaid Alipay event');

    $transaction = ['appid' => 'test-app', 'mchid' => 'test-merchant', 'trade_state' => 'SUCCESS',
        'out_trade_no' => $trade, 'transaction_id' => 'WECHATTEST1234', 'amount' => ['total' => 1234, 'currency' => 'CNY']];
    $envelope = static function (array $data, int $timestamp) use ($key, $config): array {
        $body = json_encode(['event_type' => 'TRANSACTION.SUCCESS', 'resource' => ['algorithm' => 'AEAD_AES_256_GCM',
            'nonce' => '123456789012', 'associated_data' => 'transaction',
            'ciphertext' => AesGcm::encrypt(json_encode($data), $config['wechat']['api_v3_key'], '123456789012', 'transaction')]]);
        $headers = ['timestamp' => (string) $timestamp, 'nonce' => 'testnonce', 'serial' => 'TEST_SERIAL'];
        openssl_sign($timestamp . "\ntestnonce\n" . $body . "\n", $signature, $key, OPENSSL_ALGO_SHA256);
        $headers['signature'] = base64_encode($signature);
        return [$body, $headers];
    };
    [$body, $headers] = $envelope($transaction, time());
    $event = $providers->wechat_notification($body, $headers);
    check($event['amount'] === 1234 && $event['transaction'] === 'WECHATTEST1234', 'Signed encrypted WeChat callback');
    reject(fn() => $providers->wechat_notification($body . ' ', $headers), 'WeChat altered body');
    reject(fn() => $providers->wechat_notification($body, $headers, time() + 301), 'Stale notification');
    $badHeaders = $headers; $badHeaders['serial'] = 'OTHER_SERIAL';
    reject(fn() => $providers->wechat_notification($body, $badHeaders), 'Unknown signing key');
    $transaction['mchid'] = 'another-merchant';
    [$wrongBody, $wrongHeaders] = $envelope($transaction, time());
    reject(fn() => $providers->wechat_notification($wrongBody, $wrongHeaders), 'Wrong WeChat merchant');

    $attempt = ['trade' => $trade, 'method' => 'sscn_wechat', 'amount' => 1234];
    validate_event($event, $attempt, 'sscn_wechat', 'CNY', 1234);
    reject(fn() => validate_event($event, $attempt, 'sscn_wechat', 'CNY', 1235), 'Wrong amount');
    reject(fn() => validate_event($event, $attempt, 'sscn_alipay', 'CNY', 1234), 'Wrong method');
    reject(fn() => validate_event($event, $attempt, 'sscn_wechat', 'USD', 1234), 'Wrong currency');
    $attempt['trade'] = 'SCN43X0123456789abcdef';
    reject(fn() => validate_event($event, $attempt, 'sscn_wechat', 'CNY', 1234), 'Wrong order');
    // Signed provider responses over an in-memory transport: no live API requests.
    $requests = [];
    $handler = static function ($request, $options) use ($key, &$requests) {
        $path = $request->getUri()->getPath(); $data = json_decode((string)$request->getBody(), true); $requests[] = [$path, $data];
        check(str_starts_with($request->getHeaderLine('Authorization'), 'WECHATPAY2-SHA256-RSA2048'), 'Request signature missing');
        $payload = str_ends_with($path, '/native') ? ['code_url'=>'weixin://wxpay/test'] : (str_ends_with($path, '/jsapi') ? ['prepay_id'=>'test-prepay'] : ['h5_url'=>'https://wx.tenpay.com/cgi-bin/mmpayweb-bin/checkmweb?prepay_id=test']);
        $body = json_encode($payload); $now = (string)time(); $nonce = 'test-response';
        openssl_sign($now . "\n" . $nonce . "\n" . $body . "\n", $signature, $key, OPENSSL_ALGO_SHA256);
        return \GuzzleHttp\Promise\Create::promiseFor(new \GuzzleHttp\Psr7\Response(200, [
            'Wechatpay-Timestamp'=>$now,'Wechatpay-Nonce'=>$nonce,'Wechatpay-Serial'=>'TEST_SERIAL','Wechatpay-Signature'=>base64_encode($signature)], $body));
    };
    $mobile = new Providers($config, \GuzzleHttp\HandlerStack::create($handler));
    check($mobile->wechat_create($trade,1234,'https://example.test/notify',time()+3600)==='weixin://wxpay/test','Native create');
    check($mobile->wechat_mobile('jsapi',$trade,1234,'https://example.test/notify',time()+3600,'official-openid')['prepay_id']==='test-prepay','JSAPI create');
    check($requests[1][1]['payer']['openid']==='official-openid' && $requests[1][1]['amount']['total']===1234,'JSAPI payer/amount');
    check(isset($mobile->wechat_mobile('h5',$trade,1234,'https://example.test/notify',time()+3600,'','8.8.8.8')['h5_url']),'H5 create');
    check($requests[2][1]['scene_info']['payer_client_ip']==='8.8.8.8','H5 client IP');
    reject(fn()=>$mobile->wechat_mobile('jsapi',$trade,1234,'https://example.test/notify',time()+3600,''),'Missing payer');
    reject(fn()=>$mobile->wechat_mobile('h5',$trade,1234,'https://example.test/notify',time()+3600,'','172.16.3.52'),'Private client IP');
    $params=$mobile->jsapi_parameters('test-prepay');
    check(openssl_verify($params['appId']."\n".$params['timeStamp']."\n".$params['nonceStr']."\n".$params['package']."\n",base64_decode($params['paySign']),$certPem,OPENSSL_ALGO_SHA256)===1,'JSAPI client signature');
    foreach ([false=>'alipay.trade.page.pay',true=>'alipay.trade.wap.pay'] as $isMobile=>$method) {
        $form=$mobile->alipay_form($trade,1234,'https://example.test/notify','https://example.test/return',(bool)$isMobile);
        check(str_contains($form,$method) && str_contains($form,'12.34') && str_contains($form,'https://openapi.alipay.com'),'Alipay signed form route');
    }
    echo "PASS: Native/JSAPI/H5 signed requests and mock responses, payer/IP validation, JSAPI RSA signature, desktop/mobile Alipay forms.\n";
    echo "PASS: exact money conversion, Alipay and WeChat signatures, encrypted callbacks, tampering, stale notifications, merchant/app binding, and order/amount/currency validation.\n";
} finally {
    foreach (glob($dir . '/*') as $path) { unlink($path); }
    rmdir($dir);
}
