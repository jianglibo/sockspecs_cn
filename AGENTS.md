# SockSpecs CN — independent domestic project

This repository is for the domestic store, intended domain sockspecs.cn. Read
README.md and docs/08-implementation-plan.md before changes. Reference documents
under docs/reference describe inherited architecture, not current launch approval.

## Isolation
- Never edit, deploy to, import the database of, or reuse credentials from the
  international project. This project has its own Git history, data and inventory.
- Do not use the international SSH host, server paths, database, Docker project or
  ports. No production deployment is configured here yet. GitHub remote is pending.
- Do not copy .env, .git, .local, customer/order data, source supplier archives,
  OAuth files, backups, payment credentials or operational agent settings.
- Work locally; keep coming-soon mode enabled until the user authorizes launch.
- Chinese is the intended storefront language; international policy and marketing
  copy are not approved for this store. Domestic payments, authentication,
  shipping, pack sizes and reward rules require separate decisions.

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
