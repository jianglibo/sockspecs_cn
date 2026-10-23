# Domestic catalog filter simplification — 2026-10-22

User requested fewer visible controls and automatic refresh after changing a filter.

- Main controls: category, thickness, terry distribution, sorting.
- Remaining controls are in native More filters details. Existing advanced query
  conditions open that section and show a count so active constraints are visible.
- Select changes submit the existing native GET form immediately. Text/number
  change commits on blur; Enter retains native submission. No per-keystroke reload.
- Enhanced forms hide Apply filters and show an auto-update hint. Without JS or
  requestSubmit support, the original button and GET flow remain usable.
- Keep comparison selection and page_id hidden fields. Changing filters resets
  pagination because the form does not carry pc_page. Native validation is kept;
  existing server-side range and enum validation is unchanged.
- Guard duplicate submissions; pageshow restores the form after history navigation.
  Mobile filter panels stay open when constraints are active.
- Wear_Profile::filter_controls accepts an optional subset; default callers still
  render all three controls. No product/category/specification changes.

Local browser verified all four primary controls, collapsed advanced controls,
no Apply button with JS, and category selection navigating from 11 to 4 matches.
JavaScript regression checks cover unsupported enhancement, input validity,
duplicate navigation, mobile active state and history restoration. Included in
composer check. Deployment uses tools/deploy/sync-code.py only.

Deployment checks passed; release 20261022T022138751695Z, rollback
20261022T014142599204Z. Server browser verified 8 products initially, then 3
following category selection without pressing Apply. Local Enter submission of
an advanced numeric condition retained/opened More filters with its active count.
Screenshot: /tmp/sockspecs-cn-simple-filters.png. No database or commerce edits.
