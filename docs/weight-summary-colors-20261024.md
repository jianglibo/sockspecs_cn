# Weight summary color grouping — 2026-10-24

A missing measurement on one color previously caused the common size-level net
weight to print every measured color. This produced an unnecessary list on
X109 / SS-S0001 L. Remove that trigger: equal available net weights are grouped
by size (and other non-color options), without a color list. Actual recorded
weight differences within a size still receive color labels to distinguish them.
Missing records remain missing in the underlying specifications and editor;
no measurements are inferred or copied to unmeasured variants.

Regression coverage retracts one color's weight, verifies the summary remains
size-only and the missing record is not filled. Existing tests retain separate
labels for genuinely different recorded weights. No product data is modified.

Shape-editor integration tests and deployment Composer checks passed. Deployed
release `20261024T123832787666Z`; anonymous HTTP and browser both confirmed
SS-S0001 net weights render as 52 g / M and 54 g / L without color lists.
