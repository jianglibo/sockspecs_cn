# SockSpecs CN

Independent domestic SockSpecs project. Intended production domain: **sockspecs.cn**.
This is a local source repository, not a deployed or launch-ready store.

## Current state

- Independent Git repository on `main`; no GitHub remote configured.
- Based on the international store's current **0.5.26 working-tree snapshot**,
  including its uncommitted improvements, rather than only its older HEAD commit.
- Domestic plugin baseline: **0.1.0**. Structured specifications, evidence,
  comparison, filters, optional pack building, size/shape and net pair weights
  are retained.
- International homepage/policy hooks, language toggle, Google login/feed,
  USD referral rewards and YunExpress administration are **not registered**.
  Their inherited implementation files remain available for deliberate adaptation.
- International publication payloads and operational/deployment scripts are omitted.
- No credentials, WordPress runtime, database, products, stock, customers, orders,
  reviews, uploads, supplier source documents or backups were copied.
- No server connection, DNS change, payment integration or production deployment
  has been performed. No local database/container or web server has been started.

The new store owns its own inventory and prices. There is no automatic connection
or synchronization with the international store.

## Layout

- `parameter-commerce/` — inherited product plugin with domestic-only hook selection.
- `tools/` — new local development tools, isolated from the international project.
- `tools/deploy/sync-code.py` — disabled placeholder; exits without a connection.
- `tests/` — bootstrap, isolation-boundary and pack-builder JavaScript checks.
- `docs/source-baseline.json` — original HEAD, snapshot provenance and file hashes.
- `docs/reference/` — selected historical technical reference only.
- `docs/08-implementation-plan.md` — domestic milestones and open work.

## Validation without a running store

```bash
composer install --no-interaction --prefer-dist
composer check
node tests/pack-builder-js.js
bash -n tools/*.sh
```

The lock file retains the exact inherited development dependency versions. PHP
8.3 is the initial baseline. These checks do not claim a tested domestic checkout.

## Optional fresh local WordPress installation

Run this only when ready to create the domestic preview:

```bash
bash tools/setup-local.sh
bash tools/preview.sh
```

The setup command generates new secrets in a private, ignored `.env`, installs
fresh WordPress/WooCommerce, and activates the domestic plugin. Do **not** copy
an `.env`, database or WordPress installation from the international project.
`.env.example` documents variables; it does not contain working credentials.

| Setting | Domestic preview |
|---|---|
| Docker Compose project | `sockspecs-cn` |
| Database / user | `sockspecs_cn` |
| Local database port | `33089` |
| Local web port | `8089` |
| Database volume | Compose-managed `sockspecs-cn_database_cn` |
| Administrator | `sockspecs_cn` |
| Language / currency | `zh_CN` / CNY |
| Sales / shipping destination | China |
| Public access / indexing | Coming-soon enabled / indexing discouraged |
| Email | Blocked in the local runtime |

Initial WordPress/WooCommerce versions match the inspected international baseline
(7.1.3 / 11.2.1); compatibility and full Chinese presentation still need validation.
No domestic gateway, courier, SMS provider or WeChat application is configured.
Pack sizes remain inherited optional 6/12 behavior until a domestic decision is
made; products need not enable the pack builder.

## GitHub and deployment later

Supply the new GitHub repository URL when it is ready. Do not attach the
international remote. The repository can then be connected and pushed explicitly.
A domestic server and a separate deployment process must be configured before
publishing; the disabled deployment placeholder cannot target either store.
