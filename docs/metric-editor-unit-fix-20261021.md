# Domestic metric editor unit fix — 2026-10-21

The new draft starts with cotton/%; selecting a unitless enum left the old percent
unit in place. The server correctly rejected that mismatch. The native metric
options now carry escaped dictionary units, and an independent, dependency-free
admin script synchronizes units on explicit metric changes. Fixed units are
readonly when matched; unitless fields clear on load/change/history restoration.
Multi-unit yarn counts clear on metric change without inventing Ne/Nm/etc.
Existing mismatched numeric units on validation redisplay remain editable and are
not silently relabeled or converted. Values, notes and evidence are untouched.
Server unit validation is unchanged. Without JS the existing manual field and
strict validation remain available. The asset loads only on the authorized
parameter editor, including its standalone validation-error page.

Tests: node tests/metric-editor-js.js covers 13 transitions/restoration cases;
bash tools/wp.sh eval-file tests/metric-editor.php verifies six read-only form,
validation and asset cases. No product or claim save is performed by tests.
Deploy only the domestic repo through tools/deploy/sync-code.py --apply. No
international code, product data or visibility change is included.

Executed: all 13 JS and six read-only WordPress tests passed, along with the
existing Composer/deployment checks. Browser local harness using the actual
shipped JS confirmed cotton/% → terry/blank while retaining partial-terry.
The live admin page requires login in the agent browser; no user editing session
was refreshed and no production form was submitted. Live login page recovered
normally after deployment reload. Deployed file hashes match local sources.
Domestic release: /srv/sockspecs-cn/releases/20261021T022611380154Z
Rollback release: /srv/sockspecs-cn/releases/20261021T020341755616Z
Local harness screenshot: /tmp/sockspecs-cn-unit-fix.png. Temporary harness removed.
