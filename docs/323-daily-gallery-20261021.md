# 323 daily presentation and native gallery — 2026-10-21

Domestic-only update authorized by operator. Based on the operator's physical
sample comparison: 323 feels lighter, with broadly uniform sole thickness;
X59 has thicker forefoot/heel areas. No assertion of completely absent terry,
lab-measured thickness, breathability, non-slip performance or dress-shoe fit.

323 description/excerpt now emphasize light daily cotton-blend wear. Remove
running/cushioned categories and retain socks/everyday-socks. Product SKU, price,
stock, variation attributes, structured claim records and visibility are retained.
X70/X72 status and other catalog selections are not changed in this scoped update.

User-provided source images:
- https://cbu01.alicdn.com/img/ibank/O1CN01aHj8xH26fc33qbjyf_!!3447167689-0-cib.jpg
- https://cbu01.alicdn.com/img/ibank/O1CN01uzLBlg26fc2ql1Gy6_!!3447167689-0-cib.jpg

Original downloads are retained under .local/323-refresh. Built-in image editing
removed promotional text/graphic overlays; reviewed versions are encoded as WebP
under tools/deploy/323-daily-20261021 with checksums and Chinese alt text. These
are presentation images, not evidence of construction or measurements. Previous
media remains in WordPress. No generated image is used as a source of facts.

Guarded publication: tools/deploy/update-323-daily.php apply|verify|rollback.
Domestic administrator/DB/domain and exact SKU/variation count checks apply.
The sscn_323_daily_20261021 option stores original descriptions/gallery/categories,
imported media IDs and a preservation fingerprint. Repeated apply reuses media;
rollback restores original presentation and retains new media.

Theme now declares WooCommerce native lightbox and slider support, which loads
its bundled PhotoSwipe/FlexSlider only on products. No additional plugin or
external scripts. With JavaScript disabled, normal image links remain a fallback.
Local browser verified clicking opens an overlay, next photo changes 1/2 to 2/2,
and closing returns to the product without navigating away. All domestic products
benefit from gallery behavior; only 323 imagery/copy/categories are changed.

Deploy with tools/deploy/sync-code.py --apply, then invoke the content helper on
claw-host as domestic administrator. Preserve public DNS/payment gates/settings.

Production verification completed:
- Code release: /srv/sockspecs-cn/releases/20261021T075007639688Z.
- Previous code release: /srv/sockspecs-cn/releases/20261021T051409439926Z.
- Deployment checks passed: PHP lint/coding standards, bootstrap, domestic
  isolation, provider mocks, commerce JavaScript and metric editor JavaScript.
- Content helper passed on production product 90 (SS-001-323), preserving
  variations, prices, inventory, claims and store visibility.
- Browser verified new images, daily copy/categories, and unchanged pack prices
  (4/6/12 pairs: CNY 27.60/41.40/82.80).
- Production image click opened PhotoSwipe; next reached image 2/2; close returned
  focus to the product image without changing the product URL.
- Visual proof: /tmp/sockspecs-cn-323-updated.png and
  /tmp/sockspecs-cn-323-lightbox.png (session-local screenshots).
