# Domestic free selection — 2026-10-23

The operator simplified the rule on 2026-10-23: at least four pairs, any larger
integer quantity, across products/colors/sizes/styles/cuff heights. There is no
4/6/12 tier selector in the new selection list. Prices shown are per-pair and
summed by quantity; no pricing explanation is required on the storefront.

## Pricing

The operator confirmed: use each variation's existing native per-pair price,
multiplied by quantity, with no volume discount. Catalog verification found the
existing domestic pack totals were already linear multiples of native prices.
No pricing migration is necessary and no native price/stock data is changed.
Prices are parsed into integer cents and multiplied by integer pair quantities.
No division by 4/6/12, tiers, or rounding allocation is used in new selections.
Old carts are supported, while product pages now send choices to the unified list.

## Implementation

Domestic `SelectionCart`, `SelectionDraft`, and selection UI modules use actual
WooCommerce variations. Inventory stays on those variations. The custom table
stores selection drafts only, with opaque anonymous cookies, account ownership,
revision checks, owner locks, and explicit resolution when login finds two lists.
Guest cookies expire after 30 days; inactive stored drafts expire after 180 days.

Server validation checks current availability, unit prices, real variation IDs,
stock including current cart quantities, and minimum quantity. Cart writes are
idempotent by group UUID, roll back partial additions, and keep native order line
quantities/attributes. Price changes invalidate checkout until reconfirmed.
No stock is reserved merely by saving a draft. Normal checkout stock reservation
remains WooCommerce's responsibility. Draft prices are refreshed on load; an
explicit notification of prices changing while away is not implemented yet.

The selection page has server-rendered forms with JavaScript enhancement, a saved
selection sidebar, mobile summary, and product-page entry. Existing pack group
removal/restore is reused. This remains a separate list added to cart together;
there is no global arbitrary cart-line editing conversion in this version.

## Validation completed locally

- `composer check`: lint, WPCS, existing unit/isolation checks passed.
- `bash tools/wp.sh eval-file tests/selection.php`: passed with disposable products
  and account, temporary pricing, and cleanup. Covers totals of 4/5/7/13/25,
  minimum rejection, fixed-unit sums, malformed input, stock aggregation, group
  tampering, rollback, retry, order metadata, guest isolation, stale revisions,
  CSRF, login transfer/conflict resolution, commit and editing from cart.
- `bash tools/wp.sh eval-file tests/mixed-packs.php`: passed; existing packs remain
  valid, including variant mixing, price/tax boundaries and removal/restore.
- Latest changed draft PHP and selection JavaScript syntax checks passed.

Local migration created page 377 and draft storage. Desktop/browser tests verified
2 pairs of 323 (6.90 each) + 3 pairs of X59 (10.90 each) = 46.50; reload restored
it, native Blocks cart showed matching line subtotals and total, and edit returned
it to the list without duplication. Adding one more X59 from its product page
produced six pairs and 57.40. Mobile 390px layout and summary navigation checked.
No real order or payment was placed. No-JavaScript fallback is implemented but
has not been browser-tested with JavaScript disabled.

Remote deployment/verification follows via the existing domestic sync script.
No international project changed. Existing payment/visibility settings preserved.

## Deployment result

Released via `tools/deploy/sync-code.py --apply` to domestic release
`20261023T071111298657Z`; previous release `20261023T003526152620Z`.
Remote apply/verify passed: page 202, price/stock fingerprint unchanged,
visibility remains `no` for coming-soon, existing payment/login settings unchanged.
Live HTTPS browser showed eight currently published products (local catalog has
additional products, which were not re-published remotely). Live five-pair 323
selection totaled 34.50, survived reload, and was cleared afterward. No live
cart/order/payment created. Product-page input now feeds the same saved list,
and native price HTML displays per-pair prices instead of old tier totals.
Screenshot: task output/selection/live-selection.png.
