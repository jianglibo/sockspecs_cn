# 02 · Product parameters, taxonomy and evidence

## Structure
**WooCommerce product/variation** = purchase unit; authoritative SKU, pricing, stock and availability. **Parameter profile** = versioned technical description associated with the applicable product or variation. **Evidence** = documented source of a claim. Measurements may vary by size. Resolve an exact product or variation scope; missing variation records never inherit parent or sibling values. Product-level records do not establish all-size applicability.

Usage taxonomy: `everyday`, `running`, `hiking`, `cold-weather` (admin-editable). Height: no-show, ankle, crew, knee-high. Construction: toe seam (flat/linked/standard/unknown), cushioning (none/light/medium/heavy), knit construction (declared text + optional controlled vocabulary). Don't equate 'seamless' marketing phrasing with truly seam-free construction.

## Initial metric dictionary
| Key | Unit / type | Source typically | Interpretation |
|---|---|---|---|
| cotton_pct, wool_pct, nylon_pct, polyester_pct, elastane_pct, other_pct | 0–100 decimal % | supplier declaration or lab | composition, sum should be approximately 100% with documented tolerance |
| pair_weight_g | decimal g | measured | specify size, conditioned state and number of pairs |
| foot_length_mm, leg_height_mm | decimal mm | measured | measurement protocol and specimen size required |
| fabric_thickness_mm | decimal mm | measured | specify measurement zone/load and procedure |
| needle_count | integer | supplier declaration | not an intrinsic quality score |
| toe_seam_type | enum | supplier/inspection | construction category |
| cushion_type | enum | supplier/inspection | construction category |
| abrasion_result | numeric + unit | tested | meaningless without method, condition and endpoint |
| wash_dimensional_change_pct | signed decimal % | tested | identify measurement axis, cycles, washing/drying method |
| air_permeability | value + explicit unit | tested | compare only if test method and conditioning match |

Material compositions are *declared or tested*, never inferred from imagery. Don't claim higher cotton percentage is always better. Do not compare abrasion results across differing methods or test conditions.

## M3 dictionary extension

M3 adds `merino_wool_pct`, `heel_reinforcement`, `sock_height_type`,
`cuff_circumference_mm`, `cuff_height_mm`, `pilling_result`,
`elastic_recovery_pct` and `moisture_management_result`. Merino is a subset of
total wool, not an additional fiber in the composition sum. Existing records are
not reinterpreted. The dictionary now has 24 entries; see
[M3 units, provenance rules and compatibility](14-m3-catalog-and-sourcing.md).
No testing standard or measurement value is seeded.

## Claim/evidence required fields
`metric_key`, `value` (typed), `unit`, `scope` (product/variation/size), `source_type` (`supplier_declared`, `platform_measured`, `third_party_lab`), `method_code` (optional for declarations, mandatory for tested performance), `method_version`, `test_conditions`, `sample_count`, `sample_batch`, `measured_at`, `verified_by` (staff ID), `verification_status` (`draft`, `reviewed`, `published`, `retracted`), `evidence_attachment_id` or URL, `note`, `updated_at`.

Attachments must be validated for media type/access; private lab reports may remain staff-only, while a redacted public excerpt can support consumer view. **Never expose internal file URLs or confidential supplier documents automatically.**

## Rules
- Missing = `null`; the M2 specification table uses `Not yet measured / 尚未测量`. Existing staff previews use `Not tested / 未检测`. Missing is never 0.
- Only published claims enter public filters and comparisons. A supplier declaration is labeled as such, not 'independently verified'.
- If 2 products use incompatible tested methods, display the values with an incompatibility badge; do not sort them together by that metric or declare a winner.
- Evidence versions are immutable snapshots in an audit trail; newer evidence may supersede earlier records with retained history.
- Fabric mix validation accepts configurable tolerance and an explicit 'other/unknown' fraction, rather than silently normalizing to 100%.
- All monetary calculations use WooCommerce currency-aware APIs. Products with variations show correct selected-variation attributes and prices.

## Extensibility
Use a metric-definition registry (`key`, label, unit, type, validation, category applicability, filterability, comparison policy) rather than hardcoding every new category into templates. MVP requires sock metrics only; do **not** build a fully generic marketplace schema prematurely.
