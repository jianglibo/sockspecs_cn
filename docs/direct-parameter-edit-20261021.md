# Direct parameter editing — 2026-10-21

Clicking an existing draft, reviewed or published item now shows a populated form.
The primary publisher action is 保存并发布; 保存草稿 remains secondary. No manual
retract/fork/review step is required. Saved preview, audit and individual transition
controls remain available in a collapsed advanced section. Current status is
visible beside the editor. Retracted historical rows remain immutable.

Repository::edit composes existing fork/save/review/publish operations in one
outer transaction and scope lock. Same-scope nested operations join it rather
than committing separately. On any failure, changes to claims, evidence and audit
roll back together; the old public record stays live. Published editing forks a
revision; reviewed editing returns to draft internally. Save draft never retracts
the current public value. Publication validates the current ID/version captured
when opening the form. Stale forms, mismatched identity and undisclosed new-entry
replacement attempts fail. Error redisplay preserves unsaved content and original
revision tokens. Publisher permission and POST nonce protection remain required.

Tests: 20 local direct-edit checks, including successful new/published/reviewed
edits, populated forms, draft-only behavior, canonical projection, rollback of
fork/evidence/audit on invalid publication, stale revisions, immutable scope,
unauthorized access and error redisplay. Existing 21 claim revision checks and
6 metric editor checks passed. These tests use local disposable fixtures only.

Deploy via existing domestic sync-code.py. No automatic production record writes,
price/stock/site visibility changes or international changes.

Deployed /srv/sockspecs-cn/releases/20261021T035925921963Z; rollback
20261021T034203112361Z. Required Composer checks passed. Four shipped file hashes
match local sources. Read-only server rendering confirmed populated direct-edit
forms for X109's published terry #353 and thickness #354, including 保存并发布
and collapsed advanced details. No production claims were mutated by deployment.
