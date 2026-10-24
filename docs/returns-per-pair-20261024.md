# Domestic returns wording — 2026-10-24

Operator approved returns by complete pair, including partial-order returns, now
that fixed packs are retired. Updated both publication content files and the
shared product/cart/checkout notice. Remove all whole-pack requirements; request
product and pair count in return applications. Buyer pays return postage for
no-reason returns; store covers reasonable return/exchange postage for quality
issues and fulfillment mistakes. Do not mention outbound shipping costs or add
any deduction policy. Refund basis remains the returned items' actual payment.

Deploy via sync-code.py, then run publish-domestic-returns.php apply and verify.
No order, inventory, price or refund-processing behavior changes.

Validation: publication content check and deployment's Composer checks passed.
Release `20261024T123506953665Z`; policy page 157 published and verified using
existing publisher. Browser verified live policy. Anonymous HTTP confirmed new
shared notice on product, cart and checkout URLs, with old notice absent.
