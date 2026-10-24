# SockSpecs CN

Independent domestic SockSpecs project. Intended production domain: **sockspecs.cn**.
The domestic preview is deployed at **https://sockspecs.cn/** through a hosts-file mapping.
All 11 products are published, with 60 pairs per color/size/model variation
(85 variations). All 11 products now have domestic 4/6/12-pair prices.
Public DNS is unchanged; domestic payment and shipping setup remain pending.

## Current state

- Independent Git repository on `main`; no GitHub remote configured.
- Based on the international store's current **0.5.26 working-tree snapshot**,
  including its uncommitted improvements, rather than only its older HEAD commit.
- Domestic plugin baseline: **0.1.0**. Structured specifications, evidence,
  comparison, filters, optional pack building, size/shape and net pair weights
  are retained.
- International homepage/policy hooks, language toggle, Google login/feed,
  and YunExpress administration are **not registered**.
  Their inherited implementation files remain available for deliberate adaptation.
- Referral rewards are adapted as a separate CNY program: ¥5 for the referred
  first purchase and ¥5 credit for the referrer after completion. See
  [domestic referral rewards](docs/domestic-referrals.md).
- International publication payloads and operational/deployment scripts are omitted.
- No credentials, WordPress runtime, database, stock, prices, customers, orders,
  reviews, supplier source archives or backups were copied. The five selected
  products, their five WebP images, 52 variations and 310 published specification
  records were explicitly imported as Chinese domestic preview content.
- A fresh local WordPress/WooCommerce installation now runs at
  **http://127.0.0.1:8089/** with its own database. Chinese homepage, catalog,
  comparison, about and account pages are available. The five products are imported.
- The domestic preview is deployed to `claw-host:/srv/sockspecs-cn`, with HTTPS
  terminated by claw-hub. No public DNS or payment integration changes were made.

The new store owns its own inventory and prices. There is no automatic connection
or synchronization with the international store.

## Layout

- `parameter-commerce/` — inherited product plugin with domestic-only hook selection.
- `tools/` — new local development tools, isolated from the international project.
- `theme/` — Chinese storefront theme, linked only into this local installation.
- `tools/deploy/sync-code.py` — guarded domestic deployment; dry run by default.
- `tests/` — bootstrap, isolation-boundary and pack-builder JavaScript checks.
- `docs/source-baseline.json` — original HEAD, snapshot provenance and file hashes.
- `docs/reference/` — selected historical technical reference only.
- `docs/08-implementation-plan.md` — domestic milestones and open work.

## Validation without a running store

Open the repository in the **PHP 8.3 Dev Container** (VS Code: “Reopen in
Container”) to get the same PHP baseline as CI and Composer dependencies. The
container installs the locked development dependencies on creation. Run checks
there with:

```bash
composer check
node tests/pack-builder-js.js
bash -n tools/*.sh
```

This container is for PHP development and checks; the local WordPress preview
still uses the host Docker installation and the separate setup below.

On a host that already has PHP 8.3 and Composer 2, the equivalent commands are:

```bash
composer install --no-interaction --prefer-dist
composer check
node tests/pack-builder-js.js
bash -n tools/*.sh
```

The lock file retains the exact inherited development dependency versions. PHP
8.3 is the initial baseline. These checks do not claim a tested domestic checkout.

## Local preview

The initial setup is complete. Start the preview after a restart with:

```bash
docker compose --env-file .env up -d --wait db
bash tools/preview.sh
```

Open **http://127.0.0.1:8089/**. Administrator login:
**http://127.0.0.1:8089/wp-admin/**, username `sockspecs_cn`.
The generated password is `SSCN_ADMIN_PASSWORD` in the private `.env`.
The web server binds only to this computer; stop it with Ctrl+C. To stop the
domestic database, run `docker compose --env-file .env stop db` from this repo.
Do not use `down -v`, which would delete the local database.

For a fresh installation (or to reapply local defaults):

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
| Preview visibility / indexing | Live preview / indexing discouraged |
| Email | Blocked in the local runtime |
| Time zone / measurements | Asia/Shanghai / grams and centimeters |

Initial WordPress/WooCommerce versions match the inspected international baseline
(7.1.3 / 11.2.1). The homepage and catalog are visible in the local preview;
all 11 product pages are visible. All 11 products support pack building. The Chinese WordPress,
WooCommerce and plugin translations are installed. Populated product, filter and comparison checks have passed. Domestic checkout
validation remains pending payment and fulfillment decisions.
No domestic gateway, courier, SMS provider or WeChat application is configured.
Domestic pack sizes are 4/6/12, defaulting to 4. All 11 published products now have approved
pack prices and initialized stock; payment and delivery setup remain pending. See
[domestic pack prices](docs/domestic-pack-prices-20261020.md).

`tools/seed-local-pages.php` only seeds pages once and preserves subsequent editor
changes. The official WooCommerce translation download has a curl fallback for
local updater URL-validation failures; it does not weaken WordPress HTTP checks.
After changing plugin translations, run
`bash tools/wp.sh eval-file tools/compile-translations.php`.
See `docs/local-preview-validation.md` for the runtime checks.

## Server deployment and GitHub

Supply the new GitHub repository URL when it is ready. Do not attach the
international remote. The repository can then be connected and pushed explicitly.
Custom code is synchronized with `python3 tools/deploy/sync-code.py --apply`.
This targets only claw-host and never copies local credentials, databases or uploads.
See [the deployment runbook](docs/deployment-claw-host.md) for routing, credentials,
validation and rollback. The `--bootstrap` option is for a fresh installation only;
it refuses to overwrite the deployed site.

See [the catalog import record](docs/catalog-import.md) for the approved content,
validation, repeat-run behavior and how to release preview products for sale.

See [six sample-product drafts](docs/sample-drafts-20261019.md) for the newly ordered candidates, source qualifications and draft-only import.

See [publication and inventory](docs/publish-stock-20261020.md) for the latest approved state and verification.

See [original five domestic prices](docs/original-domestic-prices-20261020.md) for the completed pricing schedule.

## Domestic login and payments

Independent WeChat login, WeChat Native/JSAPI/H5 and Alipay Page/WAP integrations
are implemented in `sscn-commerce`, default disabled pending platform/domain
and credential setup. See [domestic commerce](docs/domestic-commerce.md) for
configuration, tests and activation requirements. Refund APIs are not enabled.
