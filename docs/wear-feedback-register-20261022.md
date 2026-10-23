# Domestic manual wear-feedback register — 2026-10-22

Workflow: email received → staff review → WooCommerce / 穿着反馈 → look up order →
record summary and optional attachment → confirm ¥2–¥10 coupon → manually reply.
No email ingestion, automatic replies or automatic award decisions.

## Data and duplicate prevention

- `sscn_wear_records`: primary key is WooCommerce internal order ID; stores status,
  reserved reward amount/code/email, coupon ID, staff and timestamps.
- `sscn_wear_entries`: append-only notes, optional order item, staff/time and optional
  PDF/JPEG/PNG ≤5 MiB stored in a private database blob, never the public media library.
  Attachments download through an authenticated capability + nonce-checked endpoint.
- MySQL named lock per order serializes registration/issuance. Unique order and coupon
  keys prevent duplicate entitlements; per-form UUID prevents duplicate notes on retry.
- Reserve coupon code BEFORE creating via WooCommerce CRUD. Interrupted saves recover
  the same coupon, including a partially saved coupon post. Coupon validation denies
  use until ledger confirms issuance. Deleting/trashing a coupon does not reset reward.
- Issuance requires staff review confirmation, valid paid CNY order, valid billing
  email, prior feedback and amount within range. Subsequent notes preserve awarded state.
- Coupons use native fixed-cart discount, original billing-email restriction, global
  and per-user usage limit 1, all products, no minimum or fixed expiry. Other promotions
  can still prohibit stacking. Noncash and no change. No customer email is sent by this code.
- Uses native WooCommerce order CRUD, including HPOS; no direct writes to order tables.
  Order lookup currently uses the store's native numeric order ID/number (no sequential
  order-number plugin integration). Admin capabilities and nonce protect mutations.

## Customer material

Page 187 removes advance contact, explains range and one reward per order, accepts
negative feedback equally, and names feedback@sockspecs.cn. Original known page content
is backed up in an option and migration refuses unrelated editor changes.
PDF is A4 one page / eight use rows, with order number and purchase email/account.
PDF URL includes version query to avoid the previous cached copy.

Cloudflare: existing domestic domain routing, new feedback@sockspecs.cn alias forwards
only to existing verified jianglibo@hotmail.com, like hello/admin. No Worker, new
recipient verification, DNS, other routes, sender credentials or international changes.
Dashboard verified Active; no test email sent.

## Validation

- composer check.
- Local WordPress integration: test-only order/coupon cleanup, duplicate form and coupon,
  invalid amounts, unpaid orders, unauthorized users, foreign item, exact attachment
  persistence, subsequent notes, matching/wrong email via WC_Discounts, concurrent lock
  rejection, partially created coupon recovery, deleted coupon no reissue.
- Local browser: native login, order lookup, summary submit, issue coupon, awarded UI.
- PDF rendered and visually reviewed, one page and full site/email address.
- No real customer order, coupon issuance, email or payment test on the server.

Deployment follows tools/deploy/sync-code.py --apply plus publish-wear-feedback.php apply.
Production table setup runs on administrator access or explicit installer call. Preserve
store visibility, orders/stock/prices and login/payment gates.

## Deployed result

Release `20261022T033908180530Z`; rollback code release `20261022T030721510546Z`.
Content helper applied successfully to page 187; prior content retained in the backup option.
Live schema version 1, zero feedback records/entries; visibility remains `no` (live preview).
PDF SHA256 `3e6c7faa12115d9566fdd2bcb841d768a88079ba48de9fdf404cafd11f088d07` matches local.
Browser verified domestic admin menu and public page including dedicated email, direct
participation and new PDF link. All local browser test orders/coupons were removed.
