# Permanent model numbers — 2026-10-24

Approved scheme: SS-S0001..SS-S9999 for socks; SS-T0001..SS-T9999
reserved for future towels. Codes are independent of supplier references.
Existing counterparts receive the same code in both independent stores.

## Administration

Products → Edit → **Product model and sourcing** (domestic: 商品型号与采购信息).
The assigned SockSpecs number is read-only. Manufacturer model is editable and
private (`_pc_selected_supplier_model`). Each variation has the same private
manufacturer-model input in its expanded editor. Combined J02/J03 styles now
use public options A/B and retain J02/J03 on individual variations.

Candidates do not automatically consume a number. After saving a new candidate,
select Socks/Towels and check **Assign a permanent model number when saving**.
Native parent SKU becomes that number. Allocation uses a database advisory lock,
a monotonically increasing family counter, permanent number→product and
product→number options. A save failure retains the pending reservation for retry.
Deleting a product does not release its number; duplicate-product excludes the
public identity and old SKU. Native saves reject reassignment or manually reusing
reserved numbers. Exhaustion fails rather than wrapping. No custom database table
or schema version change.

The two stores remain operationally independent. For future counterpart imports,
coordinate the same public code explicitly using Product_Identity::assign() before
assigning an unrelated next number independently. No cross-store inventory/account
or runtime dependency was added.

## Migration and compatibility

`tools/deploy/renumber-products.php` accepts plan/apply/verify. It checks all
products and destination SKUs first, snapshots original data in private option
`pc_model_migration_20261024`, then uses native WooCommerce setters. A completed
apply rerun is verification only, preserving subsequent editorial changes.
Variants use parent-code-V001 etc.; `_pc_legacy_sku` preserves every old SKU.
No product or variation IDs changed. Price, stock, visibility, category, images,
size/color/cuff attributes and URL slugs are checked unchanged.

Existing names/descriptions use new codes. J02/J03 style values become A/B.
Private legacy-style metadata preserves pack-group hashes and upgrades old cart
session attribute spelling. Original source reports/images/evidence are not
rewritten; this is product identification, not an anonymity guarantee. Existing
URL slugs remain valid. Historical orders are not rewritten.

X70's low-cut diagram uses its saved legacy identity so renumbering does not
change its illustration. Google feed adapter continues using native product IDs
and current names/SKUs; downstream feed processing is asynchronous and was not
manually resubmitted in this task. Historical one-off scripts that locate products
by the former SKU intentionally fail closed; use IDs or the saved legacy metadata
when adapting those scripts, rather than rerunning old imports/stock initializers.

## Validation

- Both repositories: composer check (lint, coding standards, bootstrap/security
  fixtures, existing JS tests) passed using a disposable local PHP container.
- Local WordPress: 22 identity integration checks including allocation/retry,
  collisions, deletion/non-reuse, family isolation, unauthorized saves,
  immutable identity, original-field validation, Chinese editor rendering,
  variation field saving, duplicate exclusions, pack-link/cart compatibility,
  and four-digit exhaustion. Fixtures rolled back.
- Local migration: 11 parents / 97 variations, second apply is verify-only.
- Native mixed-pack and unified selection regression suites passed.
- Live CN: 11 parents / 97 variations. Live international: 11 / 85.
- Post-migration verification passed on both hosts; commerce invariant hashes
  and preexisting visibility unchanged. Both sites retained coming-soon `no`.
- Browser checked combined-style pages on both live sites: new title/SKU,
  A/B labels, no J02/J03 or old public code in visible text. Old URLs work.
- Read-only live editor render confirms original value, nonce and read-only public
  number in Chinese and English. No live test order/payment was submitted.

CN code release: 20261024T014759277714Z; previous 20261024T003323590752Z.
International code rollback ID: 20261024T014752Z-a830c6ceb74e.
Local private backups: `.local/model-numbering-20261024/`.
Use `before-and-mapping.serialized.b64` for complete PHP attribute objects;
JSON is a readable reference and does not preserve those objects. Data rollback
requires deliberate restoration of edited fields from the snapshot as well as
code rollback; keep permanent reservations to avoid allocating codes elsewhere.

## Mapping

| Original manufacturer model | Public model |
|---|---|
| X109 | SS-S0001 |
| X112 | SS-S0002 |
| 364 | SS-S0003 |
| 365 | SS-S0004 |
| X70 | SS-S0005 |
| 323 | SS-S0006 |
| X31 | SS-S0007 |
| X59 | SS-S0008 |
| X72 | SS-S0009 |
| X65 | SS-S0010 |
| J02-J03 | SS-S0011 |
