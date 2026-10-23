# Thickness, terry distribution and shoe pairing — 2026-10-21

User-authorized addition to both independent stores. Schema stays at 1;
dictionary 6 adds optional thickness_grade and footwear_fit enums and extends
existing cushion_location instead of duplicating terry data. No actual product
values are created or inferred. Existing approved terry evidence remains visible.
Terry-cushioning is explicitly coverage unspecified; it is never converted to
full-sole coverage. Net pair weight remains independent.

## Editing after sample assessment

Use WooCommerce → Parameter Specs (domestic 商品参数), select product/variation,
then save, review and publish through the existing evidence workflow. Blank or
unpublished values stay hidden on product cards and pages. New optional fields:

- thickness_grade: thin, moderate, thick. Relative wearing thickness, not a
  quality ranking. Document a consistent comparison method; do not infer from grams.
- cushion_location: no-terry, partial-terry, terry-sole, full-terry; retained
  terry-cushioning means coverage unspecified. Record partial coverage locations.
- footwear_fit: close-fitting-shoes, regular-fit-shoes, roomier-shoes. Record
  the actual shoes/sizes assessed; it is guidance, not guaranteed fit.

Manufacturer declarations retain their provenance. For the domestic store,
qualitative sample observations now use platform_observed; see
sample-observation-20261021.md. Formal measurements retain platform_measured
and the actual method/version, date, sample count, batch and conditions requirements. Write exact sample size/color scope. Do not
call an observation a manufacturer declaration. Own sample records do not
implicitly apply to all sizes/colors; only existing explicit manufacturer model
wide declarations inherit. Specific variation records take precedence.

## Presentation and filtering

One canonical published ledger serves summary near purchase options, native and
custom catalog thickness labels, detailed specification table, Compare and three
catalog dropdowns. Summary lists any exact child records with their scope, so
changing a pack size cannot leave a misleading unqualified label. Block and
classic product summaries are supported; the current custom catalog and classic
shop cards are supported. No JavaScript dependency is added. Empty summaries and
rows render nothing. Variant-only thickness on cards is qualified as recorded
options only; no universal parent value is invented.

Filters validate allowed enum values and dictionary filter flags. All selected
wearing filters are evaluated against the same native option's public projection,
including exact overrides. Unknown is not no-terry. Existing numeric/size/price
filters retain their behavior. Compare shows scoped child wearing records as well
as parent values and uses existing source/evidence details. No numeric equivalence
or fit guarantee is implied.

## Validation and deployment

Local-only tests/wear-profile.php creates clearly marked temporary products and
uses normal claim publication APIs. It checks blanks, private drafts, exact scope,
model inheritance, cross-consumer display, combined filter exclusion, input
validation, retraction and visibility. Finally deletes only its generated local
fixtures. Never run this fixture test on production. Real new fields are asserted
blank before fixture creation. No orders, emails or payment requests are created.

Compile each repository's own Chinese translation file; do not copy site data.
Use each existing sync script; international dictionary deployment requires the
exact reviewed class-metrics.php SHA-256. Code rollback uses previous releases;
additive dictionary entries may remain unused. Existing prices, stock, visibility,
credentials, public DNS and gateway gates must remain unchanged.

## Executed results

Each local site passed 39 new wearing-profile checks, 35 comparison regression
checks and 61 X59 preservation checks. Both existing deployment scripts completed
their required Composer checks; international JS and all 20 deployment tests passed.
Initial validation caught markup-only enum input being sanitized to blank; raw
wearing-filter inputs now reject that mismatch. Existing bootstrap hook inventory
was updated for the new native shop-card hook. No production fixtures were used.

Browser verified product summary and all three catalog filters in Chinese and
English. Unknown thickness and shoe-pairing values remain absent. Existing X70
terry-sole declaration appears beside the purchase area. Screenshot:
/tmp/sockspecs-wear-guide-cn.png. No mobile browser session or payment test was run.

Before/after production commerce hashes matched for products/variants, status,
name, SKU, prices, inventory, images, attributes, currency, home and visibility.
Both servers retain zero thickness_grade/footwear_fit claims and the original six
cushion_location claims. Only dictionary version advanced from 5 to 6.

International backup: /var/backups/sockspecs/snapshot-20261021T020217Z-bp3Ip9
International rollback code: 20261021T020341Z-c1a5c2f058eb
Domestic DB backup: /var/backups/sockspecs-cn/wear-20261021/before.sql
Domestic release: /srv/sockspecs-cn/releases/20261021T020341755616Z
Domestic previous release: /srv/sockspecs-cn/releases/20261021T013824105259Z
Reviewed dictionary SHA-256:
ff369934be57f70f484ba2868bc0708d74b60a2d1a9aef6efaccbae568734ac7
