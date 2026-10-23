# Domestic WeChat customer support — 2026-10-21

Operator authorized publishing the QR image and customer-service URL from the
Hotmail message “微信客服二维码”, received 2026-10-21. Only the public service
materials were copied; no mailbox credentials or unrelated email were retained.

- Public destination: https://work.weixin.qq.com/kfid/kfc2e4782f067438e20
- Original attachment: theme/assets/wechat-support-qr.png (700 × 908).
  QR decoding matches the supplied service link exactly; original bytes preserved.
- theme/customer-support.php provides the contact shortcode and compact entry
  points after the product pack/returns area, account dashboard, login/registration,
  and order thank-you hook. Theme footer exposes the contact page site-wide.
- Page publication: tools/deploy/publish-customer-support.php apply|verify,
  guarded to the domestic database/domain and administrator CLI. Stores the page ID
  in sscn_support_page_id and a prior-state snapshot in sscn_support_before_20261021.
- The image is self-hosted. No third-party script, tracking integration, automatic
  chat opening, customer-data forwarding or external API credentials are added.
- This is a WeChat customer-service conversation entry, not an add-friend QR code.
  No guaranteed response time, availability or automatic friend connection is claimed.
- Current fixed public destination is maintained in sscn_wechat_support_url();
  replacing it requires updating the matching QR image and rechecking its decoded URL.

Local rendering and 13 support-link/hook/asset checks passed. Existing deployment
runs Composer checks and domestic isolation/provider/JavaScript tests. Browser
review checks the support page, product entry and original QR. Real WeChat app
handoff/replies require a phone; no message or order is sent during verification.

Deploy using the existing tools/deploy/sync-code.py --apply, then publish the page
as domestic administrator. Preserve visibility, credentials, payment gates,
products/prices/stock and public DNS. The international store is out of scope.
Rollback code with the previous release, then restore the page/option snapshot if
rolling back content as well.

Published contact page: https://sockspecs.cn/?page_id=158 (local page 244).
Release: /srv/sockspecs-cn/releases/20261021T051409439926Z.
Rollback code: /srv/sockspecs-cn/releases/20261021T035925921963Z.
The 13 read-only support checks passed on production as well. Browser verification
confirmed the contact page, QR display, footer links and X109 purchase-area entry.
Screenshot: /tmp/sockspecs-cn-wechat-support.png.
The embedded browser security policy blocked navigation to the external WeChat
service page; no workaround was attempted. The QR/link match is verified, but
actual mobile WeChat handoff and a customer-service reply remain untested.
