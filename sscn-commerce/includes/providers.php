<?php
declare(strict_types=1);
namespace SockSpecsCN\Commerce;

use Alipay\EasySDK\Kernel\Config;
use Alipay\EasySDK\Kernel\Factory;
use WeChatPay\Builder;
use WeChatPay\Crypto\AesGcm;
use WeChatPay\Crypto\Rsa;

/** Decimal money conversion without floating-point rounding. */
function minor_units(string $amount): int {
    if (!preg_match('/^(0|[1-9][0-9]{0,8})(?:\.([0-9]{1,2}))?$/D', $amount, $m)) {
        throw new \InvalidArgumentException('Invalid CNY amount');
    }
    return ((int) $m[1]) * 100 + (int) str_pad($m[2] ?? '', 2, '0');
}

function decimal_units(int $amount): string {
    if ($amount <= 0) {
        throw new \InvalidArgumentException('Payment amount must be positive');
    }
    return intdiv($amount, 100) . '.' . str_pad((string) ($amount % 100), 2, '0', STR_PAD_LEFT);
}

/** Every callback/query must match both the saved attempt and current order. */
function validate_event(array $event, array $attempt, string $method, string $currency, int $amount): void {
    if (($event['trade'] ?? '') !== ($attempt['trade'] ?? null) ||
        ($attempt['method'] ?? '') !== $method ||
        ($event['currency'] ?? '') !== 'CNY' || $currency !== 'CNY' ||
        ($attempt['amount'] ?? null) !== $amount || ($event['amount'] ?? null) !== $amount ||
        $amount <= 0 || !is_string($event['transaction'] ?? null) ||
        !preg_match('/^[A-Za-z0-9_-]{6,80}$/D', $event['transaction'])) {
        throw new \RuntimeException('Payment does not match the order');
    }
}

final class Providers {
    public function __construct(private array $config, private ?\GuzzleHttp\HandlerStack $httpHandler = null) {}

    public static function production(): self {
        $c = config();
        if (!domestic_site() || !$c) { throw new \RuntimeException('Domestic commerce is not configured'); }
        // Existing signed callbacks/queries remain processable when new payments are disabled.
        return new self($c);
    }

    private function alipay() {
        $a = $this->config['alipay'];
        $options = new Config();
        $options->protocol = 'https';
        $options->gatewayHost = 'openapi.alipay.com';
        $options->appId = $a['app_id'];
        $options->signType = 'SCNA2';
        $options->merchantPrivateKey = preg_replace('/-----[^-]+-----|\s+/', '', file_get_contents($a['private_key']));
        $options->merchantCertPath = $a['app_cert'];
        $options->alipayCertPath = $a['public_cert'];
        $options->alipayRootCertPath = $a['root_cert'];
        $options->ignoreSSL = false;
        Factory::setOptions($options);
        return Factory::payment();
    }

    public function alipay_form(string $trade, int $amount, string $notify, string $return, bool $mobile = false): string {
        $payment = $this->alipay();
        $builder = ($mobile ? $payment->wap() : $payment->page())->asyncNotify($notify)->optional('timeout_express', '60m');
        return $mobile ? $builder->pay('SockSpecs 袜子订单', $trade, decimal_units($amount), $return, $return)->body
            : $builder->pay('SockSpecs 袜子订单', $trade, decimal_units($amount), $return)->body;
    }

    public function alipay_notification(array $params): ?array {
        foreach ($params as $value) {
            if (!is_string($value)) { throw new \RuntimeException('Invalid notification fields'); }
        }
        if (($params['app_id'] ?? '') !== $this->config['alipay']['app_id'] ||
            ($params['sign_type'] ?? '') !== 'SCNA2' || empty($params['sign']) ||
            !$this->alipay()->common()->verifyNotify($params)) {
            throw new \RuntimeException('Invalid Alipay signature or application');
        }
        if (!in_array($params['trade_status'] ?? '', ['TRADE_SUCCESS', 'TRADE_FINISHED'], true)) {
            return null;
        }
        return ['trade' => $params['out_trade_no'] ?? '', 'transaction' => $params['trade_no'] ?? '',
            'amount' => minor_units($params['total_amount'] ?? ''), 'currency' => 'CNY'];
    }

