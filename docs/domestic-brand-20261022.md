# Domestic brand: 索克派 (2026-10-22)

Operator selected 索克派 as the domestic Chinese name alongside SockSpecs.
The domain remains sockspecs.cn. Trademark registration is out of scope.

## Presentation

- Header: 索克派 SockSpecs, 看懂一双袜子. The company name was removed
  from the header at the operator’s request; it remains in the footer and title.
- Footer: 索克派 SockSpecs and existing legal operator name.
- Document title: 杭州源界星图科技有限公司 ｜ 索克派 ｜ page title.
  Keep WordPress-generated product/search/archive/error titles and pagination;
  the static front page uses 首页. No duplicate site name or tagline.
- Site name option: 索克派 SockSpecs. Homepage eyebrow and About title updated
  using narrow replacements; no bulk rewrite of articles, products or evidence.
- Theme stylesheet version bumped for browser cache invalidation.

The company is the filing site name supplied by the operator. Aliyun's filing
troubleshooting guidance says to show the filed website name in the browser title
and upper page; this is not a claim that all sites universally need their full
company name first on every page. No filing application/registration was changed.
Reference: https://help.aliyun.com/zh/icp-filing/basic-icp-service/support/for-the-record-to-dismiss-the-faq

## Apply / rollback

Code: use tools/deploy/sync-code.py --apply (claw-host only).
Content: run tools/deploy/update-domestic-brand.php apply as the domestic WP admin.
It defaults to verification and guards DB name, URL and currency. Original affected
options/page fields are retained in sscn_brand_before_20261022.
A repeat run preserves later edited page content; it replaces only the exact old
homepage brand phrase and About title. Existing product titles, specifications,
prices, inventory, orders and visibility are not modified.

## Validation

composer check passed. Local rendered HTTP checks confirmed one title per page
for home, About, account, X31 product, search and 404; all had the company/brand
prefix and meaningful individual page title. Browser verified Chinese branding
and navigation. Server deployment and verification recorded below.

Server release: 20261022T013141699247Z. Previous branding release:
20261022T013012967916Z; pre-branding rollback: 20261021T232050579491Z.
Server content helper applied and verified. Browser confirmed live homepage and
article 166 title, header, About navigation and footer. Screenshot:
/tmp/sockspecs-cn-suokepai.png. Local repeat apply checked after narrowing the
homepage replacement to its exact paragraph to avoid duplicate brand prefixes.
Direct Python HTTPS checks encountered SSL EOF in this environment; the in-app
browser successfully loaded the same server pages with HTTPS. No TLS settings
were changed.

Follow-up: removed the redundant company line from the top-left header, removed
its unused CSS, and bumped the stylesheet version to 0.1.6. The earlier page-top
interpretation was overly conservative; header placement is not treated as a
universal requirement. Document title and footer are unchanged.
Follow-up validation: deployment checks passed; release 20261022T014142599204Z.
Verified rendered local and server homepages: company line absent from header,
brand/navigation present, company retained in footer and browser title.
Screenshot: /tmp/sockspecs-cn-clean-header.png.
