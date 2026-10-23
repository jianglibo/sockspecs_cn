# Domestic mixed-variant packs — 2026-10-21

Operator approved mixing all variation attributes (including J02/J03 styles) within
one parent product at the same single-pair price and tax treatment, totaling exactly
4, 6 or 12 pairs. This supersedes the earlier same-model restriction.

The purchase form renders all native size/cuff groups together. Quantities remain
in one form instead of resetting on a group-navigation request. The Chinese name
is now 自由搭配套装. Dimensions/specifications open in another tab so the current
selection remains intact; existing pc_pack_group links still scope the diagram.

Compatibility is computed server-side from the parent, current native single-pair
price (six-decimal normalization), and tax treatment. No variation attribute is
special-cased: J02/J03 model differences are treated just like color, size and cuff
height. Display groups and parameter scopes still preserve those distinctions.
Client checks give immediate feedback but are not authoritative. Existing native
cart/checkout revalidation applies the same rule after price or availability changes.
Fixed 4/6/12 pack totals, native per-variation inventory, order attributes and atomic
pack removal/restoration are retained. No catalog, prices, stock or site gates changed.
X31 mid/low stock remains blank/out of stock and cannot be selected for purchase.

Validation: composer check, pack-builder-js.js, and local WordPress integration
mixed-packs.php, domestic-packs.php, cuff-height.php. Fixtures test mixed size/cuff
quantities, exact rounded totals, same-color different-size variants, model/price
boundaries, unavailable/insufficient stock, stale price checkout rejection, native
attributes/order pack metadata, atomic removal/restoration, and dimension scoping.
Browser verification uses local X109 M+L selection and live read-only form checks.
No paid transaction or production test order is created.

Deployed with the existing script: release `20261021T221422764617Z`, rollback
`20261021T220315376775Z`. Live X109 displays all M/L quantities, accepts an M2+L2
selection and enables its 4-pair add button at ¥59.60. Local browser cart verified
native M2/L2 lines and ¥59.60 total. Screenshot: `/tmp/sockspecs-mixed-pack-cart.png`.

Live stock recheck: the earlier X31 activation snapshot verifier no longer matches
current product data. A direct read-only check found five new variants now have
60 pairs (mid orange/blue/green/purple, low purple), while seven remain null/out
of stock. This task deployed code only and did not write product inventory. Preserve
these current values; do not reapply the activation snapshot or reset stock.


Follow-up: same-product style mixing

- Removed the attribute map from mix compatibility; parent, price, tax status and
  tax class remain mandatory matches. Chinese guidance and rejection text updated.
- Local mixed-packs fixtures now combine model A/B in all 4/6/12 packs, checking
  exact totals, native variation quantities/attributes and order pack metadata.
- Added tax-class changes rejecting both new selections and existing carts; tax
  status (inherited by WooCommerce variations) and foreign-parent keys are tested.
- Production deployment changes custom code/translations only, not product copy,
  prices, inventory or visibility. No international changes.

Follow-up validation passed: local mixed-packs.php, domestic-packs.php,
pack-builder-js.js and full composer check. Deployed as
`20261021T232050579491Z` (rollback `20261021T225608618861Z`). Live browser accepted
J02 black x2 + J03 black x2, displayed 4/4 without a compatibility error, and added
both native variation lines to one pack in the cart at CNY 47.60. No checkout/order
was submitted; the test pack was removed. Screenshot:
`/tmp/sockspecs-j02-j03-mixed-cart.png`.
