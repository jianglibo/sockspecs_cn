# Original five domestic pack prices — 2026-10-20

Operator asked to fill the original five products using already supplied purchase
prices, continuing the agreed low-margin domestic pricing approach. These are
new domestic retail prices, not supplier quotes published as retail prices.

| Model | Budget purchase CNY/pair | Retail pair allocation | 4 pairs | 6 pairs | 12 pairs |
|---|---:|---:|---:|---:|---:|
| X70 | 7.00 | 10.90 | 43.60 | 65.40 | 130.80 |
| X109 | 10.80 | 14.90 | 59.60 | 89.40 | 178.80 |
| X112 | 11.62 | 15.90 | 63.60 | 95.40 | 190.80 |
| 364 | 15.52 | 20.90 | 83.60 | 125.40 | 250.80 |
| 365 | 16.74 | 22.90 | 91.60 | 137.40 | 274.80 |

Costs come from earlier operator-pasted 1688 quotations. X70 uses its 5–499 tier;
X112/364/365 use their quoted 5–299 tiers; X109 conservatively uses the upper end
of the quoted 9.83–10.80 range. Supplier shipping/tax, current eligibility and
mixed-order aggregation remain unknown. These are procurement planning inputs,
not verified current invoices. Gross retail/purchase spread is not net profit.

All five use 4/6/12-pair building, default 4, same price across their existing
colors/sizes. Fifty-two native variation prices allocate individual pairs within
packs. Their preview purchase blocks are removed. No stock values are written by
this operation: the previously authorized 60-pair balances and any later sales
must be preserved. Publication, descriptions, specifications, supplier model
identities, images and all other products remain unchanged.

The helper price-original-domestic-products.php supports plan/apply/verify on the
domestic CNY site only. It refuses existing prices, stores the full before-state
in sscn_original_prices_20261020, checks exact unchanged stock/non-price data, and
records the cost basis privately under supplier 001. Repeated completed apply
verifies only. Interrupted writes require review of recorded state. Rollback must
restore only price/pack/preview fields and supplier amendment after checking for
later edits/orders; never reset stock or restore the whole database over sales.

Backups: .local/backups/original-prices-20261020/before.sql and
/var/backups/sockspecs-cn/original-prices-20261020/before.sql.
Earlier publication and price migration fingerprints represent historical states;
this latest verifier supersedes their assertions about five unpriced products.
Payment, shipping settings, public DNS and the international site are unchanged.

Executed: local and server application/verification passed; 52 variant prices
filled and complete non-price/stock fingerprints preserved. All 11 models passed
51 local guest model/size/pack cart cases, with no orders placed. Composer checks
passed. Release /srv/sockspecs-cn/releases/20261020T005701326573Z;
prior release /srv/sockspecs-cn/releases/20261020T005356729788Z.
All five original product pages returned HTTP 200 through the domestic origin,
with pack forms and 4 pairs selected by default. External checkout/payment was
not tested or enabled. Private supplier cost/price notes verified.
