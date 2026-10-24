# Domestic admin sourcing lookup — 2026-10-24

Scope: domestic store only. The operator deferred international synchronization.
No public product, price, inventory, identity, URL, brand taxonomy or supplier
record is changed, and no new schema or data migration is required.

## Staff UI

- Products: original manufacturer model column after SKU; supplier column replaces
  the brand column. Supplier code/name links to the private supplier editor.
- The normal product search also matches private original models, including models
  stored on variations, while retaining native name/SKU/ID results.
- Parameter Specs: an original-model text filter combines with product and metric
  filters. Claim and pagination links retain it. Matching is case-insensitive and
  allows partial text; `%` and `_` are literal characters, not SQL wildcards.
- The product selector also finds original models and shows them in staff labels.
  A combined product is returned by either variation's manufacturer reference.

Existing `_pc_selected_supplier_model`, `_pc_supplier_id`, and supplier code/name
are reused. The manufacturer query is capability-gated. The native request hook
runs only in the admin product list after WooCommerce's own search. No public
search, REST field or storefront supplier projection is added. Brand values remain
available in the native editor; only the list column changes.

## Validation

`composer check` covers lint, WordPress coding standards and existing domestic
regressions. `tests/admin-sourcing.php` runs in local WordPress with temporary
fixtures inside a rolled-back transaction: native product search, SKU compatibility,
parent/child references, literal wildcard/SQL input, supplier columns, combined
parameter filters, empty results, retained filter links and unauthorized access.

Deploy with the existing `tools/deploy/sync-code.py --apply`; verify product list
and parameter filter on the live domestic admin. Do not deploy international code.

Validation completed: Composer checks and 23 local WordPress assertions passed.
Live authenticated HTTP checks confirm the Chinese original-model/supplier columns,
absence of the brand column, searches for X31, 323, J03 and SS-S0007, and combined
original-model + foot-length metric filtering. Native server-rendered forms and
links were checked; no screenshot or live AJAX picker interaction is claimed.
An initial live test used cotton_pct, which has no saved X31 records; verification
was corrected to an existing metric (foot_length_mm), which returned the expected
records. The verification session was logged out. No product values were edited.
Domestic release: `20261024T052118423346Z`.
International repository/runtime were not changed for this feature.
