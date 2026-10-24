# Bilingual header logo — 2026-10-24

Operator approved placing the selected bilingual logo on both storefronts.
Each repository owns its own copy and deployment; no shared runtime or data.

Asset: transparent WebP, 720 × 239 pixels, 34,194 bytes. Source is the approved
image-generation preview `exec-44b06610-a27a-4310-b631-251236bdb3aa.png`.
Preparation trims transparent outer padding and resizes/compresses the PNG;
the logo artwork and wording are unchanged. Original remains in generated_images.

Domestic: theme header home link now contains the logo with Chinese/English alt
text; 220px desktop / 190px mobile width. Theme CSS version bumped to 0.1.9.
International: header template-part render filter replaces only the native
site-title link contents, retaining the home link and surrounding markup. The
footer and admin remain unchanged. Display width ranges from 170px to 220px.
Deployment accepts this exact reviewed WebP path; other image paths stay blocked.

No favicon, product, commerce, visibility or database setting changes.

Validation: PHP lint, coding standards and existing composer checks passed for
both repositories in an isolated temporary PHP container (host PHP is absent).
Domestic deployed using tools/deploy/sync-code.py --apply, release
20261024T003323590752Z; previous 20261023T071111298657Z.
Domestic browser checked at 1440px and 390px: image loaded, readable bilingual
wordmark, seven navigation links retained, no horizontal overflow.

International deployment completed with the existing sync script; rollback ID
20261024T003417Z-e21e5cd0f121. All 20 deployment tests passed, plus existing
pack-builder and Google-login JavaScript checks. The initial deployment attempt
stopped before connecting because host PHP was unavailable for deployment tests;
a temporary container-backed PHP command resolved it without bypassing checks.
International browser checked at 1440px and 390px: logo loaded, home link preserved,
no horizontal overflow, mobile navigation opens/closes normally.
Both sites retained their preexisting woocommerce_coming_soon value `no`.
The local WordPress preview was not running; visual validation was on the deployed
sites after source checks. No transaction/payment tests were performed.
