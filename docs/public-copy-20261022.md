# Domestic public-copy review — 2026-10-22

The operator requested removing redundant and unnatural customer-facing language,
starting with “直接下载，无需提前联系”.

## Changes

- Reviewed all published domestic pages/posts and the eight published product descriptions.
- Updated nine existing pages/posts: home (15), about (14), referral explanation (155),
  return policy (157), guide index (163), price/material/thickness guides (164–166),
  and wear-feedback page (187).
- Feedback copy now explains the form, submission, eligibility and voucher terms once.
  Removed internal workflow commentary, repeated reassurance and excessive emphasis.
- Home no longer describes only five products or says prices/stock are unprepared.
  Retains the preview/payment-unavailable notice.
- Kept the hypothetical material-weight example, composition/report scope distinctions,
  and practical thickness/terry guidance. Removed conversation-specific rebuttals.
- Simplified customer-support, product-feedback and footer copy in theme templates.
- Reward amounts, eligibility, order deduplication, refund/return conditions, privacy
  consent, product facts and commerce behavior were not changed.

## Publication and recovery

- Local content: tools/deploy/content/*.html and copy-review-20261022.json.
- Guarded deployment: tools/deploy/update-public-copy.php plan/apply/verify.
  All source hashes are checked before any write; concurrent editor changes abort.
  Only post_content/post_excerpt of the nine known published objects are updated.
- Previous content saved in non-autoloaded option sscn_public_copy_before_20261022.
- Existing sync-code.py --apply deployed release 20261022T042530962695Z;
  prior code release 20261022T033908180530Z remains available.
- Content publishing and code rollback are separate operations. Restore reviewed
  fields from the saved option if a content rollback is required; do not reapply
  older initialization scripts to the whole store.

## Validation

- HTML structure checks for all nine payloads passed.
- Existing deployment checks (PHP lint, coding standards, bootstrap, domestic
  isolation, payment-provider mocks and JavaScript suites) passed.
- Production plan, apply and verify succeeded; protected settings unchanged.
- Verified updated text through HTTPS on all nine pages, contact, product, cart
  and account pages (13 URLs). Confirmed removed phrases absent and PDF available.
- Opened feedback page in the in-app browser and reviewed rendered text/links.
- Product titles, descriptions and excerpts matched the pre-change snapshot.
- git diff --check passed. No payment transaction, email, coupon issuance or new
  customer/order was created. International site was not accessed or changed.
