# Catalog operations

## Refresh discovery data

Run `pnpm crawl` only with authorization. It uses one request at a time and a 1.25-second delay. It follows the public sitemaps declared by `robots.txt` and does not enter `/adm/`, `/lib/`, or `/plesk-stat/`. Run `pnpm build:data` afterward.

## Import safely

1. Run a dry run and check the count.
2. Import 250–500 draft products per batch.
3. Review failures and repeat the same batch safely; source IDs prevent duplicate products.
4. Assign prices, stock, descriptions, categories, and structured year/make/model fitment.
5. Download only authorized images. Optimize them and add useful alt text.
6. Preview and publish approved products.
7. Run the broken-link, image, checkout, and responsive checks on staging.

## Orders and payments

Use WooCommerce screens and CRUD APIs for all order work. Do not edit HPOS tables directly. Before live launch, exercise each gateway's successful payment, decline, cancellation, webhook, refund, and duplicate-callback behavior using provider sandbox credentials.
