# Chinese sock guides — 2026-10-21

Published three native Chinese WordPress posts in the 选袜指南 category and a compact index page, linked from the existing primary menu. Articles link back to the index; the index links to the domestic catalog. No product, price, stock, payment, visibility or international-store changes.

Production pages: index 163; price 164; wool content 165; thickness/terry 166. Local pages: index 251; posts 252–254.

Sources live in tools/deploy/content/{sock-price,wool-content,sock-thickness}.html. The guarded, repeatable tools/deploy/publish-sock-guides.php apply/verify helper uses native WordPress APIs, refuses unmanaged slug collisions, stores managed IDs and snapshots prior state/menu under sscn_sock_guides_before_20261021. Existing navigation items are preserved. Future article updates must modify these sources and run this helper intentionally; ordinary code sync does not rewrite posts.

Editorial decisions: 20 g × 90% = 18 g and 120 g × 16% = 19.2 g are explicitly hypothetical, not product claims or typical market weights. Low-percentage socks need >5.625× the weight to contain more wool in this example. Wool amount is not total cost. No accusation against competitors. Weight must exclude packaging; local sampling cannot represent whole-sock composition. 323/X59 observations are attributed to received samples, not laboratory thickness/shock-absorption tests.

Validation: PHP lint; local apply, repeat apply (same four IDs), verify; local browser index and price guide; existing deployment script's composer check (lint, coding standards, bootstrap/domestic isolation and commerce checks); production apply with content/category/menu verification; production browser rendered all four pages, with domestic links, and followed index → price article to confirm the calculation table. Supplemental shell HTTPS requests failed TLS and were not counted as successful checks. Screenshot: /tmp/sockspecs-cn-price-guide.png.

Code release: /srv/sockspecs-cn/releases/20261021T120409219633Z; prior release: /srv/sockspecs-cn/releases/20261021T091831790298Z. To undo publication, remove only this index's menu item and move these four managed objects to drafts; do not replace the whole menu or delete unrelated content. Code rollback alone does not unpublish WordPress posts.

## Thickness guide rewrite

Replaced the conversational sample comparison with a general consumer guide. Removed 323/X59 from both article and index excerpt. New structure: distinguish thickness from terry structure; explain no/partial/full-sole terry; fit socks to shoe volume; consider warmth, moisture and activity; compare net weight only in context. Added supporting links to REI's sock selection guide and Darn Tough's terry construction explanation; no quantitative cushioning or medical claims. Existing article ID and URL retained.

Validation: local apply + verify and rendered article-body assertions passed. An initial assertion against the entire page found unrelated numeric markup; the corrected check scopes to the article body. Existing deployment checks passed. Release /srv/sockspecs-cn/releases/20261021T122158557166Z (prior 20261021T120409219633Z).

## Self-contained terry explanation

At the operator's request, removed the Darn Tough and REI links from the public article. Research references remain only in this internal history. Expanded original Chinese explanatory copy: distinguish ordinary knit loops from raised terry loops, explain visual identification and thickness under compression, and compare no-terry, partial-terry, full-sole and full-terry coverage in an accessible table. No third-party images or branded diagrams used. The article does not claim measured shock attenuation. Scope remains the existing thickness article; publication helper also verifies the other managed guides without changing their content.

Validation for this revision: local apply/verify passed; rendered main-content link checks passed for all three guides (no outbound article links); four-row distribution table and new explanation present. Deployment checks passed; release /srv/sockspecs-cn/releases/20261021T122750229013Z, prior 20261021T122158557166Z.
