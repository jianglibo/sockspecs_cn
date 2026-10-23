# Reviewed product galleries — 2026-10-20

Operator supplied 58 URLs across ten model groups (57 unique images). Selected
18 photographs/collages for nine existing parent products, two per product.
X58 imagery was withheld: white-base photos do not match the current black-base
X59 variants. Operator explicitly chose to retain X59 images pending new sources.
The recently updated 323 gallery is also preserved.

Selection, exact source URLs/hashes, original JPEGs, edited masters, and image
editing prompts are in `product-assets/gallery-refresh-20261020/`. Image edits
used the built-in image_gen tool to remove advertising overlays, unsupported
claims and incorrect boilerplate. J02/J03 keep distinct public model labels.
Images are visual merchandising, not test or measurement evidence. Structured
specifications, prices, variants and stock are not modified. The excluded X65-03
collage has visually different construction from the selected photos. Original
text claiming 100% wool, pure cotton on wool socks, antibacterial effects and
inconsistent needle counts is not carried into the new galleries.

The administrative `refresh-product-galleries.php` accepts plan, prepare, apply,
verify or rollback plus the absolute manifest path. It validates store/database,
restricts the operation to the nine selected SKUs, validates source checksums,
saves original gallery IDs and a full before snapshot, imports all attachments
before switching galleries, and verifies unchanged business data. Only gallery
IDs/parent modification time and five known derived Google sync metadata keys
are excluded from the preserved business fingerprint. X59/323 gallery IDs and
currency/visibility are checked explicitly. Stock is never reset. Partial import
stops for inspection; completed apply is idempotent. Rollback restores original
gallery references if no subsequent gallery edit occurred and retains media.

Local PNG masters are converted with the established optimize-product-images.php
prepare/review/apply flow. Native WooCommerce thumbnails use uncropped sizing to
preserve complete photos in the catalog's square object-fit:contain frame. Full
WebP files and each store's localized alt/title manifest are checksum-verified.

Existing sync scripts deploy custom code only; media is transferred separately
and imported through WordPress/WooCommerce. Do not import one store's database
into the other. No public DNS, checkout settings or shipping changes are included.

## Completed validation and deployment

Both local stores and production stores passed the guarded import and repeat-apply
checks: nine parent products, 76 variants, two images each. All 18 final WebPs
have a combined size of 1,850,842 bytes. Product data, stock, prices, visibility,
variant images and retained X59/323 galleries matched the before snapshots.
Origin HTTP checks verified all nine pages, pack forms, full-image hashes,
localized alt text, WebP thumbnails and responsive srcsets. All 18 international
HTTPS images also returned Cloudflare cache HITs. The international X65 product
page was visually checked in the browser after loading. Domestic verification
used its private origin; public DNS remains unchanged.

Existing code sync checks passed PHP lint, coding standards and bootstrap tests.
International checks also passed email configuration, pack/Google JavaScript and
20 deployment tests; domestic isolation tests passed. International plugin code
was already synchronized. Domestic release is
`/srv/sockspecs-cn/releases/20261020T022936196137Z`.

Pre-import international backup:
`/var/backups/sockspecs/snapshot-20261020T022856Z-c85dWR`.
Pre-import domestic database backup:
`/var/backups/sockspecs-cn/gallery-refresh-20261020/before.sql`.
Media manifests and helper state allow gallery rollback without deleting media.
Verification reports are in `.local/gallery-refresh-20261020/` (private, ignored).
No checkout, order placement or payment test was needed for this gallery-only change.
