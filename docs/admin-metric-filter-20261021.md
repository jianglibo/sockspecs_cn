# Admin metric-name filter — 2026-10-21

Domestic 商品参数 now has a translated 指标名称 selector with 全部指标 default,
using registered metric labels. Can filter by metric alone or combine with the
existing product picker. Filtering starts on page 1. Previous/next links and claim
links preserve product, metric and list page. Existing new/reload link clears the
filters. No public catalog or stored claims are changed.

Repository listing accepts an optional registered metric key, validates it and
uses prepared SQL. Existing staff authorization and 21-row pagination remain.
Local read-only regression passed 20 checks: combined filters, metric-only,
empty results, invalid inputs, Chinese selector, selected value, pagination,
claim navigation and unauthenticated rejection. Changed PHP passes WPCS.
Deploy via existing domestic sync-code.py script.

Deployed /srv/sockspecs-cn/releases/20261021T034203112361Z (rollback
20261021T033452294045Z). Required Composer checks passed. Four deployed file
hashes match local source. Read-only live validation confirms X109 + terry returns
only its two historical/current records and the Chinese dropdown retains selection.
No interactive admin submission or product-data changes were performed.
