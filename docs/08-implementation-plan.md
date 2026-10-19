# Domestic implementation plan

## CN0 — independent source baseline (current)

Initialize an independent repository from the current product plugin. Preserve
source provenance, omit private/runtime data and international operations, disable
international hooks, prepare isolated local setup and run source-level checks.
No database schema or existing store is changed in this milestone.

## CN1 — fresh local storefront

Explicitly create the local environment using the new setup tools. Verify Chinese
presentation, CNY, mainland address inputs, catalog/Compare, native customer
accounts and verified-buyer reviews. Prepare domestic home/product content. Import
only intentionally selected product specifications/images after source review;
set independent stock and prices. No customer/order/database cloning.

## CN2 — domestic commerce decisions and integrations

Choose login (ordinary accounts, phone/SMS, WeChat), payment gateways (evaluate
WeChat Pay / Alipay), delivery provider, pack sizes, return policies and any reward
rules. No approval of those policies is implied by retaining inherited code.
Domestic credentials and provider registrations belong to this store only.

## CN3 — deployment and launch

Connect the future GitHub repository; configure the domestic server independently.
Build and test its deployment tooling, backups and restoration. Verify order,
payment callback, refund and fulfillment behavior. Keep coming-soon mode until
explicit launch authorization. No shared inventory, account or credit system.
