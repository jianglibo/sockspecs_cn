# Domestic selection editor cleanup

Removed the old 4/6/12 pack-price editor and opt-in checkbox, save handlers, legacy form/assets/submission entry point and legacy price HTML hook while domestic commerce is active. Only the native per-variation pair price/inventory and the pause-selection control remain. Copy describes the minimum of four pairs across products and arithmetic unit pricing.

Variable products enter the existing selection eligibility checks by default, including products with a missing or `no` legacy flag. Pause remains a purchase guard in both selection quoting and shared variation validation; paused products remain protected against native single-pair bypass. Publication, preview-only, visibility, valid price, variant stock management, no backorders and stock reservation constraints remain. Empty prices no longer produce a zero-price label through the selection price HTML filter.

Legacy pack-price metadata is retained for recovery but ignored by active domestic selection, including catalog calculations and variation validation. No product data, prices, inventory, publication/preview states, order records or international code are migrated.

Validation: real local WordPress selection suite checks legacy-off/new default enrollment, malformed archival tier prices, pause/native bypass protection, missing prices, disabled inventory tracking, preview-only purchase protection, cleaned editor hooks, arbitrary totals >=4, exact pricing, aggregated stock, cart retry/rollback/tampering, saved-list isolation/concurrency and order metadata. Full composer checks pass. Live read-only inventory UI and product-data fingerprint are compared before/after deployment.

Deployed using the existing sync script as `20261024T084638482736Z`. Live audit: old price fields=false, old opt-in=false, pause control=true, X113 default selection enrollment=true. Product/variation data and visibility fingerprint matched before/after. Browser inspection of X113's Inventory tab confirmed clean native controls plus the pause checkbox; no live product save or order action was performed.
