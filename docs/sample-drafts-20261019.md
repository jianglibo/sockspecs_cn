# Six sample-product drafts — 2026-10-19

Operator authorized adding six newly ordered candidates to both stores as drafts.
Each repository owns its localized manifest and independent data import. This is
not ongoing cross-store synchronization and does not change existing products.

| Model | Known variants | Size |
|---|---:|---|
| 323 | 5 colors | EU 39–44 |
| J02/J03 | 4 J02 + 5 J03 colors | Unknown |
| X31 | 6 colors | Unknown |
| X65 | 4 colors | EU 39–44 |
| X72 | 5 colors | Unknown |
| X59 | 4 colors | Unknown |

English international content and Chinese domestic content use SS-001 model codes.
J02/J03 remains one parent with an explicit model attribute. Only documented
combinations are created: no Cartesian expansion between model/color or invented
sizes. Native variation status is publish under a draft parent, WooCommerce’s
normal active-variation representation; anonymous purchasing/visibility are tested.
Parents and children have no prices or inventory quantities. Native shipping weight
is blank. Draft claims remain private/unapproved. No existing product is retired.

Sources: supplier workbook rows preserved privately with SHA-256, plus the
operator-pasted 323 and X65 listing details. Five original workbook photos were
extracted, reviewed and converted to WebP. Originals remain in ignored
.local/sample-drafts-originals. 323 has no confirmed image and remains blank.
The X31 source photo retains a small promotional corner mark, pending final media
review before publication. No AI-generated product photos are used.

41 draft structured claims per store retain declared values rather than inferred
performance. All measurements remain at product/group scope because variation-size
coverage is unknown. J02/J03’s 51 g is a shared reference, not two independently
confirmed weights. Workbook weights exclude packaging per operator confirmation.
323’s 24 g remains a private review note because packaging basis is unresolved.
X65 listing content “30” remains a note, not an asserted complete composition.
X65 latest listing colors replace the workbook green option. No lab report is
copied from a different model. Material details/ambiguities are also visible in the
native sourcing/readiness suggestion box for review.

## Operation

Run tools/deploy/import-sample-drafts.php via the site’s administrator WP-CLI with
plan, apply or verify. It reads sample-drafts-20261019/products.json alongside it.
The helper guards site identity, domestic DB, administrator capability, SKU and
supplier-code ownership and image hashes. A non-autoload operation option records
the manifest fingerprint, prior settings/catalog fingerprint and completed IDs.
A MySQL advisory lock serializes runs. Repeated apply verifies without overwriting
editor changes. Completed products checkpoint; an interrupted partial product is
retained as a draft and requires review rather than being silently overwritten.
Repository owns claim transactions; the entire import is not falsely treated as
one nested transaction. Failed imports can leave unreferenced staged media.

Take a private database backup first. Test locally; use each existing sync-code.py
workflow. International sync is code-only: transfer the reviewed helper and its
assets separately outside the web root, as other existing catalog operations do.
Domestic sync already includes tools/deploy assets. Run plan/apply/verify on each
server independently. No public DNS, policy, checkout or store visibility change.

For rollback after later sales/editor activity, trash only the recorded six draft
parents (and review their attached variants/media); do not restore a whole store
DB over new orders. Historical claims/evidence are retained for audit.

## Validation

Local imports and repeat apply verified draft/non-purchasable boundaries, known
variant identities, empty commerce data, source image integrity, private draft
claims and unchanged existing catalog/settings. Server results are recorded after
execution. Local pre-import SQL: .local/backups/sample-drafts-20261019/before.sql.

## Executed release

Both local sites and both servers passed 224 import assertions each. Repeat apply
was verified locally without creating duplicate products, variations, images or
claims. Both Composer checks passed. All six drafts on each server returned 404
through the anonymous WooCommerce Store API. No existing commerce/settings changed.

International code sync reported already synchronized (79 files). Its reviewed
content helper/assets were transferred separately outside the public web root to
/srv/sockspecs/imports/sample-drafts-20261019. Backup:
/var/backups/sockspecs/snapshot-20261019T120818Z-LcbYzZ.
International draft IDs: 323=2613, J02/J03=2619, X31=2630, X65=2638, X72=2644, X59=2651.

Domestic sync release: /srv/sockspecs-cn/releases/20261019T120835815838Z;
prior release /srv/sockspecs-cn/releases/20261019T023612266465Z.
Domestic backup: /var/backups/sockspecs-cn/sample-drafts-20261019/before.sql.
Domestic draft IDs: 323=90, J02/J03=96, X31=107, X65=115, X72=121, X59=128.
Both stores retained the preexisting coming-soon=no setting. All six new products
remain drafts; this change does not publish or open sales for any of them.

## Category correction

The first draft import omitted category assignment, causing WooCommerce to use
Uncategorized. The importer now assigns categories and verifies them. Existing
drafts are corrected by categorize-sample-drafts.php (plan/apply/verify), using
sample-categories.php. The repair stores prior category IDs and fingerprints of
all other product fields in pc_sample_categories_20261019, refuses unexpected
edits, and verifies that publication, descriptions, prices, stock and images stay
unchanged. Repeat apply verifies without overwriting subsequent edits.

- 323: Everyday, Running, Cushioned.
- J02/J03: Running.
- X31: Everyday, Cushioned.
- X65: Everyday, Merino Wool (supplier-declared material, not certification).
- X72: Everyday, Running.
- X59: Running.

Existing international categories are reused. Domestic categories use Chinese
labels under the existing Socks category; its five earlier products are untouched.
The import manifest and original import fingerprint remain unchanged. For a
reviewed rollback, restore only category IDs from the repair option, provided
current IDs still match the stored after-state; keep unrelated products untouched.

Category repair executed on both local sites and both servers. Category-only
fingerprints verified all six drafts retained their other product data. Composer
checks passed in both repos. Updated importer verification passed 230 checks on
both local sites and the domestic server. On the international server all six
product checks passed, but the historical full-catalog fingerprint differed from
the original import snapshot; that broader legacy check failed. No unrelated
product was overwritten or baseline reset. The scoped category verifier passed.
Domestic release: /srv/sockspecs-cn/releases/20261019T235557140953Z.
International plugin sync remained already synchronized; reviewed helpers were
transferred to the existing private import directory.
