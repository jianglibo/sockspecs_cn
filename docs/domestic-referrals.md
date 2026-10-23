# Independent domestic referral rewards

Approved on 2026-10-20: referred first purchase gets ¥5 off the product total;
the referrer earns ¥5 shopping credit after the order is marked Completed.
Staff should mark Completed after delivery, not merely when payment arrives.
Credit accumulates without expiry or an earning cap, applies to all domestic
products up to their full product total, and cannot be cashed out or used for shipping.

The domestic fork adapts the existing Referrals, Referral_Store and Referral_UI
classes. It does not load data from the international store. Balances use integer
fen, CNY-only checks, domestic database/domain guards, independent
`sscn_referral_*` tables and options, site-signed `sscn-ref-` codes,
`sscn-credit` coupons and `sscn_ref` links. No account, order or credit import.
The rule is centralized as `Referrals::REWARD_FEN = 500`; changing rewards later
requires reviewing existing order obligations rather than blindly changing it.

The native WooCommerce fixed-cart coupon flow remains in charge of discounts and
taxes. Both classic checkout and Store API order hooks reserve credit before
payment. Database transactions and a mutex protect balance spending and first-order
claims; repeated callbacks reconcile to target values rather than adding twice.
Unpaid cancelled attempts release claims; past paid/refunded buyers remain ineligible.
Self-referral checks use account ID and billing email, not a claim of unique-person
identity verification.

Cancellation/full refunds reverse the referral reward. Partial refunds preserve
the reward while a purchase remains and restore spent credit proportionally.
Previously spent reversals create a deficit offset by future earnings.
Pausing enrollment leaves ledger/history and refund reconciliation active.

## Deployment

1. Compile Chinese PO/MO with `bash tools/wp.sh eval-file tools/compile-translations.php`.
2. Run `composer check`, `node tests/pack-builder-js.js`, and local-only
   `bash tools/wp.sh --user=sockspecs_cn eval-file tests/domestic-referrals.php`.
   The latter generates synthetic customers/orders/products, blocks email/HTTP,
   exercises real separate-process races and removes only its generated records.
3. Back up the domestic database. Use `tools/deploy/sync-code.py --apply`.
4. On the domestic server, run `tools/deploy/enable-domestic-referrals.php apply`
   through WP-CLI as store staff. This creates the InnoDB tables, Chinese page,
   primary-menu link and recovery schedule. Repeat application preserves balances
   and page edits and does not duplicate the menu.
5. `verify` checks readiness; `pause` stops new rewards while keeping historical
   accounting active. Do not delete the tables to disable the program.

Discovery: 推荐有礼 in the primary menu, product-page invitation, order confirmation,
and 推荐与购物余额 in My Account. Logged-out users see a login/registration link;
signed-in users see only their own referral link and account balance.
Public DNS, login/payment release switches, product prices, stock and visibility
are preserved.

Local validation: 71 lifecycle/concurrency assertions passed, including ¥5 native
discount, completion timing, duplicate events, refunds, deficit offsets, full
product-total redemption, concurrent spend/first-order protection, failed-query
rollback, domestic 4-pair pack integrity, USD rejection and international-code
rejection. No live payment or real customer referral was performed.

Deployed release: `20261020T103502147841Z`. Database backup:
`/var/backups/sockspecs-cn/referrals-20261020/before-103442.sql`.
Server apply/verify passed; page ID 155 is published and in the primary menu.
The three domestic reward tables start empty, recovery is scheduled, and CNY,
live-preview visibility and disabled commerce authorization are unchanged.
The live guest explanation page was inspected in the browser.
