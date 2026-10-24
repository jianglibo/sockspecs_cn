# Domestic order color substitution — 2026-10-24

Checkout has an optional, initially unchecked order-specific permission to replace
unavailable colors with another color of the same style, size and cuff length.
Actual substitution earns a single CNY 2 refund per order, including when consent
is obtained after checkout. No checkout discount, coupon, gateway call, stock
change or product change is made by this module.

`sscn-commerce/includes/color-substitution.php` supports the current Checkout
Block via the native additional-checkout-field API, plus classic checkout.
The exact permission wording, version and timestamp are snapshotted on the order.
A separate order fulfillment record stores later consent, actual replacement text,
actor, timestamp, revision and one refund ID. Updates require order-edit capability,
a nonce and a current revision, and serialize under the existing order lock.
No actual replacement can be recorded without consent and a description.

The “缺货换色” order box works with HPOS and legacy order storage. It preserves
the original checkout decision; later agreement is a separate checkbox. Save via
the normal order Update button. Record actual replacement model/color/quantity;
only color may change. This is a manual fulfillment log, not a stock reassignment
or automatic variant substitution engine.

Refunds remain handled at the merchant platform. After transferring CNY 2 back,
record a native manual WooCommerce refund for exactly CNY 2 with reason
“缺货换色补偿”, then select that refund in the order box. A refund must belong
to this order and have the correct amount. One refund can be linked, and a valid
linked refund cannot be replaced or unlinked. The module never creates refunds;
separate native/manual refunds remain the operator’s responsibility. Display
says “已登记” rather than asserting provider confirmation. Deleted native refund
records no longer count as completed compensation.

No print plugin was active on the store. An authorized, nonce-protected
“打印订单／配货单” page includes original order lines/specs, delivery information,
consent, actual replacement and compensation status. It uses no external assets,
is non-cacheable, and is accessible only to users allowed to edit that order.
Customers see saved replacement information in their order and order emails;
old orders with no record do not acquire assumed consent.

Validation: local disposable orders cover Blocks request hooks and registration,
classic checked/unchecked input, no price changes, later consent, unauthorized
updates, stale revisions, unchanged-repeat saves, refund ownership/amount,
duplicate-link protection, deleted refunds, print output and nonce rejection.
No live orders, refunds or payment calls are used for testing. Product quantities,
prices, visibility and gateway configuration are unchanged.

Deployment: release `20261024T112403222754Z`, previous
`20261024T084638482736Z`. Full Composer checks passed. Post-deployment field,
order-save and print hooks were verified; product/status/price/stock and store
visibility/gateway settings fingerprint matched before deployment. Live product
page returned HTTP 200. Local browser confirmed the unchecked option, checked
option with unchanged CNY 43.60 total, HPOS order save/reload and printable
fulfillment details. The disposable demo order was removed. No real checkout,
provider refund or live order edit was performed.
