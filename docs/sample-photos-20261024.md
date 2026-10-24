# Operator sample photographs — 24 October 2026

Domestic only. The operator requested adding four real photographs received in
Hotmail under “袜子实物照片” (06:09 UTC). QQ large-attachment links were downloaded
through their normal download pages. No email authentication or download tokens
are stored in the repository.

| Original filename | Private manufacturer model | Public model | Visual match |
| --- | --- | --- | --- |
| IMG20261024133426.jpg | X31 | SS-S0007 | Plain black, ribbed leg and smooth inside |
| IMG20261024133523.jpg | X59 | SS-S0008 | White/grey low-cut with blue stripe and zoned sole |
| IMG20261024133617.jpg | J02 | SS-S0011, style A | Black with orange/white markings and ankle mesh |
| IMG20261024133728.jpg | X112 | SS-S0002 | White/grey circular pattern and terry sole |

Matching used the existing manufacturer photographs and the operator's previous
identified X59 sample. The J02 photograph is captioned as style A, not J03/style B.

Delivery: EXIF auto-orientation, longest edge 1600px, WebP quality 84, metadata
stripped. No retouching, generated detail, colour adjustment or cropping. Originals
are retained in the task's output/photos/2026-10-24/originals directory and Downloads.
The deployment manifest records original and delivery SHA-256 checksums.

`tools/deploy/add-sample-photos.php` uses native WordPress media APIs and WooCommerce
gallery setters. It appends one image per parent, preserves featured images and
existing gallery order, generates responsive thumbnails, snapshots the old gallery
and checks unchanged product/variation data and store visibility. Repeated apply
does not add duplicates. Rollback restores only gallery IDs and retains media.

Run with domestic administrator WP-CLI context, using prepare, apply, verify or
rollback as the first argument. Sync local assets/code using the existing
`tools/deploy/sync-code.py --apply` before the server operation.

Validation completed: local prepare/apply/verify, repeat apply without duplicates,
rollback and reapply; composer check passed. Server release
`20261024T061921598203Z`; attachments 204 (X31), 205 (X59), 206 (J02), 207 (X112).
All four public pages contain the added photograph with responsive WebP srcsets;
600px delivery images are 56–128 KB. X59 gallery selection and PhotoSwipe modal
were checked in the browser, including the Chinese caption. Existing product
data, variants, featured images, gallery entries and visibility passed the
preservation check.

## X109 follow-up

The operator subsequently sent email “x109” at 06:25 UTC, with the native attachment
IMG20261024142449.jpg. It shows the purple sample outside and inside. Added to the
same manifest as SS-S0001 / private model X109, captioned “紫色样品，外侧与翻面袜底细节”.
The same 1600px/WebP-84 conversion preserves the full photograph and strips EXIF.
Existing four entries are retained and verified without reimporting their media.

X109 validation: local prepare/apply/verify and deployment composer checks passed.
Release `20261024T062916223726Z`; new server attachment 208. The previous four
attachment IDs were retained. Public X109 gallery was checked in the browser with
the new photograph selected. Product/variant preservation checks passed.
