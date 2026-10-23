# Domestic WeChat login and payments

## Account-page update — 2026-10-20

The operator authorized ordinary WooCommerce registration on My Account and
checkout. Apply `tools/deploy/enable-customer-registration.php` through WP-CLI
after code sync; it preserves initial values in
`sscn_registration_before_20261020`. Registration and checkout privacy hints are
Chinese. WordPress general registration and OAuth/payment activation stay unchanged.

The account page uses responsive login/register columns. A disabled WeChat button
is visible to guests, explicitly marked 暂未开放, with email login guidance.
It has no OAuth link until the existing domain/configuration gates pass.
The placeholder is hidden for logged-in customers; account binding is still gated.
Local tests cover the non-actionable placeholder and the existing OAuth safeguards.
Actual email delivery and live WeChat authorization have not been tested by this update.
Validation: `composer check`, local OAuth integration tests and
`WC()->checkout()->is_registration_enabled()` passed. Deployed through the
existing sync script to release `20261020T041010820442Z`, then applied the
registration script. Live guest-page inspection confirms both forms and the
disabled WeChat button; CNY and live-preview visibility are preserved.
The existing domestic privacy-policy page (ID 3) is still a draft; its publication
and real email delivery remain outstanding before public launch.

Implemented 2026-10-20 in the independent `sscn-commerce` plugin. Reference code
was read from `bb/deploy/woocommerce/rakumo-payments` and the bbserver WeChat
login handlers. Neither bb nor the international store is changed. WooCommerce
owns customers, orders, stock, taxes and order totals; no bb wallet or account API
is used. Runtime dependencies are locked in `commerce-libraries/composer.lock`.

## Behavior

- Website WeChat QR OAuth (`snsapi_login`) and official-account in-WeChat OAuth
  (`snsapi_base`) create or sign in a WordPress customer. No nickname/avatar/email
  is fetched. Billing email/address can be supplied at checkout normally.
- OpenID is scoped by AppID. Existing logged-in customers can explicitly bind an
  additional app identity through the account page. No automatic email/UnionID
  merge. An identity already bound to another customer cannot be reassigned.
  Privileged WordPress users cannot use this customer login mechanism.
- OAuth state has 256 bits of randomness, five-minute expiry, one-time consumption,
  a Secure/HttpOnly/SameSite=Lax browser cookie and a binding to the initiating WP
  session. Tokens remain server-side and are not persisted. Redirects stay on-site.
- WeChat Native QR is used on desktop; JSAPI in WeChat; H5 in a mobile browser.
  Each mode must be explicitly enabled after the merchant product is approved.
  JSAPI uses the official-account AppID's OpenID; website-login OpenIDs cannot be
  substituted. If missing, the payment page offers authorization then returns to
  the same order without creating another order.
- Alipay uses signed desktop Page or mobile WAP forms from the official SDK.
- Orders use `SCN{order_id}X{random}` references and independent `_sscn_` metadata.
  Callback signatures, merchant/app IDs, currency, integer fen amount and order
  attempt are checked before `payment_complete`. Duplicate confirmation does
  not reduce stock twice. Browser success results are never proof of payment.
- Background query, customer status polling and a manual order action recover
  missed notifications. Attempts expire after one hour; changed amount/method/
  device flow requires a new order. No silent replacement of payable attempts.
  A late successful payment on an already cancelled/failed/refunded order needs
  manual review; it is not silently fulfilled. Do not discard such payments.
- Refunds are currently performed in the merchant dashboard and recorded as a
  manual WooCommerce refund. No automatic refund API is advertised.

## Disabled until platform/domain setup is complete

Three gates apply: a private `live_enabled` switch; explicit per-app/provider/
mode flags; and WP login/payment enable settings. Missing SDKs, credentials or
wrong domain fail closed. Code activation alone enables no customer entry point.
Disabling new payments does not disable verification of already-created orders.

Admin: **WooCommerce → 国内登录与支付** shows the login switch and callback URLs.
Payment switches: **WooCommerce → 设置 → 付款**. Never enter secrets in the database,
front-end scripts, source tree or logs. Credentials must belong to the intended
merchant/application and be authorized for this store.

Copy `commerce-libraries/config.example.json` to
`/etc/sockspecs-cn/commerce/config.json`, outside the document root. Directory:
root:sockspecs-cn 0750; JSON/key files: root:sockspecs-cn 0640. The original
platform verification *public key ID* belongs in `platform_serial`; the matching
public key or certificate goes in `platform_cert`. Do not confuse this with the
merchant signing certificate serial. Key rotation requires updating this private
copy. The SDK handles signed requests and response verification.

The local ignored `.local/commerce/config.json` and production private config
now contain the operator-authorized selected bbserver login/payment parameters.
The nine referenced secret variables were resolved read-only from the source
service environment file on claw-host. Six PEM files are stored in private
versioned credential directories. All switches remain off. These files are NOT
part of code sync. The source service configuration was not changed.
Use `SSCN_COMMERCE_CONFIG` and `SSCN_COMMERCE_AUTOLOAD` wp-config constants only
for local/testing overrides; production defaults target this store's private paths.

After ICP/domain setup, configure/verify:

1. Website application's approved login domain and official-account OAuth domain:
   `sockspecs.cn`. Callback: `https://sockspecs.cn/?sscn_auth=callback`.
2. Official-account AppID bound to the intended payment merchant and identical to
   the JSAPI payment AppID. Confirm Native/JSAPI/H5 and Alipay Page/WAP permissions.
