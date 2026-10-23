# Domestic sample observation — 2026-10-21

Visible terry placement is an observation, not a laboratory test. Added the
explicit platform_observed source (本站样品观察) for thickness_grade,
cushion_location and footwear_fit only. Composition, weight and performance
claims cannot use this source. Existing measured/lab/report rules remain intact.

The reviewer needs the actual observation date and a brief public observation
note. Color/size scope and sample count are optional; blank values remain blank
rather than assuming a sample count or variation scope. No external source link, attachment,
method, version, batch or conditions JSON is required. Blank evidence title and
organization are identified as 样品观察记录 and 本站 during saving; no dates,
counts or observations are invented. Existing author/reviewer audit is retained.

Existing drafts must explicitly switch their source, save, review and publish.
No actual claim is automatically reclassified or published. Observations retain
scope and do not inherit from parent products to every variation. Product evidence
and Compare identify observations honestly; filters use the canonical projection.

Local regression: 24 observation workflow checks, 39 wearing-profile checks,
6 metric editor checks and 13 JavaScript editor checks passed. Observation tests
use disposable local-only fixtures, exercise normal save/review/publish/retract,
and restore visibility. No production fixture or email is created.

Deploy using the existing domestic sync-code.py script after its Composer checks.
No international changes, dictionary migration, product price/stock/visibility
changes or public DNS changes are part of this fix.

Deployment completed: /srv/sockspecs-cn/releases/20261021T023624146149Z.
Rollback release: /srv/sockspecs-cn/releases/20261021T022611380154Z.
Existing sync script Composer checks passed. Six deployed implementation and
translation hashes match local sources. Read-only server validation accepted an
observation without laboratory fields and confirmed the Chinese source label.
Domestic live-preview visibility remains no (coming-soon disabled). No live
admin browser submission was performed and no production claim was modified.

## Optional observation details follow-up

After operator feedback, removed mandatory size_scope and sample_count for
platform_observed only. Admin guidance and Compare wording reflect optional
scope. The local save/review/publish/retract regression now uses both fields
blank, verifies no defaults are invented, and still accepts supplied details.
23 observation checks passed. No actual product claims were changed.

Follow-up deployed: /srv/sockspecs-cn/releases/20261021T025725191113Z;
rollback: /srv/sockspecs-cn/releases/20261021T023624146149Z. All deployment
Composer checks passed. Four deployed hashes match; read-only production
validation accepts an observation with blank scope and null sample count.
