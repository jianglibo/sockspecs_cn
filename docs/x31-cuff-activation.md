# X31 cuff activation — 2026-10-21

Operator corrected the dimension graphic's identity from X109 to X31 and explicitly confirmed all three cuff styles offer the existing six colors. New stock must remain blank.

The guarded `tools/deploy/enable-x31-cuffs.php` was applied locally, repeated, verified, then applied/verified on the domestic server after `sync-code.py --apply`. Release: `20261021T220315376775Z`; previous `20261021T215236898698Z`.

X31 now has 18 native variants, all EU 39–44: six existing IDs/SKUs mapped to high cuff with stock/prices preserved, plus six mid and six low variants with new suffixed SKUs. New stock uses WooCommerce's empty string setter (null is converted to zero), stock management enabled, outofstock, no backorders. Existing model pack prices remain unchanged. New pair prices use the existing same-model price; no orders or stock movements were made.

Published exact-variant dimensions from the user-supplied supplier image are 190/130/80 mm height and 180 mm foot, with stated ±5 mm tolerance. Thirty-six records point to the supplier image. Old ambiguous parent height/foot records were retracted, retaining history; other records, including the operator's current thickness record and withdrawn terry record on the server, were preserved. The old 47 g parent record has unconfirmed style scope and was not copied into new variants.

`_pc_pack_show_unavailable=yes` enables only display/spec browsing of published, visible, active variants when stock is unconfirmed. The pack form and diagram request display groups, while selection/cart validation still requests only purchasable in-stock groups. Quantity controls for unavailable rows are disabled and show 库存待确认，暂不可购买. This opt-in does not change store visibility or permit backorders. Current same-style/same-size pack rule remains unchanged; the operator's question about mixed sizes/heights was answered as a separate design discussion.

Verification: scoped helper checks 18 variants, 3 display groups versus 1 purchasing group, correct guest diagrams, blank new stock, rejected new-stock selections, original variant commerce fingerprints, prices/images/other claims/status/visibility preservation. Cuff-height regression and pack-builder JavaScript tests passed, as did sync's lint/standards/unit/boundary checks. Browser verified mid→low switch and disabled stock controls on the live product. Screenshot `/tmp/x31-live-cuff-options.png`.

Historical `update-x31-daily.php` is superseded and must not be rerun. Future stock updates should use native variation editing rather than rerunning this migration, whose verification deliberately expects new stock still blank. Source image: https://cbu01.alicdn.com/img/ibank/O1CN01yoMwO026fcA02I0qr_!!3447167689-0-cib.jpg
