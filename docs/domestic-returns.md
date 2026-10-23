# Domestic returns policy — 2026-10-20

Published the operator-approved Chinese policy, domestic server page 157 (`https://sockspecs.cn/?page_id=157`), local page 205. Normal unpacking/touch inspection is permitted, goods must remain intact, 4/6/12-pair packs return as complete packs, and buyers pay non-quality return freight. A customer can return a complete pack without returning other packs in the same order. Quality/fulfillment faults are handled separately with seller-paid reasonable return/exchange freight.

Content source: `tools/deploy/content/domestic-returns.html`. Guarded idempotent publication: `tools/deploy/publish-domestic-returns.php apply|verify`, with a pre-change snapshot in `sscn_returns_before_20261020`. Policy page is an ordinary editable WordPress page, referenced by `sscn_returns_page_id`. Reapplying publication deliberately restores the checked-in approved content.

`theme/returns-policy.php` displays one concise notice after the pack form, above block cart/checkout content, and supports classic WooCommerce cart/checkout hooks. Theme footer links to the published policy. No broken policy links if the page is unpublished. Theme CSS version increased to 0.1.2.

Validation: local real-browser product, populated cart and checkout displays passed; empty cart and policy HTML checks passed. The local test pack was removed without submitting an order. Existing deployment checks passed; code synchronized through `tools/deploy/sync-code.py --apply`, then policy published on claw-host. Live policy inspected in browser and server product/cart/policy HTML checked. Payment gates, stock and international store unchanged.

Payment research: WeChat and Alipay support partial refunds, useful when one of several packs is returned. Current domestic gateway advertises only products, not automatic refunds. Staff must first refund through the payment merchant platform, then record the matching manual WooCommerce refund; WooCommerce manual refunds do not transfer money. Wallet portions return as wallet balance. This update does not implement or execute payment refunds.

Official references:
- https://pay.wechatpay.cn/doc/v3/merchant/4013071031
- https://aipay.alipay.com/docs/subscribe/api-list/other-basic-interfaces/alipay-trade-refund.html
