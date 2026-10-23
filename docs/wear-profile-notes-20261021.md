# Domestic wearing summary notes — 2026-10-21

Product detail summaries now show the published public note under each wearing
value, including exact variation rows. Reads only the existing public projection;
private evidence notes and URLs remain excluded. Escapes text and preserves line
breaks; blank notes have no output. Compact catalog cards stay concise.

Removed the fixed thickness/shoe explanation. Thickness gets its explanation only
when a thickness row is displayed; shoe guidance only when a footwear row is
displayed. A terry-only panel contains its value, optional scope and public note.
No new product facts or publication state changes.

Local checks: 25 observation workflow checks and 42 wearing-profile checks passed,
including parent/child notes, compact cards and explanation visibility. Deployment
uses the existing domestic sync script; international site is outside this change.

Deployed /srv/sockspecs-cn/releases/20261021T033452294045Z; rollback release
20261021T032050124835Z. Required Composer checks passed. Refreshed the live X109
browser page and verified 局部毛圈 followed by 前脚掌及后跟有毛圈，足弓无毛圈,
with no unrelated thickness or shoe explanation. No product data was modified.
