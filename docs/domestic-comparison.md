# Domestic comparison repair — 2026-10-20

The six sample products had been published as products, but their parameter claims
remained drafts. Thus public comparison correctly excluded their claims and displayed
only commerce information. Native size/color choices were also missing from the
comparison template.

Changes:
- Compare reads the same published Repository projection as product specifications
  and numeric catalog filters. No bypass of draft/evidence visibility rules.
- `publish-domestic-sample-specs.php` reviews the archived domestic sample manifest
  and publishes 39 supported claims through Repository save/review/publish, with
  source receipt dates, private archived-source hash, scope and audit history.
  X59 composition/shoe size, 323 net weight and incomplete fibre percentages stay
  unknown. The unsupported 323 mesh and terry-location drafts stay unpublished.
- Workbook weights are one-pair net weights; unconfirmed size coverage and dimension
  endpoints remain explicit. J02/J03 figures stay product-group references.
- Comparison adds native available sizes/colors. Product-level selections can show
  existing child measurement values, with exact size/color scope in disclosures;
  this does not create parent measurements or transfer values between variants.
  Listing/package dimensions are not aggregated as sock measurements.
- Remove the WooCommerce customer-facing sentence; clarify that prices are per pack.
  Source and evidence details fold away; unknown cells are labelled 未提供.

Validation:
- Local real-catalog integration: 35 checks (guest data, native sizes, known/unknown
  values, private evidence exclusion, exact variation scope and all 11 products).
- `composer check` passed: syntax, coding standards, base tests, isolation boundaries,
  payment provider mocks and payment frontend tests. No payment/order submitted.
- Local browser verified the X59 / 323 comparison and expandable evidence.
- Apply is idempotent and checks commerce values and store visibility remain unchanged.

Commands (domestic target only):
```
bash tools/wp.sh --user=sockspecs_cn eval-file tools/deploy/publish-domestic-sample-specs.php apply
bash tools/wp.sh eval-file tests/domestic-comparison.php
python3 tools/deploy/sync-code.py --apply
```
After code sync, run the publisher with the domestic server's administrator WP CLI
from `/srv/sockspecs-cn/current/tools/deploy/publish-domestic-sample-specs.php`.

Live verification completed: release `20261020T233545610813Z`, publisher apply and
verify both confirmed 39 public claims and preserved commerce settings. The exact
reported URL (`?page_id=13&pc_compare=128,90`) now shows 323 cotton 80%, EU 39–44,
both products' needle/toe-seam details, and X59's 28 g / 170 mm / 85 mm workbook
references. Browser verification confirmed no WooCommerce customer copy.
