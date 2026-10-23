<?php
/** Explicit operator-invoked test: sends exactly one account and one order-style message. */
if (!defined('WP_CLI') || !WP_CLI || !current_user_can('manage_options') ||
    DB_NAME !== 'sockspecs_cn' || home_url() !== 'https://sockspecs.cn' ||
    !defined('WPMS_DO_NOT_SEND') || WPMS_DO_NOT_SEND) {
    throw new RuntimeException('Enabled domestic staff CLI only');
}
$recipient = $args[0] ?? '';
if (!is_email($recipient)) { throw new RuntimeException('Explicit test recipient required'); }
$mode = $args[1] ?? 'both';
if (!in_array($mode, ['both', 'account', 'order'], true)) { throw new RuntimeException('Invalid test mode'); }
$stamp = gmdate('Y-m-d H:i:s') . ' UTC';
$body = '这是 SockSpecs 国内站邮件配置测试，不涉及真实订单、付款或密码修改。';
$captured = [];
add_action('phpmailer_init', static function ($mailer) use (&$captured) {
    $mailer->SMTPDebug = 0;
    $captured[] = $mailer->From;
}, PHP_INT_MAX);
$expected = [];
if ($mode !== 'order') {
    $account = wp_mail($recipient, 'SockSpecs 账户邮件测试 — ' . $stamp, $body);
    if (!$account) { WP_CLI::error('Account test was not accepted. Check delivery before retrying.'); }
    $expected[] = 'hello@sockspecs.cn';
}
if ($mode !== 'account') {
    $email = WC()->mailer()->get_emails()['WC_Email_Customer_Processing_Order'];
    $order = $email->send($recipient, 'SockSpecs 订单邮件测试 — ' . $stamp, $body, $email->get_headers(), []);
    if (!$order) { WP_CLI::error('Order test was not accepted. Check delivery before retrying.'); }
    $expected[] = 'orders@sockspecs.cn';
}
if ($captured !== $expected) {
    WP_CLI::error('Tests submitted, but sender selection did not match expectations.');
}
echo wp_json_encode(['submitted' => count($captured), 'recipient' => $recipient, 'senders' => $captured, 'time' => $stamp], JSON_UNESCAPED_SLASHES) . PHP_EOL;
