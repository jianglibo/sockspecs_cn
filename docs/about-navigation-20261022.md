# About navigation — 2026-10-22

Moved 关于索克派 from the domestic primary menu to the shared footer. The
homepage introduction link and About page remain published. Other menu entries
are unchanged.

- Theme footer links to the published `about` page by permalink.
- Fresh local seeds no longer include About in the primary menu.
- Guarded `tools/deploy/move-about-to-footer.php plan|apply|verify` changes only
  matching page-menu entries to draft. Original statuses are saved in the
  non-autoloaded option `sscn_about_navigation_before_20261022`; restore those
  statuses to undo the menu migration. The About page itself stays published.
- Applied and verified locally, then deployed with existing sync-code.py --apply.
- Code release: 20261022T232133646515Z. Previous: 20261022T111851575772Z.
- Production migration applied and verified. Existing deployment checks passed:
  PHP syntax, coding standards, bootstrap/isolation, provider mocks and JS suites.
- Store visibility preserved; international store untouched.
