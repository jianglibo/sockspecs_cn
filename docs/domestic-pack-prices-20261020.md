# Domestic sample pack prices — 2026-10-20

Operator approved domestic 4/6/12-pair packs, default 4, using the six quoted
retail prices. International code/data are untouched. No shipping policy was
approved by the question about feasibility: shipping settings remain unchanged.

| Model | Pair allocation CNY | 4 pairs | 6 pairs | 12 pairs |
|---|---:|---:|---:|---:|
| 323 | 6.90 | 27.60 | 41.40 | 82.80 |
| X31 | 9.90 | 39.60 | 59.40 | 118.80 |
| X59 | 10.90 | 43.60 | 65.40 | 130.80 |
| X72 | 10.90 | 43.60 | 65.40 | 130.80 |
| J02/J03 | 11.90 | 47.60 | 71.40 | 142.80 |
| X65 | 14.90 | 59.60 | 89.40 | 178.80 |

The shared J02/J03 parent has pack totals. After the operator confirmed identical
J02/J03 prices, all four J02 and five J03 variants receive the same native price
allocation (CNY 11.90). Both model groups remain distinct and unpublished. User subsequently confirmed X58–X61 share procurement prices;
X59's planning cost is CNY 7.56 in the 5–499 tier. This does not equate their
materials/construction. Native prices allocate one pair within packs, not a
single-pair storefront offer. No volume discount is added.

All six parents stay draft and retain the domestic preview purchase block.
Stock quantities, stock management, specifications, weights, other five products,
shipping, launch settings and public DNS remain unchanged. Pack opt-in is staged;
actual checkout still requires confirmed variation inventory and other readiness.

## Operation

Administrator WP-CLI: eval-file tools/deploy/price-domestic-samples.php plan,
apply, verify. Guards domestic database, URL, CNY and original sample identities.
The helper uses WooCommerce CRUD, records before-data in non-autoload option
sscn_sample_prices_20261020, and verifies a fingerprint of every non-price product
field and store/shipping settings. It refuses existing prices. Interrupted writes
require review of the recorded before-state, not a silent retry/overwrite.
Repeat completed apply performs verification only. Historical sample import and
quote full-catalog fingerprints predate this authorized pricing; use this newer
scoped verifier for current prices and unchanged fields.

Back up before applying. Local backup:
.local/backups/sample-prices-20261020/before.sql.
Sync custom code with the existing tools/deploy/sync-code.py --apply, then run
server helper from /srv/sockspecs-cn/current/tools/deploy as sockspecs-cn.
Rollback restores only the recorded price/pack fields after checking no later
edits/orders depend on them; never restore a whole database over new transactions.

## Validation

Composer checks and pack-builder JS regressions passed. Local WooCommerce
integration tests/domestic-packs.php creates/removes its own fixtures, verifies
4/6/12 mixed-color cart totals and repeat calculation, rejects unsupported sizes
and wrong quantities, checks Chinese form/default/GET selection, editor save/clear,
and confirms real drafts remain unavailable. No real inventory is fabricated.
Server data verification follows deployment. No payment/carrier end-to-end claim.

## Shipping feasibility assumptions, not carrier quotes

323 four-pair revenue 27.60; procurement 16.40 at the five-plus purchase tier.
Assume outbound freight 5.00, packing 1.00, inbound allocation 0.80, payment 0.6%
and after-sales reserve 3% of revenue: remaining about 3.40 before tax, fixed cost,
labor and acquisition. Freight 8.00 leaves about 0.40. Free shipping is feasible
only with controlled freight; no nationwide free-shipping promise is configured.

## Executed release

Local and server apply/verify passed, including non-price/settings fingerprints.
Domestic release: /srv/sockspecs-cn/releases/20261020T002904634838Z;
previous release: /srv/sockspecs-cn/releases/20261020T001332894886Z.
Server SQL backup: /var/backups/sockspecs-cn/sample-prices-20261020/before.sql.
The domestic origin homepage returned HTTP 200. All six drafts returned JSON
404 woocommerce_rest_product_invalid_id to unauthenticated WooCommerce Store API
requests through the origin's real ?rest_route= endpoint (plain permalinks).
Direct workstation HTTPS probes failed during TLS handshake, so this run does not
claim an external HTTPS/browser check. No proxy/certificate/API configuration was
changed. Local WooCommerce form/cart integration passed as documented above.

## J03 equal-price confirmation

The operator confirmed J02/J03 supplier prices are identical and listed four J02
colors (black, blue, coral, gray) and five J03 colors (black, dark blue, white,
light blue, gray). Existing attributes match exactly. Supplier style labels remain
declarations; no new verified quick-drying or anti-pilling claim is published.
Both use the earlier operator-selected procurement budget CNY 8.42/pair.

price-j03.php (plan/apply/verify) amends only five J03 native prices, synchronizes
the parent price range, and appends the private supplier confirmation. Its option
sscn_j03_prices_20261020 stores the before-state and exact non-price/store-setting
fingerprints. Raw earlier quotes remain intact; the latest dated supplier note
supersedes the earlier J03 exclusion. The main sample-pricing verifier recognizes
the completed amendment. Local apply and verification passed.

Local backup: .local/backups/j03-prices-20261020/before.sql.
Server backup: /var/backups/sockspecs-cn/j03-prices-20261020/before.sql.
Rollback requires reviewing the saved before-state and restoring only the five
J03 price fields and supplier amendment, then syncing parent prices; do not restore
the entire database over later activity.

J03 amendment deployed and verified on the domestic server. Release:
/srv/sockspecs-cn/releases/20261020T004735693698Z. Composer checks passed;
both amendment and full sample-price verifiers passed with all 33 variations
priced and all six parents still draft. No shipping or inventory changes.
