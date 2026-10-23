# Domestic implementation plan

## CN0 — independent source baseline (complete)

Initialize an independent repository from the current product plugin. Preserve
source provenance, omit private/runtime data and international operations, disable
international hooks, prepare isolated local setup and run source-level checks.
No database schema or existing store is changed in this milestone.

## CN1 — fresh local storefront

Local WordPress, isolated database, Chinese/CNY defaults, starter theme and
Chinese preview pages are running. Five Chinese products with images, 52 variations and structured parameters are
imported. Populated comparison and filters are verified; domestic checkout and
full customer journey validation remain pending.

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

Preview deployed on 2026-10-19 to claw-host, with HTTPS on claw-hub and local
hosts mappings. Live preview is now authorized; purchasing is blocked per product and public DNS is unchanged. See
`deployment-claw-host.md`. Public launch and payment/fulfillment validation remain pending.

Connect the future GitHub repository; configure the domestic server independently.
Build and test its deployment tooling, backups and restoration. Verify order,
payment callback, refund and fulfillment behavior. Live catalog preview is
authorized; keep purchases blocked until domestic commerce is configured. No shared inventory, account or credit system.

2026-10-20 update: all 11 products published with 60 pairs per variation by explicit
operator approval; six priced products can enter the cart. Five original products
await domestic prices. Shipping/payment integration remains pending. This
supersedes the earlier blanket purchasing block; see publish-stock-20261020.md.

Later 2026-10-20 clarification: original five domestic prices are now filled using
the operator-provided cost quotations and low-margin approach. All 11 products
can enter pack carts. No stock reset or payment/shipping change.

2026-10-20: CN2 login/payment implementation prepared as independent domestic
plugin; all customer entry points remain disabled pending ICP/domain, merchant
permissions, private secrets and real transaction validation. See domestic-commerce.md.
