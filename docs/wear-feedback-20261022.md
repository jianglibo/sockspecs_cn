# Wear-feedback download page (2026-10-22)

Operator approved adding the printable survey to the domestic site, customer
submission through existing support channels and a manually issued coupon reward.

## Implementation

- Chinese page `wear-feedback`, title 穿着体验记录, saved ID option
  `sscn_wear_feedback_page_id`. Page content is editable in WordPress.
- Original approved one-page A4 PDF copied unchanged into theme assets/downloads;
  same-origin download attribute and separate new-tab preview link. Full site URL
  already printed on the form. SHA256:
  b3952aac6de90ab27a77e6b31a723d265baa9b147b3001b25e8ba3185607fd0b.
- Actions are small theme shortcodes. Submissions go to the existing WeChat support
  page or hello@sockspecs.cn. Mail link opens a client, does not send or attach files.
- Product summary invitation, footer link and paid-order thankyou invitation.
  Native thankyou hook works for classic and block AdditionalInformation templates
  (verified in installed WooCommerce source). No order data is placed in URLs.
- Real positive/negative feedback has equal eligibility; one reward per order;
  publication permission separate. Amount/expiry/minimum/stacking are not invented:
  customers are told to confirm these with support before participating.
- No coupon creation/issuance automation, uploads, emails, order/stock/payment or
  public DNS changes. Staff manually review/issue coupons and track prior rewards.

## Apply and rollback

Run normal `tools/deploy/sync-code.py --apply`, then WP CLI as domestic admin:
`eval-file .../tools/deploy/publish-wear-feedback.php apply`.
Defaults to verify; guards site URL, DB, currency, capability. Refuses overwriting
an existing page with different content. Repeated apply does not duplicate pages.
Previous code release rolls back new hooks; unpublish the generated page to hide
its entries. Original PDF outside the repository is untouched.

## Checks

PDF text and visual rendering verified, one A4 page. Local HTTP PDF download
returned application/pdf and identical bytes. Local browser verified page copy,
preview/download links and support channels. Product markup has one invitation;
footer points to saved page. Repeated apply verified. Composer checks run via the
normal deployment script. No test orders or real feedback were submitted and no
coupons/emails were sent; paid-order display is code-integrated, not a completed
real purchase test.

Deployed release 20261022T030721510546Z (rollback 20261022T022138751695Z).
Published server page ID 187: https://sockspecs.cn/?page_id=187.
Browser verified page copy, footer and links; actual server PDF download succeeded
at /home/jianglibo/Downloads/sockspecs-wear-record.pdf with matching SHA256.
Screenshot: /tmp/sockspecs-cn-wear-feedback.png.

## Later update: manual register now implemented

The initial manual/offline tracking limitation above is superseded by
`wear-feedback-register-20261022.md`: dedicated feedback email, order-keyed register,
private optional attachments, manually confirmed one-time coupons, and revised PDF.
