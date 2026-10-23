# Chinese catalog import — 2026-10-19

The operator authorized importing the five existing international products and
switching the domestic preview out of coming-soon mode. Prices and inventory
quantities remain **unset**, not zero. No public DNS changes or international
mutations are included.

## Reviewed content

| Model | Domestic title | Color/size combinations |
|---|---|---:|
| SS-001-X109 | 缓震跑步袜 | 13 |
| SS-001-X112 | 合成纤维运动中筒袜 | 12 |
| SS-001-364 | 日常羊毛混纺中筒袜 | 11 |
| SS-001-365 | 毛圈缓冲羊毛混纺中筒袜 | 12 |
| SS-001-X70 | 低帮缓冲运动袜 | 4 |

The source is a narrowly scoped read-only export of the international live store.
Only the selected products, images, variation identity and published specifications
were exported. No prices, stock, accounts, orders, reviews, credentials, commerce
integration settings or private supplier archives are in the import payload.

`tools/deploy/catalog/products.zh-CN.json` contains the reviewed Chinese content;
five WebP assets are in its `images/` subdirectory, each checked against its source
SHA-256. Local-only tooling under `tools/catalog/` documents source export and
translation; it is not part of the server deployment. This is a one-time content
snapshot, not automatic synchronization between stores.

All 310 approved records retain their exact metric, value, source tier, date,
conditions and sample scope. Descriptions, scope labels, source titles and notes
are translated; machine tokens used by filtering stay unchanged. Their display
labels are Chinese. PDF-based sample composition remains confined to its tested
sample/area; it does not become a declaration for all variants. Original source
hashes remain private, with a note that source archives remain at the original
store. No private source attachment is published.

The 50 net pair weights exclude packaging. X70 is 37 g; X109 Qing Gray and 364
Lilac remain unknown. Listing package/item dimensions retain their qualification
and never become garment dimensions. The low-cut X70 illustration is preserved.

## Reproducible import

Local CLI (own database only):

```bash
bash tools/wp.sh --user=sockspecs_cn eval-file tools/deploy/import-catalog.php plan
bash tools/wp.sh --user=sockspecs_cn eval-file tools/deploy/import-catalog.php apply
bash tools/wp.sh --user=sockspecs_cn eval-file tests/catalog-import.php
```

`apply` is guarded to the domestic database and authorized administrator, validates
image hashes, checks SKU ownership, records native ID mappings, uses WooCommerce
CRUD and Repository save/review/publish operations, and resumes tracked steps.
Once complete, repeat `apply` only verifies the result; it does not reset later
editor changes. A changed manifest or edited imported data requires a separately
reviewed update, not an overwrite. The import state is stored in the non-autoload
option `sscn_catalog_import_20261019`. The homepage's exact initial empty-catalog
sentence is updated; unrelated editor content is preserved.

Code/assets deploy via `python3 tools/deploy/sync-code.py --apply`. The payload
already includes `tools/deploy/`. Run the same guarded import helper on claw-host
as `sockspecs-cn`, with the domestic admin user and domestic WordPress path.
Code sync now checks that the preexisting visibility option is preserved; it does
not require or change coming-soon mode. Only this explicitly authorized import
changes it to `no`.

## Preview versus sales

Parents and variants have empty regular/sale/current prices, unmanaged stock with
null stock quantities, and an unavailable internal stock status. No fabricated
inventory counts or USD pack settings are imported. `_sscn_catalog_preview=yes`
blocks purchasing even if a price is later supplied. The storefront explains
that prices and stock are pending rather than saying products have sold out.

Preview-marked published variants can be inspected in the specification selector,
Compare and filters despite their missing commerce data. Existing publication,
parent ownership and catalog visibility checks still apply. This exception never
makes them purchasable. Ordinary unmarked products retain native behavior.

When domestic sales are ready, set local prices and actual stock, configure the
payment/delivery journey, then uncheck **仅供参数预览（暂不销售）** in each product's
General settings. Pack quantities and domestic selling rules remain undecided.
Do not clear the marker just to make parameters visible.

## Validation and recovery

- 334 read-only runtime assertions: all 52 anonymous preview scopes; 50 net weights
  and two unknown values; exact Compare projection; weight and measured-only
  filters; missing-price exclusion; no inherited sample composition; no exposed
  private provenance; purchase denial and foreign-variation rejection.
- `apply` repeated locally verified without duplicate products, images or claims.
- Composer syntax/style/bootstrap/isolation checks and existing pack JS tests pass.
- Browser checks cover Chinese product/specification views, X70 37 g selection,
  image loading, comparison entry links and narrow-screen containment.
- Local pre-import database: `.local/backups/catalog-import/before.sql` (0600).
- Take a new private server database backup before importing. For rollback before
  any sales/admin edits, restore that domestic snapshot and prior code release;
  imported media can remain unreferenced until reviewed cleanup. After subsequent
  edits or orders, use a scoped retraction/update rather than full database restore.

The site's mail block, noindex policy, Chinese/CNY settings and independent
international integrations remain in place. Checkout/payment fulfillment is not
claimed as tested or enabled.

## Server execution

- Private pre-import backup: `/var/backups/sockspecs-cn/catalog-20261019T023539Z`.
- Deployed code/assets: `/srv/sockspecs-cn/releases/20261019T023612266465Z`.
- Previous release: `/srv/sockspecs-cn/releases/20261019T021137510050Z`.
- Guarded import verified five products, 52 variations, five matching images,
  310 published records and 50 net pair weights. The same 334 read-only runtime
  assertions passed on the server as locally.
- All five HTTPS product views and catalog images loaded. There are no purchase
  buttons or structured-data price offers. Chinese weight summaries preserve the
  two undocumented variant gaps. Live preview is `no` for coming-soon, CNY and
  noindex are retained, mail guard remains active, and enabled gateways are empty.
- The existing API `/health` returned 200 after deployment.