    private function wechat() {
        $w = $this->config['wechat'];
        $options = [
            'mchid' => $w['mch_id'], 'serial' => $w['serial'],
            'privateKey' => Rsa::from('file://' . $w['private_key'], Rsa::KEY_TYPE_PRIVATE),
            'certs' => [$w['platform_serial'] => Rsa::from('file://' . $w['platform_cert'], Rsa::KEY_TYPE_PUBLIC)],
            'timeout' => 20, 'connect_timeout' => 10,
        ];
        if ($this->httpHandler !== null) { $options['handler'] = $this->httpHandler; }
        return Builder::factory($options);
    }

    public function wechat_create(string $trade, int $amount, string $notify, int $expires): string {
        $w = $this->config['wechat'];
        $response = $this->wechat()->chain('v3/pay/transactions/native')->post(['json' => [
            'appid' => $w['app_id'], 'mchid' => $w['mch_id'], 'description' => 'SockSpecs 袜子订单',
            'out_trade_no' => $trade, 'notify_url' => $notify,
            'time_expire' => gmdate('Y-m-d\TH:i:s+00:00', $expires),
            'amount' => ['total' => $amount, 'currency' => 'CNY'],
        ]]);
        $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $url = $data['code_url'] ?? '';
        if (!is_string($url) || !str_starts_with($url, 'weixin://wxpay/')) {
            throw new \RuntimeException('Invalid WeChat QR response');
        }
        return $url;
    }

