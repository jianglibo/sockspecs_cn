<?php
/** Disposable local orders only; no gateway/network requests. */
use SockSpecsCN\Commerce\ColorSubstitution as C;
if (!defined('WP_CLI') || !WP_CLI || wp_get_environment_type() !== 'local' || DB_NAME !== 'sockspecs_cn') { throw new RuntimeException('Local only'); }
function color_check($ok, $label) { if (!$ok) { throw new RuntimeException($label); } }
function color_reject($fn, $label) { try { $fn(); } catch (Throwable $e) { return; } throw new RuntimeException('Expected rejection: ' . $label); }
$orders = []; $refunds = []; $old_user = get_current_user_id(); $old_post = $_POST;
try {
    wp_set_current_user(1);
    $controller = Automattic\WooCommerce\Blocks\Package::container()->get(Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFields::class);
    $field = $controller->get_additional_fields()[C::FIELD] ?? [];
    color_check($field['type'] === 'checkbox' && $field['required'] === false && $field['location'] === 'order', 'Optional order-level field registered');
    foreach ([false, true] as $accepted) {
        $o = wc_create_order(['status' => 'pending']); $orders[] = $o->get_id(); $o->set_currency('CNY'); $o->set_total('59.60'); $o->save();
        $r = new WP_REST_Request('POST', '/wc/store/v1/checkout'); $r->set_param('additional_fields', [C::FIELD => $accepted]);
        do_action('woocommerce_store_api_checkout_update_order_from_request', $o, $r); $o->save();
        $o = wc_get_order($o->get_id()); color_check(C::accepted($o) === $accepted, 'Blocks consent persists ' . (int) $accepted);
        color_check($o->get_total() === '59.60' && !$o->get_refunds() && !$o->get_items('fee'), 'Consent does not discount or refund');
        color_check($o->get_meta(C::CONSENT)['text'] === C::LABEL, 'Exact terms retained');
    }
    $no = wc_get_order($orders[0]); $yes = wc_get_order($orders[1]);
    $args = ['revision' => '0', 'later' => '', 'performed' => '1', 'details' => 'SS-S0001 黑色 → 蓝色，M，2双', 'refund_id' => '0'];
    color_reject(fn() => C::update($no->get_id(), $args), 'Unconsented substitution');
    color_reject(fn() => C::update($yes->get_id(), array_merge($args, ['details' => ''])), 'Missing actual replacement');
    wp_set_current_user(0); color_reject(fn() => C::update($yes->get_id(), $args), 'Unauthenticated admin update'); wp_set_current_user(1);
    C::update($no->get_id(), array_merge($args, ['later' => '1'])); $no = wc_get_order($no->get_id());
    color_check(!C::accepted($no) && C::state($no)['later'] && C::state($no)['performed'], 'Later consent distinct from checkout');
    color_check(str_contains(implode(' ', C::summary($no)), '待退 ¥2'), 'Later consent gets same refund entitlement');
    color_reject(fn() => C::update($no->get_id(), $args), 'Stale revision');
    C::update($yes->get_id(), $args); $yes = wc_get_order($yes->get_id());
    color_check(C::state($yes)['by'] === 1 && C::state($yes)['at'] !== '', 'Actor and time retained');
    $before_notes = count(wc_get_order_notes(['order_id' => $yes->get_id()]));
    C::update($yes->get_id(), array_merge($args, ['revision' => '1']));
    color_check(count(wc_get_order_notes(['order_id' => $yes->get_id()])) === $before_notes, 'Repeat save is idempotent');
    $paid = wc_create_refund(['order_id' => $yes->get_id(), 'amount' => 2, 'reason' => '缺货换色补偿', 'refund_payment' => false, 'restock_items' => false]);
    color_check(!is_wp_error($paid), 'Local manual refund fixture'); $refunds[] = $paid->get_id();
    $wrong = wc_create_refund(['order_id' => $yes->get_id(), 'amount' => 1, 'refund_payment' => false, 'restock_items' => false]); $refunds[] = $wrong->get_id();
    color_reject(fn() => C::update($no->get_id(), array_merge($args, ['revision' => '1', 'later' => '1', 'refund_id' => $paid->get_id()])), 'Other order refund rejected');
    color_reject(fn() => C::update($yes->get_id(), array_merge($args, ['revision' => '1', 'refund_id' => $wrong->get_id()])), 'Wrong refund amount');
    C::update($yes->get_id(), array_merge($args, ['revision' => '1', 'refund_id' => $paid->get_id()])); $yes = wc_get_order($yes->get_id());
    color_check(C::state($yes)['refund_id'] === $paid->get_id() && str_contains(implode(' ', C::summary($yes)), '已登记 ¥2'), 'Existing exact refund linked');
    color_reject(fn() => C::update($yes->get_id(), array_merge($args, ['revision' => '2'])), 'Cannot unlink completed refund');
    color_check(count($yes->get_refunds()) === 2 && $yes->get_total() === '59.60', 'No extra refund or price change');
    $html = C::print_html($yes);
    color_check(str_contains($html, 'SS-S0001 黑色 → 蓝色，M，2双') && str_contains($html, '已登记 ¥2') && str_contains($html, '下单时已同意'), 'Print consent, fulfillment and compensation');
    ob_start(); C::box($yes); $box = ob_get_clean(); color_check(str_contains($box, '打印订单') && str_contains($box, 'sscn_color_nonce'), 'HPOS-compatible editor and print entry');
    $notes = wc_get_order_notes(['order_id' => $yes->get_id()]); color_check(count($notes) > $before_notes, 'Audit notes recorded');
    $paid->delete(true); $refunds = array_values(array_diff($refunds, [$paid->get_id()]));
    color_check(!C::valid_refund($yes, C::state($yes)['refund_id']), 'Deleted refund no longer displayed as registered');
    // Classic checkout checked and unchecked; no inherited customer-level consent.
    $classic = new WC_Order(); $classic->set_currency('CNY'); $classic->set_status('pending');
    $_POST = ['sscn_color_consent' => '1']; do_action('woocommerce_checkout_create_order', $classic, []);
    color_check(C::accepted($classic), 'Classic checkbox captured');
    $_POST = []; do_action('woocommerce_checkout_create_order', $classic, []); color_check(!C::accepted($classic), 'Unchecked remains false');
    $classic->set_status('completed'); C::capture($classic, true); color_check(!C::accepted($classic), 'Fulfilled order consent cannot be rewritten');
    $_POST = ['sscn_color_nonce' => 'bad', 'sscn_color_revision' => '1'];
    require_once WC_ABSPATH . 'includes/admin/class-wc-admin-meta-boxes.php';
    $before = C::state(wc_get_order($no->get_id())); C::admin_save($no->get_id());
    color_check(C::state(wc_get_order($no->get_id())) === $before, 'Bad nonce does not save');
    echo "Color substitution: PASS (checkout, consent, permissions, concurrency, refund linking, print, no money movement)\n";
} finally {
    $_POST = $old_post; wp_set_current_user($old_user);
    foreach ($refunds as $id) { $o = wc_get_order($id); if ($o) { $o->delete(true); } }
    foreach ($orders as $id) { $o = wc_get_order($id); if ($o) { $o->delete(true); } }
}
