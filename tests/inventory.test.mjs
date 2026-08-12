import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';

const root = new URL('../', import.meta.url);
const json = async path => JSON.parse(await readFile(new URL(path, root), 'utf8'));

test('crawl inventory is complete and internally consistent', async () => {
  const summary = await json('data/crawl-summary.json');
  assert.equal(summary.counts.products, 17529);
  assert.equal(summary.counts.vehicles, 10636);
  assert.ok(summary.total_inventory_records > 28000);
  const products = (await json('data/products.json')).records;
  assert.equal(products.length, summary.counts.products);
  assert.ok(products.every(product => product.source_url.startsWith('https://www.topgearautosport.com/')));
  assert.ok(products.every(product => product.page_type === 'product'));
});

test('vehicle hierarchy has unique makes and models', async () => {
  const hierarchy = await json('data/vehicle-hierarchy.json');
  assert.ok(hierarchy.make_count >= 40);
  assert.equal(new Set(hierarchy.makes.map(item => item.make)).size, hierarchy.makes.length);
  for (const make of hierarchy.makes) assert.equal(new Set(make.models).size, make.models.length);
});

test('route inventory covers commerce and operational surfaces', async () => {
  const paths = (await json('data/route-inventory.json')).routes.map(route => route.path);
  for (const expected of ['/', '/shop/', '/product/{slug}/', '/cart/', '/checkout/', '/my-account/', '/healthz.php']) assert.ok(paths.includes(expected), expected);
});

test('redirect map covers every discovered public record', async () => {
  const summary = await json('data/crawl-summary.json');
  const redirectMap = await json('data/redirect-map.json');
  assert.equal(redirectMap.redirects.length, summary.total_inventory_records);
});
