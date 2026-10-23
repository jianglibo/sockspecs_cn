<?php
/** WP-CLI eval-file integration test; uses temporary orders and never contacts providers. */
if (!defined('WP_CLI') || !WP_CLI || DB_NAME !== 'sockspecs_cn' || home_url() !== 'http://127.0.0.1:8089') { throw new RuntimeException('Local domestic test only'); }
add_filter('pre_http_request', static function () { throw new RuntimeException('External HTTP forbidden'); }, 99);
add_filter('pre_wp_mail', '__return_true');
$product = new WC_Product_Simple();
$product->set_name('SockSpecs payment integration fixture');
$product->set_status('draft');
$product->set_regular_price('12.34');
$product->set_manage_stock(true);
$product->set_stock_quantity(3);
$product->save();
$order = null;
try {
    $order = wc_create_order(['status' => 'pending']);
    $order->set_currency('CNY');
    $order->set_payment_method('sscn_wechat');
    $order->set_billing_email('payment-test@example.invalid');
    $order->add_product($product, 1);
    $order->calculate_totals();
    $trade = 'SCN' . $order->get_id() . 'X' . bin2hex(random_bytes(8));
    $attempt = ['trade' => $trade, 'method' => 'sscn_wechat', 'amount' => 1234];
    $order->update_meta_data(\SockSpecsCN\Commerce\ATTEMPT_META, $attempt);
    $order->save();
    $event = ['trade' => $trade, 'transaction' => 'TESTONLY' . bin2hex(random_bytes(8)), 'amount' => 1234, 'currency' => 'CNY'];
    $wrong = $event; $wrong['amount'] = 1;
    $rejected = false;
    try { \SockSpecsCN\Commerce\complete_payment('sscn_wechat', $wrong); }
    catch (Throwable $e) { $rejected = true; }
    if (!$rejected || wc_get_order($order->get_id())->is_paid()) { throw new RuntimeException('Amount mismatch accepted'); }
    $count = 0;
    add_action('woocommerce_payment_complete', static function ($id) use (&$count, $order): void {
        if ($id === $order->get_id()) { $count++; }
    });
    \SockSpecsCN\Commerce\complete_payment('sscn_wechat', $event);
    \SockSpecsCN\Commerce\complete_payment('sscn_wechat', $event);
    $fresh = wc_get_order($order->get_id());
    if (!$fresh->is_paid() || $fresh->get_transaction_id() !== $event['transaction'] || $count !== 1 ||
        wc_get_product($product->get_id())->get_stock_quantity() !== 2) {
        throw new RuntimeException('Payment completion or stock idempotency failed');
    }
    $conflict = $event; $conflict['transaction'] = 'OTHERTRANSACTION123';
    $rejected = false;
    try { \SockSpecsCN\Commerce\complete_payment('sscn_wechat', $conflict); }
    catch (Throwable $e) { $rejected = true; }
    if (!$rejected) { throw new RuntimeException('Conflicting transaction accepted'); }
    echo "PASS: amount mismatch rejected; valid confirmation pays the order and reduces stock exactly once; conflicting transaction rejected.\n";
} finally {
    if ($order) { $order->delete(true); }
    $product->delete(true);
    echo "Deleted the temporary order and draft product. No provider calls or emails were made.\n";
}
