# Product URLs using public model codes — 2026-10-24

The operator approved replacing existing product slugs on both independent stores
with the lowercase permanent public model, e.g. `?product=ss-s0007`.
The subsequent instruction explicitly excludes legacy-link compatibility work.
No custom redirect or alias system is added; WordPress retains its native behavior.

## Operation

Run `tools/deploy/model-product-urls.php` with WP-CLI `eval-file`, using
`plan`, `apply`, or `verify`. The helper validates the eleven identities and slug
collisions before writing. It saves the original slugs, edited content/menu fields,
visibility and commerce fingerprints in private option `pc_model_urls_20261024`.
Repeat apply verifies the completed migration without overwriting later content.
Native WooCommerce setters preserve product/variation IDs, prices, inventory,
attributes, status and images. Existing orders and carts remain attached to their
native IDs. Draft products stay draft. No rewrite-rule or DNS changes are needed.

Generated catalog/menu/product links and canonical URLs use the new slugs.
Same-site explicit links in posts, pages, products, reusable blocks, navigation,
templates and custom menu URLs are replaced if present. External links and media
filenames are not rewritten. The live plans found no explicit content/menu links
requiring replacement on either site. Historical import scripts and source archives
remain historical records, not runtime link sources.

The older numbering migration intentionally checked that slugs had not changed;
its historical `verify` command is no longer the appropriate verifier after this
separately approved URL change. Use the URL migration verifier instead.

## Validation and rollback

The local WordPress URL rewrite tests cover query links, extra pack/attribute
arguments, fragments, HTML entities, Gutenberg JSON, path links, similar-name
boundaries, external hosts and image filenames. Apply and repeated apply are tested
against the local catalog before live deployment. The deployment scripts run their
normal source checks. Live verification checks published product URLs, canonical
links, catalog links, unchanged commerce fingerprints and draft status.

To roll back deliberately, restore slugs and only the edited fields from the private
snapshot using WordPress/WooCommerce APIs. A code rollback alone does not restore
product URLs. No customer, order, payment, stock or pricing migration is performed.

## Execution results

Local migration updated 11 parents, repeated apply performed verification only.
Ten URL rewrite cases and Google-status/price-change fingerprint tests passed.
Both deployment scripts passed their required checks; international runtime code
was already synchronized (this change is a native catalog data migration).

International Google for WooCommerce marked products/children from synced to
pending on save. The initial strict metadata fingerprint caught this expected
status change. A read-only reconstruction matched every original fingerprint by
changing only that one status field back in memory; no price/stock/attribute
changes occurred. After the queued sync completed, its timestamp also changed. Version 2 fingerprints
exclude only Google sync status and timestamp; all other metadata and commerce
fields remain checked. The snapshot upgrade independently verified all 204 parent
and variation commerce fingerprints against the earlier numbering snapshot,
substituting only the old slug in memory. Initial full-metadata hashes are retained
in the snapshot. No live product metadata was reset to bypass the check. Google processes its queued feed update asynchronously.

Final live checks: domestic 8 published pages return 200 with new canonical URLs;
3 draft pages remain 404. International 11 published pages return 200 in the
browser with new canonical URLs. Domestic catalog has 16 and international
catalog 22 product links, all using public-code slugs. Both migration verifiers
pass. International generic HTTP requests encountered Cloudflare 403, so actual
browser navigation was used for all 11 products and the catalog. No live order
or payment was submitted. Private URL snapshots exported under
`.local/model-numbering-20261024/url-migration.json` in each repository.

Final domestic code release: `20261024T051358770077Z`. International runtime
code remained identical; the tested catalog helper applied the data update.
