# Domestic publication and inventory — 2026-10-20

Operator explicitly authorized publishing all domestic products and setting each
variant's stock to 60 pairs, with fulfillment possible if orders arrive.
This supersedes earlier draft/empty-stock requirements for these products.

Scope: 11 native variable parents, 85 native color/size/model variations.
Each variation independently manages 60 pairs, no backorders; total 5,100 pairs.
Variable parents do not maintain a duplicate stock ledger. All parents published
and visible. All existing prices, colors, sizes, images, descriptions and claims
are preserved. This does not approve or publish unreviewed specification claims.

Six recently priced products (323, J02/J03, X31, X65, X72, X59) have the domestic
preview purchase block removed and support 4/6/12-pair cart building. The earlier
five (X109, X112, 364, 365, X70) still have no domestic prices: their prices remain
blank, their specification preview marker remains enabled to preserve access to
variant evidence, and the storefront says price pending confirmation. Their stock
is nevertheless set to 60 per variation, and products are published.

Shipping methods, payment gateways, coming-soon setting, public DNS, proxy and
international site remain unchanged. This is not payment/fulfillment activation.

## Operation and recovery

Administrator WP-CLI eval-file tools/deploy/publish-domestic-catalog.php with
plan/apply/verify. Guarded to domestic CNY database and known imported identities.
The non-autoload option sscn_publish_stock_20261020 retains complete before-data,
non-target-field fingerprint and store-settings fingerprint. First apply refuses
existing quantities/managed stock; repeats only verify and never refill sold
stock. An interrupted apply requires review of its recorded before-state. A
later order reducing stock can make the exact-initial-stock verifier fail; this
is intentional, and must never trigger a reset to 60.

Backups: local .local/backups/publish-stock-20261020/before.sql;
server /var/backups/sockspecs-cn/publish-stock-20261020/before.sql.
Rollback must account for any new orders before restoring per-variation stock;
never restore the whole database over orders. Earlier import/pricing verifiers
assert historical draft/empty inventory and are superseded by this verifier.

## Validation

Local apply and field-preservation verification passed. Guest integration tested
21 mixed-color pack/cart combinations (three sizes across seven model groups,
including separate J02 and J03). No orders or payment attempts were created.
Tests/domestic-packs.php accepts the explicit publication operation while still
checking draft protection before it. Initial known stock is verified again after
cart tests. External checkout remains dependent on domestic delivery and payment.

Executed: local and server apply/verify passed. Composer syntax, coding standards,
bootstrap and domestic-isolation checks passed. Release:
/srv/sockspecs-cn/releases/20261020T005356729788Z; prior release:
/srv/sockspecs-cn/releases/20261020T004735693698Z.
Anonymous origin HTTP Store API returned all 11 expected product SKUs. All six
priced product pages returned HTTP 200 with the pack form and default 4 selected.
This origin-level check does not claim an external TLS/browser payment test.
No orders created and no international resources changed.
