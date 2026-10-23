# Domestic homepage photography — 2026-10-23

Replaced the text-only homepage with a split photo introduction and two sample
structure cards. All photographs are the operator's originals received in the
Gmail `images` message on 2026-10-23; no generated texture or product detail.

Sources: IMG20261023080549.jpg (paired sole view), IMG20261023080505.jpg (black
flat-knit close-up), IMG20261023080534.jpg (light zoned sole). The originals remain
in the task's output/photos/2026-10-23 directory. Public assets are resized WebP
copies at 640/1200 pixels wide, with camera/GPS metadata omitted. CSS frames the
photographs; no retouching or claims about quantified performance were added.
Copy about relative thickness is based on the operator's sample observations.

The ordinary WordPress homepage content remains editable. The guarded migration
`tools/deploy/update-home-photos.php plan|apply|verify` resolves local catalog and
guide URLs, refuses unexpected existing homepage content, and keeps the previous
content in non-autoloaded option `sscn_home_before_photos_20261023` for recovery.
Restore that option's `content` to its `id` to undo the content change. Theme
styles are scoped to `.sscn-photo-home`; existing pages are unaffected.

Local migration plan/apply/verify passed. Browser checked hero, three loaded
photos and the detail anchor. Existing sync script runs PHP lint, coding standards,
bootstrap/domestic boundary tests, provider mock tests and the JS suites. No
store visibility, prices, stock, payment settings or international files changed.

Deployment completed with release `20261023T003526152620Z`; pre-task release
`20261022T232133646515Z`. Remote content apply/verify passed for homepage ID 15.
Fixed CLI-generated theme image URLs to explicitly follow the HTTPS home scheme;
browser verification confirms all three images load over HTTPS. Desktop hero,
detail-anchor navigation and a local 390px iframe layout were visually inspected.
