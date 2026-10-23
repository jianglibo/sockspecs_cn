# Domestic traffic analytics

Installed Matomo for WordPress 5.13.1 on 2026-10-20, locally and on claw-host. Official WordPress.org plugin checksums passed on both. The international store was not changed.

## Operator access

- WordPress → Matomo Analytics → Summary / Reporting.
- Summary: https://sockspecs.cn/wp-admin/admin.php?page=matomo-summary
- Detailed reports: https://sockspecs.cn/wp-admin/admin.php?page=matomo-reporting
- Records page views, visits, page titles/URLs, referring sites and campaign tags from activation onward. Does not reconstruct past traffic.
- Chat applications may omit referrers. Tag links, for example `https://sockspecs.cn/?utm_source=wechat&utm_medium=social&utm_campaign=launch`. The core campaign report shows the campaign name; do not promise paid advanced marketing reports.
- The test campaign `analytics_setup_20261020` is installation verification, not customer activity.

## Configuration

`tools/deploy/configure-domestic-analytics.php apply|verify` is a guarded staff WP-CLI helper. Local database must be sockspecs_cn, with either localhost:8089 or exact HTTPS domestic home. It preserves a one-time pre-configuration snapshot in `sscn_analytics_before_20261020`.

- Automatic frontend page tracking; no admin backend tracking.
- Administrator and shop_manager visits excluded; anonymous visitors and customers included.
- Asia/Shanghai time, CNY currency.
- Cookies disabled, user IDs disabled, IPs masked by two bytes and masked for enrichment.
- Referrer query strings excluded; known sensitive/account/order/referral query fields excluded from stored page URLs. Product and page identifiers remain visible in reports.
- Server trusts X-Forwarded-For because claw-hub overwrites it and the domestic origin accepts only claw-hub and loopback. Local preview has no trusted proxy header.
- Tracking script, collector and database reside on the domestic site. This does not depend on Google Analytics.
- Ecommerce tracking explicitly disabled until pack quantity/value semantics and asynchronous payment/refund attribution are tested. Orders remain available in WooCommerce Analytics.
- Preserve Matomo data on plugin uninstall. To pause, use Matomo Settings to disable tracking or deactivate the plugin; do not delete tables.

The third-party plugin is an independently installed dependency, outside custom release payloads. Install the pinned official plugin through WP-CLI/WordPress and verify checksums. Apply custom configuration through the existing `tools/deploy/sync-code.py --apply`, then execute the helper as the domestic administrator. Do not copy local WordPress settings/database to the server.

Server backup before installation: `/var/backups/sockspecs-cn/analytics-20261020/before-install.sql`. Restoring a full database can overwrite later orders; prefer deactivation to roll back analytics alone.

## Verification

- Local and server configuration checks passed: guest tracking present, administrator tracking absent, same-site script, cookieless mode, anonymization, currency/timezone, proxy configuration.
- Full standard custom-code deployment checks passed.
- An actual browser opened the catalog with a marked test campaign. Server recorded one visit and one page view, the Chinese page title, and the campaign name.
- Ran `matomo_scheduled_archive`; reporting APIs confirmed VisitsSummary (one visit/action), Actions.getPageUrls (catalog URL), and Referrers.getCampaigns (test campaign).
- Existing domestic system cron runs due WordPress tasks every five minutes; Matomo schedules report archiving itself. Reports need not update instantly.
- Existing visibility, public DNS, email senders, payment and WeChat gates were preserved. Public access remains governed by the existing preview deployment.

Official documentation: https://matomo.org/guide/installation-maintenance/matomo-for-wordpress/

## Diagnostics follow-up — 2026-10-20

The setup page reported a cached diagnostics flag. A fresh privileged Matomo system check identified one warning: vendor YAML files were publicly readable under Nginx (Apache .htaccess rules are not applied by Nginx). Added a scoped YAML/YML deny rule to the domestic Nginx template and deployed with the existing code sync. `tools/deploy/harden-matomo-nginx.py` applies only that expected template change on claw-host, backs up the prior site config, validates Nginx and reloads with rollback on failure. It does not change claw-hub or API routing.

Validated after application: vendor .yml and .yaml return 403; tracker JS, collector, homepage and health endpoint return 200. Fresh Matomo diagnostics have no errors/warnings; recomputed the cached diagnostic flag from those successful results (0), rather than suppressing checks. Archive cron and page/source reporting APIs still work. Historical installation-time cron failure was retained in the log; current archive execution succeeds. Nginx backup: `/var/backups/sockspecs-cn/analytics-20261020/nginx-112514420470.conf`.
