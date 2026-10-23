# Exact-scope parameter revisions — 2026-10-21

Uniqueness is (wc_product_id, wc_variation_id, metric_key), matching the existing
unique published_slot. Multiple draft/history records are valid; only one value
is currently published for a scope. Parent and child records remain separate.

Saving another entry when a published claim exists automatically pins its ID and
revision in supersedes_id/supersedes_revision. Saving does not change the live
value. Existing linked drafts never silently rebase to newer records.

The editor shows current/new values and explains that updating a WooCommerce
product does not publish parameters. The action is labelled 发布并替换当前值.
For legacy unlinked reviewed drafts, including X109 #353, the form sends the
exact displayed replacement ID and revision. Publication verifies the active
slot and expected revisions inside the existing scope transaction, then replaces
the old claim and records both audit events atomically. Stale/wrong-scope targets
fail without altering either record. Existing capability, nonce, validation and
confirmation boundaries remain intact. No schema migration is needed.

No production claims are automatically reclassified, reviewed or published.
X109's existing reviewed observation can be published through the updated editor
without re-entering its data. The old statement remains live until that action.

Executed local checks: 21 exact-scope revision checks (including legacy migration,
wrong target, stale revision, no silent rebasing, audit history, unique live slot,
independent variation/metric and editor before/after controls); 23 observation
checks; 39 wearing-profile checks; WPCS for the changed shipped PHP files.
Fixture tests are restricted to the local domestic database. Never run on server.

Deploy only with tools/deploy/sync-code.py --apply. International code/data,
prices, stock, site visibility and DNS are outside this change.

Deployment completed: /srv/sockspecs-cn/releases/20261021T032050124835Z;
rollback: /srv/sockspecs-cn/releases/20261021T025725191113Z. Required Composer
checks passed. Four shipped file hashes match local source. A read-only render
of the live X109 #353 editor confirms translated before/after values, replacement
button, and pinned target #7 revision 3. Observation remains reviewed; no live
record has been published by this deployment. No interactive browser submission.
