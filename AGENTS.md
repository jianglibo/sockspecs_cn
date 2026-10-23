# SockSpecs CN — independent domestic project

This repository is for the domestic store, intended domain sockspecs.cn. Read
README.md and docs/08-implementation-plan.md before changes. Reference documents
under docs/reference describe inherited architecture, not current launch approval.

## Isolation
- Never edit, deploy to, import the database of, or reuse credentials from the
  international project. This project has its own Git history, data and inventory.
- Do not mutate the international SSH host, server paths, database, Docker project or
  ports. The operator authorized a one-time read-only product/spec/image export on
  2026-10-19; no customer, order, price, stock or credential data may be copied. Domestic deployment is claw-host:/srv/sockspecs-cn, proxied by claw-hub.
  Public DNS changes are not authorized. GitHub remote is pending.
- Do not copy .env, .git, .local, customer/order data, source supplier archives,
  OAuth files, backups, payment credentials or operational agent settings.
- Explicit operator exception on 2026-10-20: selected bbserver WeChat login, WeChat Pay and Alipay credentials were copied read-only into this domestic store's private configuration. Keep them outside Git/web root; never alter the source service. All commerce entry points remain disabled pending domain/platform approval.
- Make changes locally, test and use tools/deploy/sync-code.py --apply for custom
  code updates. Preserve the current visibility setting during code sync. The operator authorized
  live preview mode on 2026-10-19. On 2026-10-20 the operator authorized publishing
  all 11 products and setting each of 85 variants to 60 pairs. All 11 products now have domestic pack prices and may enter the cart.
  Payment/delivery integrations remain separate work. Never reinitialize stock after sales.
- Chinese is the intended storefront language; international policy and marketing
  copy are not approved for this store. Domestic payments, authentication,
  shipping, pack sizes and reward rules require separate decisions.
- On 2026-10-20 the operator approved an independent domestic referral program:
  new customers receive ¥5 off their first order; referrers earn ¥5 CNY shopping
  credit on completion. Credit accumulates, applies to all domestic products and
  cannot be withdrawn. See docs/domestic-referrals.md; never import international balances.

## Engineering
- WordPress/WooCommerce own products, variations, inventory, prices and orders.
  Reuse the structured specifications, evidence, comparison and catalog modules.
- Never modify WooCommerce/WordPress core or write directly to core order tables.
- Unknown product facts remain blank; keep source provenance and publication
  review. Demonstration data must never become real product claims.
- Follow capabilities, nonce, escaping, validation and prepared-query conventions.
- Prefer small server-rendered interfaces with progressive enhancement.
- International integration classes retained for reference are not registered by
  this fork's bootstrap. Do not re-enable them wholesale.
- Record changes, validation and limitations. Test affected functionality and
  isolation boundaries. Never claim unexecuted browser/order/payment tests.
- Do not create a shared package, inventory sync or a cross-store login system
  unless requested. Do not spawn subagents unless requested.

## Domestic email
- Operator approved Cloudflare transactional mail on 2026-10-20: orders@sockspecs.cn for orders, hello@sockspecs.cn for accounts. See docs/domestic-email.md. The server preview may send authenticated mail; local mail remains blocked. Keep the token outside Git/web root and preserve payment/login gates. Test messages require an explicit recipient authorized by the operator.
