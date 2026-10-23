# Domestic product translation completion

Filled 46 missing pack-interface translations and added the Model → 款式 label.
Attribute label matching now accepts both lowercase keys and the capitalized
Color/Size/Model names used by the newer products. Compiled the checked-in Chinese
MO using `tools/compile-translations.php`; no product facts, stock, or prices changed.

Validation: all 11 local published product pages rendered Chinese pack controls
and attribute labels, with no remaining English prose in main content (model codes
and SKU excluded). Browser check: switching X59 to six pairs and selecting six
updates the Chinese progress, subtotal and enabled add-to-cart button correctly.
Pack-builder JavaScript checks and `composer check` passed.

Synced with `tools/deploy/sync-code.py --apply` to domestic release
`20261020T042357966900Z`. Verified X59's live page shows the Chinese controls,
options, quantity status and explanatory text. Existing visibility is preserved.
