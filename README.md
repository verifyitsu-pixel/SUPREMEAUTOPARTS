# Supreme Autoparts

A production-oriented WordPress/WooCommerce implementation for Supreme Autoparts, designed for Railway and driven by a normalized inventory of the authorized public source architecture.

## What is included

- Custom responsive WooCommerce theme with product search, category/catalog pages, product pages, cart, checkout, My Account, orders, editorial pages, accessibility handling, schema, and mobile navigation.
- Companion plugin for centralized branding, year/make/model taxonomies, vehicle-filtered catalog queries, local garage UX, product fitment, REST search, PesaPal/TransactPay adapters, local-currency estimates, and WP-CLI imports.
- Public crawl inventories under `/data`: 17,529 products, 10,636 vehicle URLs, 280 category entries, 19 brands, 28,475 route records, assets, redirects, and vehicle hierarchy.
- Reproducible, rate-limited crawler and inventory builder under `/scripts`.
- Railway Docker deployment based on WordPress 6.9.1, PHP 8.3, Apache, and pinned WooCommerce 10.8.1.
- Static validation and tests for PHP/JSON syntax, branding, routes, data integrity, HPOS-compatible CRUD, gateway neutrality, assets, security config, and responsive CSS.

## Railway deployment

1. In Railway, deploy this GitHub repository as the web service. Railway detects the root `Dockerfile`.
2. Add a managed MySQL service to the same project.
3. In the web service, add the variables shown in `.env.example`, using Railway reference variables to the MySQL service. If the database service has a different display name, replace `MySQL` in the references.
4. Generate a public domain. `WP_HOME` and `WP_SITEURL` can reference `${{RAILWAY_PUBLIC_DOMAIN}}` or are inferred automatically by the entrypoint.
5. Add a Railway volume mounted at `/var/www/html/wp-content/uploads`. The database uses Railway-managed persistence and does not need a web-service volume.
6. Deploy. The configured health check is `/healthz.php`.
7. The idempotent container bootstrap installs WordPress when needed, activates WooCommerce/theme/plugin, creates editable pages, and keeps the configured administrator identity synchronized.
8. Set PesaPal and/or TransactPay credentials in Railway. A gateway is invisible until all required credentials exist; start in sandbox mode.
9. Configure shipping, taxes, and transactional email before accepting orders.

Railway builds from GitHub on each push to the connected branch. The container maps Railway's `MYSQLHOST`, `MYSQLPORT`, `MYSQLUSER`, `MYSQLPASSWORD`, and `MYSQLDATABASE` automatically when explicit `WORDPRESS_DB_*` values are not present.

## Catalog import

The import defaults to draft products and does not download images unless explicitly requested:

```sh
wp supreme catalog import /opt/supreme/data/products.csv --limit=500 --status=draft --dry-run
wp supreme catalog import /opt/supreme/data/products.csv --limit=500 --status=draft
wp supreme catalog import /opt/supreme/data/products.csv --offset=500 --limit=500 --status=draft --include-assets
```

Imports are streaming and idempotent by legacy public source ID. Review product descriptions, prices, stock, fitment, asset rights, and availability before publishing. The public source inventory contains no customer/order data.

## Development and validation

Requires Node 20+ and pnpm 11.

```sh
pnpm install
pnpm build
pnpm preview
```

`pnpm build` regenerates inventories, checks JavaScript syntax, parses every PHP and JSON file, runs repository policy checks, and executes the automated tests.

## Production operations

- WooCommerce is the system of record for products, inventory, cart sessions, customers, orders, coupons, taxes, shipping, and payments.
- The companion plugin declares HPOS and Cart/Checkout block compatibility and uses WooCommerce product CRUD.
- PesaPal uses API 3.0 token/order/status endpoints; TransactPay uses encrypted hosted-order creation. Test authorization, callback/webhook, failure, cancellation, and refund flows with merchant sandbox credentials before enabling live mode.
- Customer-facing local prices are cached informational FX estimates selected from WooCommerce geolocation. The cart, order ledger, and provider requests always remain USD.
- Configure an external transactional email provider; the base WordPress Docker image does not provide a mail transport.
- Schedule Railway MySQL backups and volume backups. Rebuild regularly for WordPress/PHP security updates and update the pinned WooCommerce release through a tested pull request.

See [`docs/architecture.md`](docs/architecture.md), [`docs/catalog-operations.md`](docs/catalog-operations.md), and [`reports/final-audit.md`](reports/final-audit.md).
