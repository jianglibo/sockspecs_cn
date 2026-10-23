# X59 colors and material — 2026-10-21

Operator supplied current model-specific listing options: White Gray, White Blue,
White Green and White Purple. Correct the four existing native variant attributes
and SKUs in place; preserve IDs, prices, stock, images and order history. Old-to-new
mapping follows Gray/Blue/Green, with old Pink corrected to Purple. No new variants
or stock initialization. Parent options and default choice update together.

Add dictionary v5 material_trade_name as an optional scoped enum (COOLMAX currently),
not a fibre percentage or performance result. X59's archived workbook names COOLMAX;
its declaration uses the existing review/publish API and same public projection for
product pages and Compare. Notes explain unknown composition, licensing and
performance. No X60/X61 combed cotton claims or unknown percentages are imported.
Dictionary schema stays at v1; the new metric is additive and empty elsewhere.

update-x59.php apply/verify enforces store/database/currency, source hash, four-child
identity and SKU collision checks; snapshots original data and preserves prices,
stock, imagery and visibility. Resumes by saved child/claim IDs without duplicates.
A local test caught native attribute objects being mutated before change detection;
cloning the attribute before updating fixed persistence. Both local sites passed
repeat apply after that repair. Regression tests are read-only and create no orders.

Each repo keeps its own localized source manifest. Deploy custom code through the
existing script; international dictionary deployment uses its reviewed file hash.
Transfer the international helper/manifest separately into its private import
folder; domestic sync includes its helper. Apply independently, never copy databases.
Rollback requires reviewed per-field restoration from pc_x59_correction_20261021
and retracting the new claim; do not restore whole databases over subsequent orders.

## Executed validation and deployment

Both local sites passed all 61 read-only X59 regression assertions. Deployment
checks passed through each repository's existing sync script. Both production
apply/verify runs completed successfully. Browser checks confirmed all four new
colors and the localized COOLMAX material row on both public product pages.

The first production preservation check flagged WooCommerce's derived
attribute_summary after the intended color rename. Auditing saved/current parent
and child data found only intended attributes, SKUs, names, timestamps and that
derived summary changed. Verification now checks the summary contains the corrected
color while preserving original price, stock and image checks. No baseline was reset.

International backup: /var/backups/sockspecs/snapshot-20261021T013532Z-bTHWcE
International code rollback release: 20261021T013612Z-0111527f171d
Domestic database backup: /var/backups/sockspecs-cn/x59-20261021/before.sql
Domestic final release: /srv/sockspecs-cn/releases/20261021T013824105259Z

Existing photos, prices, inventory and site visibility were preserved. No checkout,
payment or email was initiated. Matching X59 white-color photographs remain a
separate future update; X58 photographs were not substituted.
