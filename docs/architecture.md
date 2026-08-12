# Architecture

## Runtime

The web service is the official Apache WordPress image extended with the Supreme theme, companion plugin, must-use environment hardening, and a pinned official WooCommerce package. Railway provides TLS termination and the public domain. A Railway managed MySQL service stores WordPress and WooCommerce data; a Railway volume persists media uploads.

## Commerce ownership

WooCommerce owns catalog product records, pricing, stock, coupons, cart/session state, checkout, taxes, shipping, customers, accounts, and orders. Payment behavior is deliberately not hard-coded. Any enabled `WC_Payment_Gateway` is exposed through the small checkout-provider adapter. No card data is stored by custom code.

## Fitment

Products can be assigned hierarchical `sa_year`, `sa_make`, and `sa_model` terms and a human-readable fitment summary. Catalog queries map the public `vehicle_year`, `vehicle_make`, and `vehicle_model` parameters to those taxonomies. The browser garage stores at most five selected vehicles in local storage; it contains no customer identity and can be cleared with site data.

## Import boundary

Raw source sitemaps stay in ignored `/work`. Normalized public inventories live in `/data`. The WP-CLI importer streams `products.csv`, keys idempotency on `_sa_source_id`, creates draft products by default, and only downloads images when `--include-assets` is provided. Operators must review assets, descriptions, fitment, pricing, and inventory before publishing.

## Security

Secrets are Railway variables, not files. WordPress file editing, XML-RPC, and application passwords are disabled. Apache adds baseline response headers. The health check validates both the web process and database after installation. Production should add a WAF/CDN, transactional email, anti-spam, rate limits, least-privilege admin accounts, and tested backups.
