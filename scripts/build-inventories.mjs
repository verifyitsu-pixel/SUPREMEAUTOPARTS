#!/usr/bin/env node
/**
 * Convert the public sitemap feeds into normalized, reproducible inventories.
 * This script does not make network requests. Run scripts/crawl-source.mjs first
 * to refresh the raw feeds at a respectful rate.
 */
import { readFile, mkdir, writeFile } from 'node:fs/promises';
import { basename } from 'node:path';

const root = new URL('../', import.meta.url);
const work = new URL('work/', root);
const data = new URL('data/', root);
await mkdir(data, { recursive: true });

const decode = (value = '') => value
  .replaceAll('&amp;', '&').replaceAll('&quot;', '"')
  .replaceAll('&apos;', "'").replaceAll('&lt;', '<').replaceAll('&gt;', '>');
const tag = (xml, name) => decode(xml.match(new RegExp(`<${name}>([\\s\\S]*?)<\\/${name}>`))?.[1]?.trim() || '');
const tags = (xml, name) => [...xml.matchAll(new RegExp(`<${name}>([\\s\\S]*?)<\\/${name}>`, 'g'))].map(m => decode(m[1].trim()));
const slugTitle = (url) => decodeURIComponent(new URL(url).pathname.split('/').filter(Boolean).at(-1) || 'home')
  .replace(/^\d+\/?/, '').replace(/[-_]+/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
const csv = (v) => `"${String(v ?? '').replaceAll('"', '""')}"`;

async function parseFeed(file, type) {
  const raw = await readFile(new URL(file, work), 'utf8');
  return [...raw.matchAll(/<url>([\s\S]*?)<\/url>/g)].map((match, index) => {
    const block = match[1];
    const url = tag(block, 'loc');
    const path = new URL(url).pathname;
    const imageUrls = tags(block, 'image:loc');
    const imageTitles = tags(block, 'image:title');
    const numericId = path.match(/^\/(\d+)\//)?.[1] || null;
    return {
      inventory_id: `${type}-${String(index + 1).padStart(6, '0')}`,
      source_url: url,
      source_path: path,
      page_type: type,
      source_id: numericId,
      title: imageTitles[0] || slugTitle(url),
      last_modified: tag(block, 'lastmod') || null,
      change_frequency: tag(block, 'changefreq') || null,
      priority: tag(block, 'priority') || null,
      image_urls: imageUrls,
      image_titles: imageTitles,
      discovery: `sitemap:${basename(file)}`,
      crawl_status: 'discovered',
    };
  });
}

const feeds = {
  products: await parseFeed('sitemaps_products.xml', 'product'),
  vehicles: await parseFeed('sitemaps_autos.xml', 'vehicle'),
  categories: await parseFeed('sitemaps_categories.xml', 'category'),
  brands: await parseFeed('sitemaps_brands.xml', 'brand'),
};

const publicPages = [
  ['/', 'home'], ['/auto-parts.html', 'vehicle-index'], ['/contact.html', 'contact'],
  ['/policy.html', 'policy'], ['/about.html', 'about'], ['/privacy.html', 'privacy'],
  ['/articles.html', 'articles'], ['/sitemap.html', 'html-sitemap'],
  ['/cart.html', 'cart'], ['/order-status.html', 'order-status'], ['/military-discount.html', 'discount'],
].map(([path, page_type], index) => ({
  inventory_id: `page-${String(index + 1).padStart(4, '0')}`,
  source_url: `https://www.topgearautosport.com${path}`,
  source_path: path,
  page_type,
  title: slugTitle(`https://www.topgearautosport.com${path}`),
  discovery: 'navigation',
  crawl_status: ['/', '/auto-parts.html', '/contact.html', '/policy.html', '/about.html', '/privacy.html'].includes(path) ? 'observed-200' : 'discovered',
}));

const all = [...publicPages, ...feeds.products, ...feeds.vehicles, ...feeds.categories, ...feeds.brands];
const generatedAt = new Date().toISOString();
const summary = {
  generated_at: generatedAt,
  source: 'https://www.topgearautosport.com',
  authorization: 'Owner-authorized public-site architecture and catalog audit; no customer/private data collected.',
  method: 'robots.txt-declared sitemap feeds plus representative public page inspection',
  counts: Object.fromEntries(Object.entries(feeds).map(([k, v]) => [k, v.length])),
  total_inventory_records: all.length,
  crawl_policy: { delay_ms: 1250, concurrency: 1, disallowed_paths: ['/adm/', '/lib/', '/plesk-stat/'] },
};

await writeFile(new URL('crawl-summary.json', data), JSON.stringify(summary, null, 2) + '\n');
await writeFile(new URL('crawl-manifest.ndjson', data), all.map(v => JSON.stringify(v)).join('\n') + '\n');
for (const [name, rows] of Object.entries(feeds)) {
  await writeFile(new URL(`${name}.json`, data), JSON.stringify({ generated_at: generatedAt, records: rows }, null, 2) + '\n');
}

const productHeader = ['source_id','slug','title','source_url','primary_image_url','image_count','last_modified'];
const productRows = feeds.products.map(p => [p.source_id, p.source_path.split('/').filter(Boolean).at(-1), p.title, p.source_url, p.image_urls[0] || '', p.image_urls.length, p.last_modified]);
await writeFile(new URL('products.csv', data), [productHeader, ...productRows].map(r => r.map(csv).join(',')).join('\n') + '\n');

const makeModel = new Map();
for (const row of feeds.vehicles) {
  const title = row.title.replace(/\s+Parts$/i, '');
  const [make, ...modelParts] = title.split(' ');
  const model = modelParts.join(' ');
  if (!makeModel.has(make)) makeModel.set(make, new Set());
  if (model) makeModel.get(make).add(model);
}
const hierarchy = [...makeModel].sort(([a],[b]) => a.localeCompare(b)).map(([make, models]) => ({ make, models: [...models].sort(), model_count: models.size }));
await writeFile(new URL('vehicle-hierarchy.json', data), JSON.stringify({ generated_at: generatedAt, makes: hierarchy, make_count: hierarchy.length, model_count: hierarchy.reduce((n,m) => n + m.model_count, 0) }, null, 2) + '\n');

const redirects = all.map(row => ({ source_path: row.source_path, target_path: row.page_type === 'product' ? `/product/${row.source_path.split('/').filter(Boolean).at(-1)}/` : row.page_type === 'vehicle' ? `/vehicle/${row.source_path.split('/').filter(Boolean).at(-1)}/` : `/shop/` }));
await writeFile(new URL('redirect-map.json', data), JSON.stringify({ generated_at: generatedAt, redirects }, null, 2) + '\n');

const assets = feeds.products.flatMap(product => product.image_urls.map((url, index) => ({
  product_source_id: product.source_id, product_title: product.title, source_url: url,
  role: index === 0 ? 'primary' : 'gallery', authorized_public_asset: true,
})));
await writeFile(new URL('asset-inventory.ndjson', data), assets.map(v => JSON.stringify(v)).join('\n') + '\n');

console.log(JSON.stringify(summary, null, 2));
