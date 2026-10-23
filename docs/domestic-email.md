# Domestic transactional email

Configured on 2026-10-20, independently of sockspecs.com.

- Order messages: `SockSpecs <orders@sockspecs.cn>`.
- WordPress account messages and WooCommerce registration/password reset: `SockSpecs <hello@sockspecs.cn>`.
- Customer-facing replies: `hello@sockspecs.cn`. Admin order alerts preserve the customer Reply-To.
- The supplied `admin@sockspecs.cn` is the test recipient; this work does not change WordPress administrator or order-alert recipients.

## Configuration and deployment

WP Mail SMTP 4.10.2 uses Cloudflare SMTP (`smtp.mx.cloudflare.net`, implicit TLS port 465, username `api_token`). The token is read from `/etc/sockspecs-cn/email.json`, outside Git, the web root and WordPress options. That file is root:sockspecs-cn, mode 0640, with `token` and boolean `enabled` fields. Never print its contents.

`tools/deploy/cloudflare-email.php` is loaded by the domestic preview MU guard. Sending requires the domestic database, exact HTTPS home URL, domestic deployment flag, enabled private configuration and the SMTP plugin. Otherwise normal mail is blocked, including unauthenticated PHP-mail fallback. Local preview remains blocked. Production-like email is explicitly enabled for this staging preview at the operator's request; payment and login integration gates are unchanged.

`tools/deploy/configure-domestic-email.php` configures WooCommerce sender/reply settings and disables the SMTP plugin's stored force-from preference. The false constant alone does not override the plugin default. Previous options are retained in `sscn_email_before_20261020`.

Custom code is deployed with `python3 tools/deploy/sync-code.py --apply`. The SMTP plugin is a separate WordPress dependency and was installed with official checksums verified. To pause sending, set only `enabled` to false in the private file, retaining ownership and mode. Do not disable the MU guard or enable PHP-mail fallback.

## Validation

- Local and server read-only integration checks: `tests/domestic-email.php` (17 checks, no messages sent).
- Existing deployment checks passed, including domestic isolation and commerce tests.
- Cloudflare authenticated the server and accepted both sender addresses.
- One account test from hello and one order-style test from orders were submitted to `admin@sockspecs.cn`. No real order or password was changed. SMTP acceptance does not prove inbox delivery; check inbox and spam.
- The order-only test was accepted on 2026-10-20 at 10:52:30 UTC. The account test had already been accepted before correcting the test helper's WooCommerce argument count, so it was not resent.

For explicitly authorized future tests, the staff CLI helper `tools/deploy/test-domestic-email.php` accepts a recipient followed by `account`, `order`, or `both`. Do not run it routinely or retry blindly after an uncertain result.

Official SMTP documentation: https://developers.cloudflare.com/email-service/api/send-emails/smtp/