    public function wechat_mobile(string $mode, string $trade, int $amount, string $notify, int $expires, string $openid = '', string $ip = ''): array {
        if (!in_array($mode, ['jsapi', 'h5'], true)) { throw new \RuntimeException('Unknown mobile mode'); }
        $w = $this->config['wechat'];
        $data = ['appid' => $w['app_id'], 'mchid' => $w['mch_id'], 'description' => 'SockSpecs 袜子订单',
            'out_trade_no' => $trade, 'notify_url' => $notify, 'time_expire' => gmdate('Y-m-d\TH:i:s+00:00', $expires),
            'amount' => ['total' => $amount, 'currency' => 'CNY']];
        if ($mode === 'jsapi') {
            if (!preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $openid)) { throw new \RuntimeException('Missing payer identity'); }
            $data['payer'] = ['openid' => $openid];
        } else {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) { throw new \RuntimeException('Public client IP required'); }
            $data['scene_info'] = ['payer_client_ip' => $ip, 'h5_info' => ['type' => 'Wap']];
        }
        $r = $this->wechat()->chain('v3/pay/transactions/' . $mode)->post(['json' => $data]);
        $result = json_decode((string) $r->getBody(), true, 32, JSON_THROW_ON_ERROR);
        if ($mode === 'jsapi') {
            if (!preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $result['prepay_id'] ?? '')) { throw new \RuntimeException('Invalid prepay ID'); }
            return ['prepay_id' => $result['prepay_id']];
        }
        $url = $result['h5_url'] ?? '';
        if (parse_url($url, PHP_URL_SCHEME) !== 'https' || parse_url($url, PHP_URL_HOST) !== 'wx.tenpay.com') { throw new \RuntimeException('Invalid H5 cashier'); }
        return ['h5_url' => $url];
    }
    public function jsapi_parameters(string $prepay): array {
        $w = $this->config['wechat'];
        $p = ['appId' => $w['app_id'], 'timeStamp' => (string) time(), 'nonceStr' => bin2hex(random_bytes(16)),
            'package' => 'prepay_id=' . $prepay, 'signType' => 'RSA'];
        $p['paySign'] = Rsa::sign($p['appId'] . "\n" . $p['timeStamp'] . "\n" . $p['nonceStr'] . "\n" . $p['package'] . "\n",
            Rsa::from('file://' . $w['private_key'], Rsa::KEY_TYPE_PRIVATE));
        return $p;
    }

    public function wechat_notification(string $body, array $headers, ?int $now = null): ?array {
        $w = $this->config['wechat'];
        $timestamp = $headers['timestamp'] ?? '';
        $nonce = $headers['nonce'] ?? '';
        if (!is_string($timestamp) || !ctype_digit($timestamp) || abs(($now ?? time()) - (int) $timestamp) > 300 ||
            !is_string($nonce) || !preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $nonce) ||
            ($headers['serial'] ?? '') !== $w['platform_serial'] ||
            !Rsa::verify($timestamp . "\n" . $nonce . "\n" . $body . "\n", $headers['signature'] ?? '',
                Rsa::from('file://' . $w['platform_cert'], Rsa::KEY_TYPE_PUBLIC))) {
            throw new \RuntimeException('Invalid WeChat signature');
        }
        $envelope = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        if (($envelope['event_type'] ?? '') !== 'TRANSACTION.SUCCESS' ||
            ($envelope['resource']['algorithm'] ?? '') !== 'AEAD_AES_256_GCM') {
            throw new \RuntimeException('Unsupported WeChat notification');
        }
        $r = $envelope['resource'];
        $data = json_decode(AesGcm::decrypt($r['ciphertext'], $w['api_v3_key'], $r['nonce'], $r['associated_data'] ?? ''), true, 512, JSON_THROW_ON_ERROR);
        return $this->wechat_event($data);
    }

    private function wechat_event(array $data): ?array {
        $w = $this->config['wechat'];
        if (($data['appid'] ?? '') !== $w['app_id'] || ($data['mchid'] ?? '') !== $w['mch_id']) {
            throw new \RuntimeException('WeChat merchant does not match');
        }
        if (($data['trade_state'] ?? '') !== 'SUCCESS') { return null; }
        if (!is_int($data['amount']['total'] ?? null)) { throw new \RuntimeException('Invalid WeChat amount'); }
        return ['trade' => $data['out_trade_no'] ?? '', 'transaction' => $data['transaction_id'] ?? '',
            'amount' => $data['amount']['total'], 'currency' => $data['amount']['currency'] ?? ''];
    }

    public function query(string $method, string $trade): ?array {
        if ($method === 'sscn_alipay') {
            $r = $this->alipay()->common()->query($trade);
            if ((string) $r->code !== '10000') {
                if ($r->subCode === 'ACQ.TRADE_NOT_EXIST') { return null; }
                throw new \RuntimeException('Alipay query failed');
            }
            if (!in_array($r->tradeStatus, ['TRADE_SUCCESS', 'TRADE_FINISHED'], true)) { return null; }
            return ['trade' => $r->outTradeNo, 'transaction' => $r->tradeNo,
                'amount' => minor_units((string) $r->totalAmount), 'currency' => 'CNY'];
        }
        if ($method !== 'sscn_wechat') { throw new \RuntimeException('Unknown gateway'); }
        try {
            $r = $this->wechat()->chain('v3/pay/transactions/out-trade-no/' . rawurlencode($trade))
                ->get(['query' => ['mchid' => $this->config['wechat']['mch_id']]]);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $response = $e->getResponse();
            $data = $response ? json_decode((string) $response->getBody(), true) : null;
            // A Native QR can exist before the payer scans it and creates the transaction.
            if ($response && $response->getStatusCode() === 404 && ($data['code'] ?? '') === 'ORDER_NOT_EXIST') {
                return null;
            }
            throw $e;
        }
        return $this->wechat_event(json_decode((string) $r->getBody(), true, 512, JSON_THROW_ON_ERROR));
    }

    /** Close an unpaid WeChat transaction; never issues a charge or refund. */
    public function wechat_close(string $trade): void {
        $this->wechat()->chain('v3/pay/transactions/out-trade-no/' . rawurlencode($trade) . '/close')
            ->post(['json' => ['mchid' => $this->config['wechat']['mch_id']]]);
    }

    /** Close an unpaid Alipay transaction; never issues a charge or refund. */
    public function alipay_close(string $trade): void {
        $r = $this->alipay()->common()->close($trade);
        if ((string) $r->code !== '10000' && $r->subCode !== 'ACQ.TRADE_NOT_EXIST') {
            throw new \RuntimeException('Alipay close failed');
        }
    }
}
