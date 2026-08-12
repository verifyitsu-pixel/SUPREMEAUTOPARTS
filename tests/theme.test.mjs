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
