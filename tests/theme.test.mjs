import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';

const root = new URL('../', import.meta.url);
const read = path => readFile(new URL(path, root), 'utf8');

test('theme declares WooCommerce and accessibility support', async () => {
  const functions = await read('wp-content/themes/supreme-autoparts/functions.php');
  assert.match(functions, /add_theme_support\( 'woocommerce'/);
  assert.match(functions, /title-tag/);
  const header = await read('wp-content/themes/supreme-autoparts/header.php');
  assert.match(header, /skip-link/);
  assert.match(header, /aria-controls="primary-nav"/);
  assert.match(header, /role="search"/);
});

test('core plugin uses WooCommerce CRUD and declares HPOS compatibility', async () => {
  const plugin = await read('wp-content/plugins/supreme-autoparts-core/supreme-autoparts-core.php');
  const importer = await read('wp-content/plugins/supreme-autoparts-core/includes/class-sa-import-command.php');
  assert.match(plugin, /custom_order_tables/);
  assert.match(importer, /WC_Product_Simple/);
  assert.doesNotMatch(importer, /wp_insert_post\([^)]*shop_order/s);
});

test('checkout remains gateway-neutral', async () => {
  const providers = await read('wp-content/plugins/supreme-autoparts-core/includes/class-sa-checkout-providers.php');
  assert.match(providers, /WC_Payment_Gateway/);
  assert.doesNotMatch(providers, /stripe|paypal|authorize\.net/i);
});

test('checkout requires and records store-policy acceptance', async () => {
  const compliance = await read('wp-content/plugins/supreme-autoparts-core/includes/class-sa-compliance.php');
  const importer = await read('wp-content/plugins/supreme-autoparts-core/includes/class-sa-import-command.php');
  assert.match(compliance, /woocommerce_checkout_process/);
  assert.match(compliance, /_sa_policy_acceptance_utc/);
  assert.match(compliance, /Privacy & Cookie Policy/);
  assert.match(compliance, /Payment & Chargeback Policy/);
  assert.match(importer, /payment-chargebacks/);
  assert.match(importer, /privacy-cookies/);
});

test('customer-care routes include WhatsApp, contact form, and business address', async () => {
  const compliance = await read('wp-content/plugins/supreme-autoparts-core/includes/class-sa-compliance.php');
  const brand = await read('wp-content/plugins/supreme-autoparts-core/includes/class-sa-brand.php');
  const footer = await read('wp-content/themes/supreme-autoparts/footer.php');
  assert.match(compliance, /sa_contact_form/);
  assert.match(brand, /wa\.me/);
  assert.match(brand, /Midax Plaza/);
  assert.match(footer, /sa_brand\( 'address' \)/);
});

test('production bootstrap makes the WooCommerce storefront public', async () => {
  const bootstrap = await read('deploy/supreme-bootstrap.sh');
  assert.match(bootstrap, /option update woocommerce_coming_soon 'no'/);
  assert.match(bootstrap, /option update fresh_site '0'/);
});

test('store setup configures shipping and avoids duplicate policy checkboxes', async () => {
  const importer = await read('wp-content/plugins/supreme-autoparts-core/includes/class-sa-import-command.php');
  assert.match(importer, /WC_Shipping_Zone/);
  assert.match(importer, /free_shipping/);
  assert.match(importer, /woocommerce_terms_page_id', 0/);
});
