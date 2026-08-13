# Supreme Autoparts — Production Audit

Audit updated: 2026-08-13  
Repository: `verifyitsu-pixel/SUPREMEAUTOPARTS`  
Production: `https://www.supremeautoparts.co.ke/`  
Deployment: Railway Docker service with managed MySQL and GitHub auto-deploy

## Result

The public WooCommerce storefront is live. The repository contains a custom responsive Supreme Autoparts theme and core plugin, normalized source inventories, crawler/importer tooling, deterministic market-price estimates, vehicle fitment, local-currency display, cart/account/order support, PesaPal and TransactPay abstractions, policy enforcement, tests, and Railway deployment automation.

No private source pages, customer records, credentials, or payment data were collected. No AI product images are used.

## Authorized source inventory

The respectful crawl used the public `robots.txt` sitemap declarations, concurrency 1, and a 1.25-second delay. Restricted paths were excluded.

| Surface | Discovered records |
|---|---:|
| Products | 17,529 |
| Vehicle/catalog URLs | 10,636 |
| Categories | 280 |
| Brands | 19 |
| Total inventory records | 28,475 |
| Real source photo mappings | 39,058 |

Machine-readable outputs are under `/data`, including product CSV/JSON, vehicle hierarchy, categories, brands, crawl manifest, assets, routes, redirects, verified prices, and generated estimated prices.

## Production verification

- Railway deployment for commit `8335ea0` reported success.
- Homepage, shop, shop-by-vehicle, cart, checkout, account, contact, about, shipping/returns, privacy/cookies, chargebacks, terms, search API, vehicle API, and health endpoint returned HTTP 200.
- WooCommerce "Coming soon" protection was disabled in production.
- Browser flow passed: public listing → add product → persistent cart → shipping calculation → checkout.
- Cart displayed a USD subtotal, $35 standard shipping, and qualifying free shipping.
- Checkout displayed exactly one required policy acceptance checkbox linking Terms, Privacy/Cookies, Shipping/Returns, and Payment/Chargebacks. Acceptance is versioned and recorded on the order.
- Footer displayed Midax Plaza, Off Kangundo Rd, Nairobi, Kenya; +254 714 498 451; support@supremeautoparts.co.ke; WhatsApp; and contact-form routes.
- Browser console produced no warnings or errors during final checkout inspection.
- Catalog batch import is idempotent and resumes from its saved offset after deployment. At the last audit request, 3,407 products were live and the background importer was continuing toward all 17,529.

## Payments

PesaPal and TransactPay gateway implementations are present and fail closed. They only become available when their real Railway merchant variables exist. At final browser QA neither provider had merchant credentials configured, so checkout correctly showed no available payment method. A live payment redirect, callback, refund, and settlement cannot be validated without credentials issued by those providers; no credentials were invented or exposed.

## Product images

Every discovered product has at least one authorized real source-image mapping. The source image host currently answers Railway requests with an Incapsula HTML challenge (`text/html`) instead of image bytes. The importer validates `Content-Type: image/*` before sideloading and refuses the challenge response, preventing HTML from being stored as a JPEG. Source URLs remain attached as retryable metadata. No AI or synthetic product photographs are used.

## Validation

- JavaScript syntax/type checks passed.
- PHP/JSON/config/branding/assets/responsive/Docker/env validation passed for 68 files.
- Automated tests: 11 passed, 0 failed.
- Git whitespace/diff checks passed.
- GitHub push and Railway deployment succeeded.
- Live route/status checks passed.
- Browser product, cart, shipping, checkout, policy, footer, and console QA passed.

## Operational notes

- WooCommerce is the system of record for products, carts, customers, orders, coupons, taxes, shipping, and gateway settings.
- Checkout and settlement currency is USD. Local-currency values are estimates only.
- Administrator and provider secrets belong only in Railway variables and must never be committed, printed, or returned by a public endpoint.
- Transactional email requires a production SMTP/email delivery provider before relying on the contact form or order emails.
- Policy text is operational copy, not jurisdiction-specific legal advice; local counsel should approve it before high-volume trading.
