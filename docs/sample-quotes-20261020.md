# Private sample procurement quotes — 2026-10-20

The operator supplied five quantity schedules and two unqualified J02 quotes.
These are CNY per-pair supplier prices, not retail USD/CNY selling prices.
record-sample-quotes.php reads sample-quotes-20261020.json, guarded to its own site,
and saves a private supplier metadata snapshot plus readable Internal remarks.
Existing supplier fields/remarks are preserved through Suppliers::save. Products,
variations, inventory, published specifications and store settings are not changed.
The private option pc_sample_quotes_20261020 records before/after supplier fields
and a complete product-data fingerprint. Apply checkpoints and verifies; repeat
apply verifies rather than appending duplicates. A named lock serializes writes.

X58 is retained exactly as supplied, pending clarification against the existing
X59 draft. It is not assigned to X59. J02 quotes 8.10 and 8.42 are retained as two
unmapped prices, not an inferred range/tier and not assigned to J03. First-tier
quotes retain the 60-day returning-customer condition except X65; this is not a
calculated expiry date. A starting quantity of one is not asserted to be a defined
1–4 bracket. Tax and shipping inclusion are unknown.

Use administrator WP-CLI eval-file with plan/apply/verify, locally first, then the
existing code sync workflow. International helpers/data transfer separately to a
private import directory outside the web root. Domestic sync includes tools/deploy.
The payload is procurement data: do not place it in public media or plugin content.
Review in WooCommerce → Suppliers (private) → supplier 001 → Internal remarks.

For rollback, check current fields still equal the recorded after-state, restore
only the before-fields via Suppliers::save and prior_quote_meta; retain other
supplier edits and never overwrite commerce data. Amend ambiguous prices through
a separate reviewed change instead of rewriting the applied manifest fingerprint.

Executed: local apply/repeat-apply and both Composer checks passed. Both server imports verified all six private quote records and unchanged product fingerprints. International supplier ID 2599; domestic ID 89. Domestic code release /srv/sockspecs-cn/releases/20261020T001026783841Z. International code already synchronized; private content helper under /srv/sockspecs/imports/sample-quotes-20261020. X58 and J02 conditions remain unresolved as explicitly recorded.

## Operator clarification

clarify-sample-quotes.php records CNY 8.42/pair as an operator-selected J02 budget, not a newly verified supplier tier; J03 is excluded. Raw quotes remain unchanged. The operator reports X58–X61 share a listing; exact X59 pricing and shared specifications are not thereby established. Separate private decision metadata and a dated note retain this distinction. Original remarks verification is historical after the amendment; use the clarification verifier for current supplier fields.

Clarification applied and verified locally and on both servers. Existing raw quote metadata, all product/variation data and retail prices remain unchanged. Both existing sync workflows completed; only private budget/clarification records and supplier notes changed.
