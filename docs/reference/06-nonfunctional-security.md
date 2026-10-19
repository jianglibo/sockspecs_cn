# 06 · Nonfunctional requirements

## Security
- WordPress capability checks on every privileged path; nonces against CSRF on state changes; server-side authorization independent of UI.
- SQL via `$wpdb->prepare`, allowlisted sort/filter parameters; escape outputs with `esc_html`, `esc_attr`, `esc_url` as appropriate; sanitize on input and validate business types.
- File uploads through WordPress media controls, allowable MIME types, controlled evidence visibility. Don't expose private invoices/lab contracts.
- REST endpoint abuse controls: validation, bounded page size, transient caching and sensible request throttling via infrastructure when required.
- Respect WooCommerce customer privacy; do not store card data; use PCI-appropriate payment gateway provider.

## Performance
MVP evaluation workload: 30 products and up to 20 metrics per item; also run synthetic test with ≥1,000 SKUs for query-shape confidence. Target catalog/compare page usability on commodity mobile; set empirical LCP/query budgets based on staging data, not invented guarantees. Pagination and appropriate composite indexes required. No unconditional full-table scans for range filters. Defer JS/CSS to used pages.

## Reliability & observability
No fatal errors if WooCommerce is disabled: admin notice and graceful failure. Log structured plugin validation/migration errors without exposing private data. Run backup/restore smoke test; migrations idempotent. Revalidation on plugin/WC upgrades.

## Accessibility
Aim for WCAG 2.2 AA on custom UI: semantic compare tables, responsive fallback, keyboard-only filtering, understandable error text, focus visible, color not sole status indicator.

## Localization & privacy
All strings translatable; do not concatenate translated fragments. Currency and taxes via WooCommerce settings, units rendered consistently. Analytics opt-in/configurable, no hidden third-party scripts in plugin. Document data retention, evidence access rights and customer-data treatment.

## Documentation
Plugin installation/upgrade notes, schema dictionary, admin operations manual, measurement-method content policy, backup/restore, developer test commands, and explicit list of unresolved regulatory requirements for operating markets.
