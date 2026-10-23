# Updated purchase rule

The original single-size/cuff pack restriction below is superseded by
[mixed-variant-packs.md](mixed-variant-packs.md). Dimension scopes remain unchanged.

# Optional cuff-height variants — domestic store

Implemented 2026-10-21. Uses WooCommerce's native global attribute `pa_cuff-height`, labelled 袜筒款式, with optional terms 低筒款 (`low`), 中筒款 (`mid`), 高筒款 (`high`). The guarded `tools/deploy/setup-cuff-height.php apply` installs only the attribute/terms and never assigns them to products. Repeating it is safe; conflicting existing labels stop the operation.

## Editing confirmed products

1. Product → Attributes: add 袜筒款式 only when the supplier confirms independently selectable cuff styles. Mark it as used for variations. A length difference caused solely by shoe size is not a separate cuff style.
2. Under Variations create only confirmed color × size × cuff-style combinations. Assign each its own SKU, pair stock, price and shipping weight. Do not blindly generate unavailable combinations. Existing pack validation supports at most 100 variants per parent.
3. In 商品参数 select the precise variation and record/publish its catalog height, foot length and net pair weight with source and scope. Unknown measurements remain blank. Do not copy product-level measurements into variants without evidence.
4. Pack selection combines non-color attributes in one selector, then allows colors within that group. Native stock remains per variation. Existing fixed 4/6/12 pack prices apply to every group; different cuff-specific pack prices are not introduced here.

Shape and net-weight displays follow the pack group's server-rendered selection. Switching the selector reloads the read-only page using the existing progressive enhancement. Structured diagram rows use all non-color attributes, avoiding same-size cuff-style collisions. Missing dimensions in any color of the selected cuff/size group hide that group's diagram rather than substituting a different group. Weight entries retain the existing partial-coverage color labels. Specification and comparison scope labels include all native attributes when cuff height is present (WooCommerce may omit attributes from generated variation names with three axes).

The 19×18 cm operator sample does not create or alter X109 variants. No current product is assigned this attribute until the actual style/size/color availability is confirmed. Existing product status, prices, stock and visibility are preserved. This does not resolve the separate legacy model-level dimension import gap for X31/X59/X65/J02-J03.

## Verification

- `bash tools/wp.sh eval-file tools/deploy/setup-cuff-height.php apply --user=sockspecs_cn`
- `bash tools/wp.sh eval-file tests/cuff-height.php --user=sockspecs_cn`
- `node tests/pack-builder-js.js`
- `composer check`

The integration test creates and removes a local-only disposable three-height × two-size × two-color fixture. Checks cover six separate groups, within-group color selection, cross-height/size rejection, selected shape/weight, missing measurement isolation, malformed query fallback, scope labels and existing X109 regression. `preview` retains the fixture briefly for browser verification; `cleanup` removes it. Never deploy the fixture.

Browser verification confirmed high/M → low/M updates dimensions from 19×18 to 8×18 cm and net weight from 49 to 38 g using synthetic test data, with no purchase. Screenshot `/tmp/sockspecs-cn-cuff-height-test.png` is a local test, not an actual product.

Deploy code using `python3 tools/deploy/sync-code.py --apply`, then run the guarded setup helper with `apply` and `verify` on claw-host. No international changes.

Deployment completed: `20261021T215236898698Z` (rollback `20261021T212858631862Z`). Server attribute apply/verify passed; product/variation data, all claim records, currency and store visibility fingerprints were identical before and after attribute installation. Live X109 still shows its existing size options, dimensions and net weights; no cuff options were added to real products. Local disposable fixture was removed after the browser check, and the final integration test cleaned up its own fixture.
