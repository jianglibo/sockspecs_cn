# Local preview — 2026-10-19

Fresh WordPress 7.1.3 and WooCommerce 11.2.1 installed independently. WP core
checksums passed. Docker project `sockspecs-cn`, MySQL container
`sockspecs-cn-db-1`, volume `sockspecs-cn_database_cn`; database/user
`sockspecs_cn`, host `127.0.0.1:33089`. Preview binds to `127.0.0.1:8089`.

Verified from the running installation:

- Locale `zh_CN`, currency `CNY`, RMB example formatting `¥19.90`.
- Store base Zhejiang, China (`CN:CN12`), mainland-only sales/shipping.
- Time zone `Asia/Shanghai`, grams/centimeters, two price decimals.
- Coming-soon enabled for store pages, indexing discouraged, no enabled payment
  gateways, zero published products and zero completed orders.
- Local mail blocker returns false; no test email was sent.
- Chinese homepage, catalog filters, empty comparison and account login rendered.
- Homepage visually checked at 1440px and 390px; no horizontal overflow.
- Native shop rendered the Chinese coming-soon page.
- Source checks, isolation assertions, pack JavaScript checks and shell syntax pass.
- First-run page seed rerun preserves existing editor content.

The WooCommerce translation updater rejected its official download URL on this
network. The same official archive downloaded successfully through HTTPS curl;
only MO/PO/JSON translation files were extracted, leaving HTTP safety checks intact.
Installation logged a WP-CLI HTTP_HOST warning and a WooCommerce activation
translation-timing notice. They did not recur during the tested frontend visits.
The initial shop visit also exposed missing theme header/footer fallbacks; explicit
templates were added and the shop was rechecked without new PHP log entries.

No international source/runtime data was imported or changed. Its database
container remains healthy with its pre-existing start time
`2026-10-18T03:20:51.099764927Z`. No production connections or deployment performed.
The new repo still has no Git remote. Credentials are generated locally and ignored.

This is an empty local storefront, not a completed domestic commerce launch.
Product facts, prices, inventory, payment, delivery, policies and populated
purchase flows remain separate future work.

## Subsequent catalog import

The empty-store/coming-soon observations above describe the initial setup only.
On 2026-10-19 the five Chinese product previews were imported and live preview
was enabled by the operator. See `catalog-import.md` for current state and tests.
