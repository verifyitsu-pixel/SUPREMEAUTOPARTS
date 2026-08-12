#!/usr/bin/env node
/** Respectful, resumable downloader for robots.txt-declared public sitemap feeds. */
import { mkdir, writeFile } from 'node:fs/promises';

const base = 'https://www.topgearautosport.com/';
const root = new URL('../', import.meta.url);
const out = new URL('work/', root);
const delay = Number(process.env.CRAWL_DELAY_MS || 1250);
const names = ['robots.txt', 'sitemaps.xml', 'sitemaps_products.xml', 'sitemaps_autos.xml', 'sitemaps_categories.xml', 'sitemaps_brands.xml'];
await mkdir(out, { recursive: true });
for (const name of names) {
  const response = await fetch(new URL(name, base), { headers: { 'user-agent': 'SupremeAutopartsAuthorizedAudit/1.0 (+https://github.com/verifyitsu-pixel/SUPREMEAUTOPARTS)' } });
  if (!response.ok) throw new Error(`${name}: HTTP ${response.status}`);
  await writeFile(new URL(name, out), Buffer.from(await response.arrayBuffer()));
  console.log(`${name}: ${response.status}`);
  await new Promise(resolve => setTimeout(resolve, delay));
}
