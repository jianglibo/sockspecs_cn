# Domestic server preview

Deployed 2026-10-19. Public DNS is unchanged. The browser preview is https://sockspecs.cn/.

## Layout

- TLS proxy: claw-hub (139.196.167.200 / 172.16.3.52), HAProxy.
- Origin: claw-host (172.16.3.53), Nginx on private/loopback port 18081.
- Origin accepts only claw-hub and loopback on that listener.
- Site: /srv/sockspecs-cn/public; dedicated OS account/PHP pool sockspecs-cn.
- Database/login: sockspecs_cn; grants restricted to this database.
- WordPress 7.1.3, WooCommerce 11.2.1; Chinese, CNY, Asia/Shanghai.
- Code releases: /srv/sockspecs-cn/releases; current is an atomic symlink.
- The five specifically authorized product/image/specification snapshots are imported;
  no international commerce data or credentials are used.
- Live preview is enabled. Five Chinese products with 52 variants are visible,
  with prices and stock quantities unset and purchasing blocked per product.
  No payment gateways are enabled. Preview mail is blocked.
- TLS certificate covers sockspecs.cn and *.sockspecs.cn, expires 2027-01-17.
  Certificate renewal remains with the existing certificate-management process;
  this deployment did not create a new certificate issuer or renewal job.

## Hosts-only access

This computer's /etc/hosts contains:

```
139.196.167.200 sockspecs.cn www.sockspecs.cn
```

The origin maps the same names to 172.16.3.52 for its own HTTPS loopback requests.
These mappings do not change public DNS. Removing the names restores normal DNS
resolution; preserve unrelated entries. Hosts-file backups were saved next to
/etc/hosts with a before-sockspecs-cn timestamp.

The local privileged edit used the existing local SSH service on port 22 after
verifying its ED25519 public host key against /etc/ssh/ssh_host_ed25519_key.pub.
Direct sudo from the tool environment was unavailable. No SSH verification was disabled.

## Administration

URL: https://sockspecs.cn/wp-admin/ ; username: sockspecs_cn.
Newly generated credentials are root-only at /etc/sockspecs-cn/credentials.json on
claw-host. The administrator login only is also saved locally in ignored,
mode-0600 .local/claw-host-admin.json. Do not commit or display those values.

The PHP pool limits memory to 256 MB per worker and has at most four on-demand
workers. Cron runs due WordPress events every five minutes. Site logs have weekly
rotation. Core/plugin automatic updates and dashboard file modifications are
blocked for this preview; runtime upgrades should be deliberate and tested.

## Updates

```
python3 tools/deploy/sync-code.py          # scope only, no connection
python3 tools/deploy/sync-code.py --apply  # source checks, upload, atomic switch
```

Only the domestic plugin, theme and deployment helpers are packaged. Existing
WordPress data, uploads, credentials and host configuration are preserved by normal
code sync. PHP workers are gracefully reloaded after the switch to clear OPcache
and symlink/translation caches. Initial bootstrap refuses an existing site or DB.
If an initial installation fails after WordPress is configured, the guarded
remote finish-bootstrap mode can finish its own incomplete installation without
recreating credentials or data; inspect the failure before using it.

## Recovery

Initial site/database/config backup on claw-host:
/var/backups/sockspecs-cn/20261019T020841Z

HAProxy and previous certificate bundle on claw-hub:
/var/backups/sockspecs-cn-proxy-20261019T020351Z

Both directories are private. Gzip integrity checks passed for the initial
WordPress backup. It is an on-server recovery copy, not an off-server backup.
Previous custom-code releases are retained; point current back to the desired
release and gracefully reload PHP to roll code back. Schema/data restoration is a
separate operation. Always validate Nginx/PHP/HAProxy configs before reloads.

## Validation

- WordPress core checksums, PHP lint/WPCS/bootstrap/isolation tests and pack JS checks passed.
- Deployment guards reject a wrong server and refuse overwriting an existing site.
- Browser homepage and Chinese catalog render over HTTPS without insecure assets.
- Product preview markers prevent purchasing; no enabled payment gateway or
  configured domestic fulfillment is claimed.
- HAProxy checks: apex 200, www 301, API /health 200; WeChat login redirect checked.
- The initial atomic release exposed stale PHP translation-path caches. A graceful
  PHP reload fixed this; reload is now included in normal code synchronization.
- Local command-line curl/OpenSSL direct requests to the new domain encountered a
  TLS EOF on this computer's network path, while both the in-app browser and
  Playwright loaded the HTTPS site successfully and server-side curl verified TLS.
  No certificate validation was disabled. The precise command-line path issue is
  unconfirmed; it is not evidence that the browser preview is blocked by filing.

Products are imported; domestic pricing, inventory, payment, fulfillment and
public launch remain future work. See `catalog-import.md` for the 334 runtime
checks and the reviewed content snapshot.
