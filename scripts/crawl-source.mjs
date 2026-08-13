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
  const body = Buffer.from(await response.arrayBuffer());
  const contentType = response.headers.get('content-type') || '';
  const text = body.toString('utf8', 0, Math.min(body.length, 4096)).trim().toLowerCase();
  const isSitemap = name.endsWith('.xml');
  const looksLikeHtmlChallenge = text.startsWith('<html') || text.startsWith('<!doctype html') || text.includes('incapsula_resource') || text.includes('access denied');
  if (isSitemap && (looksLikeHtmlChallenge || (!contentType.includes('xml') && !text.startsWith('<?xml') && !text.startsWith('<urlset') && !text.startsWith('<sitemapindex')))) {
    throw new Error(`${name}: HTTP ${response.status} returned non-XML content (${contentType || 'unknown content type'}); refusing to overwrite crawl inputs`);
  }
  await writeFile(new URL(name, out), body);
  console.log(`${name}: ${response.status}`);
  await new Promise(resolve => setTimeout(resolve, delay));
}