3. JSAPI payment directory covers the actual WooCommerce order-pay URL. For the
   current plain-permalink configuration the root directory is
   `https://sockspecs.cn/`; reconfirm after permalink changes. Configure H5 domain.
4. Public notifications:
   `https://sockspecs.cn/?wc-api=sscn_wechat` and
   `https://sockspecs.cn/?wc-api=sscn_alipay`. Public DNS/HTTPS must work; a local
   hosts override does not make callbacks accessible to providers.
5. H5 requires the real public payer IP. Only configured trusted proxy peers may
   supply it; the final X-Forwarded-For entry must be appended by that proxy.
   Confirm claw-hub's behavior before enabling H5. Private proxy IPs are rejected.
6. Fill private credentials, enable only approved modes, then enable the master
   gate and WP switches. Verify on desktop, inside WeChat, and a mobile browser.
   Perform a user-approved small real purchase and refund; verify order amount,
   callback, stock reduction, duplicate notification and refund reconciliation.

No DNS, merchant-platform configuration, source API service or store visibility
was changed. Domestic private credentials were subsequently populated with
explicit operator authorization; live entry points remain disabled.

## Validation and operations

- `composer check`: syntax, existing standards/bootstrap/isolation, offline signed
  payment tests. `node tests/commerce-js.js`: JSAPI unavailable/cancel/success paths.
- `bash tools/wp.sh --user=sockspecs_cn eval-file tests/commerce-login.php`:
  local-only generated identities, disabled gates, account reuse, binding and
  privilege rejection, state/browser/expiry/replay, redirects, mocked OAuth.
- `bash tools/wp.sh --user=sockspecs_cn eval-file tests/commerce-orders.php`:
  local-only temporary order/product, amount rejection, payment completion and
  stock exactly once, conflicting transaction rejection. Fixtures deleted.
- The provider tests exercise Native/JSAPI/H5 through an in-memory Guzzle
  HandlerStack with signed mock responses, and Alipay Page/WAP form generation.
  During development an initial plain handler was ignored by the SDK and fake
  test requests received HTTP 400 (invalid merchant). This was fixed with a
  strictly typed HandlerStack. No real credentials/payment were used; final tests
  use the mock transport and make no provider requests.
- Existing domestic sync script installs locked SDK dependencies locally, tests,
  and transfers plugin + private runtime dependencies in the atomic release.
  Only the plugin is linked into wp-content; SDK fixtures/libraries stay outside
  the public document root. Symlink/activation rollback is included for failure.
- Backup before deployment:
  `/var/backups/sockspecs-cn/commerce-20261020/before.sql`.
- Manual rollback to a pre-commerce release: deactivate `sscn-commerce` first,
  then restore the old `current` symlink and reload PHP-FPM. Keep private config
  and order data for investigation; never delete payment state to retry.

Sources checked:
- https://developer.woocommerce.com/docs/features/payments/payment-gateway-api/
- https://developer.woocommerce.com/docs/block-development/extensible-blocks/cart-and-checkout-blocks/checkout-payment-methods/payment-method-integration/
- https://pay.wechatpay.cn/doc/v3/merchant/4012791834
- https://pay.wechatpay.cn/doc/v3/merchant/4012791856

## Deployed result

Release: `/srv/sockspecs-cn/releases/20261020T030410896639Z`. Both official SDKs
load successfully; both gateways are registered with `enabled=no`,
`available=false`; login option remains `no` and the live gate is false. Store
visibility remains `no` for coming-soon and currency remains CNY, as before.
Local WordPress integration tests passed with all temporary users/orders/products
removed. Deployment checks and provider/JS tests passed.

## Authorized credential copy completed

On 2026-10-20 the operator explicitly authorized copying existing bb parameters.
The selected values came from bbserver configuration and its `/d/apps/bbserver/app.env`
on claw-host, without modifying either. Only the two login secrets, WeChat API
v3 secret, six key/certificate materials and required identifiers were copied.
Alipay DER private-key material was normalized to PEM without changing the key.
Alipay application private key matches its certificate; each certificate bundle
has a currently valid certificate; WeChat uses a platform public key ID. Official
account login AppID matches payment AppID. Both private RSA signing keys passed
offline signature verification. No provider authentication, domain authorization,
product entitlement or real payment is claimed by these offline checks.

Local files are owner-only; server directory is root:sockspecs-cn 0750 and files
0640, outside the document root. The local config is Git-ignored. Private config
backups/versioned key directories support rollback; source key rotation must be
propagated deliberately to this independent copy. No broad environment file was
copied.

Post-copy validation passed in both local WordPress and production as the
domestic service user: all fields/files readable, actual merchant key signing
and SDK Alipay Page/WAP form generation work offline, login/payment switches
still disabled. Parent `/etc/sockspecs-cn` uses root:sockspecs-cn 0710 (traversal
only for this service); its existing credentials.json remains root-only 0600.

Production already has BCMath. Local PHP lacked it; the matching Ubuntu
php8.3-bcmath 8.3.6-0ubuntu0.24.04.11 package was extracted into ignored
`.local/php-bcmath`, without system package changes. `tools/local-env.sh` adds
its private INI directory for domestic CLI/preview processes. Restart the local
preview to pick this up before enabling any real payment testing. The SDK's
small fixture certificates had not exercised the large-serial BCMath path;
actual-credential offline verification now covers it.
