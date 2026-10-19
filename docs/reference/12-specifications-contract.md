# Product specifications contract — M1 interface and M2 public extension

Current five-model consolidation and explicit declaration applicability are documented in
[shared structured specifications](42-shared-structured-specifications.md).

Historical M1 scope follows. M2 now supplies the public wrapper, table and native
variation updates; see [M2 operations and QA](13-m2-public-specifications.md).
The original `specifications()` method remains staff-only. Its anonymous sibling
`public_specifications()` enforces public product/variation access before using
the same data projection. No schema migration was introduced.

The public WooCommerce product page must present a structured specification
table in M2. M1 supplies the data interface below. It does not add a public
route, product-page hook, table template, or variation-selection JavaScript.
Comparison remains M4 and is not part of this change.

## M1 PHP interface

```php
// Requires a logged-in user with manage_woocommerce; no public route is registered.
$product_records = \ParameterCommerce\Repository::specifications( $product_id );
$size_records = \ParameterCommerce\Repository::specifications( $product_id, $variation_id );
```

Arguments accept nonnegative integer IDs (or digit strings); product ID must
identify an existing parent/simple product. A nonzero variation must belong to
that variable parent. Invalid scopes raise `Failure` with status 422, unauthorized
access 403, and unavailable schema/dependency/storage 503. Failure to load storage
must not be presented as an absence of measurements.

This interface is staff-only during M1. Staff may inspect draft/private WC
products. Its output must not be passed straight to an anonymous endpoint in M2:
that milestone must enforce WooCommerce product/variation visibility, publication,
password/access restrictions and unavailable-variation handling independently.
Neither this contract nor `Repository::preview()` is public authorization.

The result contains:

| Field | Meaning |
| --- | --- |
| `wc_product_id`, `wc_variation_id` | Exact requested WC scope; variation 0 means product-level records only |
| `scope_type` | `product` or `variation` |
| `sku` | Current SKU from the requested WooCommerce product/variation |
| `inherits_parent_records` | Always `false` |
| `groups` | Ordered `composition`, `material`, `construction`, `measurement`, `performance`, each with a translated `label` and registry-keyed `metrics` |

Each metric row has its registry `label` and `type`, nullable `value`, `unit`,
`availability` (`available` or `not_yet_measured`), translated `missing_label`
(**Not yet measured / 尚未测量**), nullable `source_label`, and nullable `record`.
All 16 registered metrics have rows, even if the requested scope has no records.
Numeric values stay exact decimal strings; enums stay strings; missing stays
PHP/JSON null. Explicit numeric zero remains available. A dictionary unit can
remain present for a missing metric; variable test units remain null until known.
Do not substitute an assumed unit, method or value.

`record` carries the existing safe preview fields: value/unit, source type,
status, scope and specimen size, method code/version, conditions JSON, sample
count/batch, source/measurement date, public limitations, evidence title, and
permitted public excerpt/attachment URLs. Text is **not HTML**; M2 must escape it
for its rendering context. Private source URLs, source organization, internal
notes, reviewer IDs, and audit data are excluded. A private report may have an
approved public evidence title, without a public report URL.

Eligibility requires a current publication slot, `status=published`, a recorded
approval (`verified_by > 0`), publication time, evidence snapshot, and a non-null
value. Draft, reviewed-only, retracted, superseded and unapproved rows are absent.
The normal write workflow still validates provenance before review/publication.
Approval means staff approval of the record, not independent testing or scientific
certification. Source tiers remain the existing stored values:

- `supplier_declared`: Manufacturer-declared.
- `platform_measured`: Platform-measured.
- `third_party_lab`: Third-party-tested.

## Scope and M2 rendering requirements

1. Use a semantic, responsive table on the native WooCommerce product page,
   grouped into material composition, construction, physical measurements, and
   performance. Include units, recorded method/version and relevant provenance.
2. Render only eligible records. Product pages hide empty metric rows and groups
   (see `41-unified-product-details.md`); the API projection retains nulls and
   **Not yet measured** labels for other consumers;
   keep a declaration clearly labelled as a declaration, never a tested result.
3. For a simple product, use its product scope. For a variable product before a
   selection, identify any parent records explicitly as product-level/sample
   records; do not describe them as measurements of every size. Ask the shopper
   to choose a size/SKU for variation-specific measurements.
4. Once a variation is selected, resolve that exact child. Missing child metrics
   stay missing even when parent or sibling values exist. The current model has
   no explicit all-sizes applicability flag, so there is no implicit fallback.
5. Respond to native WooCommerce variation selection, changes and reset. Clear
   stale rows immediately; ignore responses belonging to a previous selection.
   Invalid/unavailable selections must not retain another SKU's measurements.
6. Preserve native product templates, price/stock/cart flows, keyboard access,
   responsive table readability, and an understandable server-rendered baseline.
   Verify classic and applicable block product templates against installed WC.
7. Do not add comparisons, rankings or inferred durability; comparison is M4.

## Installation, tests and manual QA

Replace the plugin files as usual. There is no schema migration, new option,
new capability or measurement-model change. Schema version remains 1. Rolling
back this interface change does not alter stored claims or evidence.

Run `composer check` and `bash tools/wp.sh eval-file "$PWD/tests/integration/m1.php"`
in the disposable local environment. The integration test uses only DEMO draft
products, exercises the real publication workflow, and removes fixtures afterward.
It tests all groups, source tiers, methods/units/sample metadata, null versus zero,
publication/approval/retraction/supersession, private evidence, and exact parent/
child/sibling scope with no inheritance. The ordinary integration runner includes
these assertions automatically.

For manual M1 QA, create a disposable DEMO variable product, record different
published values for the parent and one variation, and leave another variation
unmeasured. Call the interface as authorized staff for each scope. Confirm the
second variation stays null and private evidence URLs are absent. Retract the
first variation's measurement and confirm it disappears immediately. Keep DEMO
products as drafts and delete them after QA. Public table, mobile/template and
variation-selection browser acceptance checks must run during M2; they are not
claimed as completed here.

Verification on the local PHP 8.3.6 / WooCommerce 11.2.1 baseline:

- `composer check`: passed PHP lint (17 files), WPCS (10 shipped files), and
  four bootstrap scenarios; the expected hook list remains unchanged.
- `bash tools/wp.sh eval-file "$PWD/tests/integration/m1.php"`: **138 assertions
  passed**, including storage failure returning 503 instead of fabricated missing
  measurements. DEMO fixtures were removed by the test's cleanup block.
- Chinese translation catalog compiled successfully (171 source strings).

Four warnings originated from the installed TikTok plugin's `tt4b_menu.php`
during temporary product removal; that plugin was not changed. Hosted CI,
MariaDB, public rendering, and browser variation-update tests were not run for
this M1-only interface change.

Material fields and scope/count validation are documented in [material differences](43-material-differences.md).
