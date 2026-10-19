# 03 · Technical architecture

## Baseline
WordPress + WooCommerce + `parameter-commerce` plugin + existing MySQL/MariaDB database. Nginx/PHP-FPM may be used but is not assumed by plugin code. WooCommerce APIs remain the integration boundary for product and order flows.

```text
Browser (responsive theme + blocks/shortcodes)
  ├─ WooCommerce product/cart/checkout (commerce source of truth)
  └─ Parameter Commerce UI (usage landing, filtered catalog, compare, evidence)
         ├─ Metric definition + validation service
         ├─ Profile/evidence repository
         └─ Public, read-only catalog query layer
                  ↳ WooCommerce product API + custom parameter tables
```

## Storage recommendation
For launch, store categorical facets using WooCommerce global attributes/taxonomies where appropriate (size, usage, cushion category). Use **plugin-managed custom tables** for typed numeric metrics and provenance, because range filters plus evidence records don't map cleanly to generic postmeta.

Proposed tables (all prefixed with `$wpdb->prefix`):
- `pc_metric_definitions`: metric_key (unique), data_type, unit, label_i18n_key, is_filterable, applicability, schema_version, created_at.
- `pc_claims`: id PK, wc_product_id, wc_variation_id nullable, metric_key, value_num nullable, value_text nullable, value_enum nullable, unit, source_type, method_code, method_version, conditions_json, sample_count, sample_batch, measured_at, status, evidence_id nullable, published_at, created_at, updated_at; indexes on `(metric_key,status,value_num)`, `(wc_product_id,wc_variation_id,status)`.
- `pc_evidence`: id PK, public_title, attachment_id nullable, public_url nullable, visibility, source_org, note, created_at, updated_at.
- `pc_claim_audit`: id PK, claim_id, actor_user_id, action, before_json, after_json, happened_at.

Use `$wpdb->dbDelta` or carefully versioned migrations; test schema on supported DB versions. JSON columns may be represented as LONGTEXT for portability with strict JSON validation. Enforce unique **active published** claim per (SKU scope, metric) via application transaction/validation if partial indexes unavailable. Plan for concurrent edits; do not silently overwrite published records.

## Integration surfaces
- Admin: WooCommerce product edit integration or dedicated 'Parameter Specs' submenu. Staff capabilities: `manage_woocommerce` for editing, separately gate evidence publication to a custom capability `publish_pc_claims` assigned explicitly to administrators/store managers at activation.
- Public: shortcode/block `pc_catalog`, `pc_compare`, `pc_evidence` or route-based rendering. WC product IDs must be checked for public/published visibility.
- Queries: validated, allowlisted parameter keys, prepared SQL and bounded results. Range filters use numeric indexes; paginate. Product availability/visibility resolved using WC APIs.
- API: optional read-only `wp-json/parameter-commerce/v1/...` endpoints for filtered catalog and comparison; built-in WP REST permissions and query limiting. No public write routes.
- Templates: respect theme rendering and WooCommerce accessibility. Enqueue scripts/styles only where needed.

## Price, stock and variation handling
Do not copy WC prices or stock into custom tables. Fetch via product/variation APIs and apply WC catalog visibility and tax display settings. For variation-specific measurements, select a variation or show 'varies by size' rather than silently showing the parent value.

## Lifecycle
Activation: check required WP/WC functionality, create/update schema, register capabilities. Deactivation: disable hooks, **do not remove data**. Uninstall: opt-in deliberate data removal documented separately. Add schema version migration tests and preserve backward compatibility for saved claims.

## Deployment
Staging first, backups before schema migrations, HTTPS, limited-admin access, normal WP update strategy. Use WordPress cron for noncritical background cleanup only; no dependence on a bespoke process for storefront rendering. Configure WooCommerce shipping/payments through maintained suitable plugins after selecting geography.

## M3 additions

The catalog shortcode reads WooCommerce products through its APIs and joins only
the plugin-owned claim/evidence tables for approved measurement filters. Supplier
records use a nonpublic WordPress post type; protected product metadata stores
the supplier relationship and review-only description suggestions. The four
measurement tables remain unchanged. See [M3 architecture and operating limits](14-m3-catalog-and-sourcing.md).

## M4 addition

The `[pc_compare]` page reuses public exact-scope projections and native WooCommerce
prices/availability for at most four selections. Selection state travels in GET
URLs; no new table, option, cookie or public write endpoint. Both product and
comparison tables share escaped evidence details. See [M4](15-m4-comparison.md).
