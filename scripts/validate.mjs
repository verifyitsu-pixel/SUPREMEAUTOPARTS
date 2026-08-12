#!/usr/bin/env node
import { readFile, readdir, stat } from 'node:fs/promises';
import { join, relative, extname } from 'node:path';
import { Engine } from 'php-parser';

const root = new URL('../', import.meta.url);
const rootPath = decodeURIComponent(root.pathname.replace(/^\/(.:)/, '$1'));
const failures = [];
const engine = new Engine({ parser: { extractDoc: true, suppressErrors: false }, ast: { withPositions: true } });

async function walk(dir) {
  const items = [];
  for (const entry of await readdir(dir, { withFileTypes: true })) {
    if (['.git','node_modules','work','outputs'].includes(entry.name)) continue;
    const path = join(dir, entry.name);
    if (entry.isDirectory()) items.push(...await walk(path)); else items.push(path);
  }
  return items;
}

const files = await walk(rootPath);
for (const file of files.filter(file => extname(file) === '.php')) {
  try { engine.parseCode(await readFile(file, 'utf8'), relative(rootPath, file)); }
  catch (error) { failures.push(`PHP parse: ${relative(rootPath, file)}: ${error.message}`); }
}
for (const file of files.filter(file => extname(file) === '.json')) {
  try { JSON.parse(await readFile(file, 'utf8')); }
  catch (error) { failures.push(`JSON parse: ${relative(rootPath, file)}: ${error.message}`); }
}

const customerFiles = files.filter(file => !relative(rootPath, file).match(/^(data|scripts|reports|tests)[\\/]/));
for (const file of customerFiles.filter(file => ['.php','.css','.js','.svg','.json','.md'].includes(extname(file)))) {
  const source = await readFile(file, 'utf8');
  if (/topgearautosport/i.test(source)) failures.push(`Legacy branding in customer-facing file: ${relative(rootPath, file)}`);
}

const front = await readFile(new URL('wp-content/themes/supreme-autoparts/front-page.php', root), 'utf8');
for (const match of front.matchAll(/assets\/images\/['" ]?\.?'?\s*\.\s*['"]([^'"]+)/g)) void match;
const requiredAssets = ['logo.svg','logo-light.svg','hero-truck.svg','headlight.svg','taillight.svg','lightbar.svg','mirror.svg','step.svg','grille.svg'];
for (const asset of requiredAssets) {
  try { const info = await stat(new URL(`wp-content/themes/supreme-autoparts/assets/images/${asset}`, root)); if (!info.size) failures.push(`Empty asset: ${asset}`); }
  catch { failures.push(`Missing asset: ${asset}`); }
}

const css = await readFile(new URL('wp-content/themes/supreme-autoparts/assets/css/app.css', root), 'utf8');
if ((css.match(/{/g)||[]).length !== (css.match(/}/g)||[]).length) failures.push('CSS braces are unbalanced.');
for (const breakpoint of ['1000px','720px','430px']) if (!css.includes(`max-width:${breakpoint}`)) failures.push(`Missing responsive breakpoint ${breakpoint}.`);
if (!css.includes('prefers-reduced-motion')) failures.push('Missing reduced-motion accessibility handling.');

const dockerfile = await readFile(new URL('Dockerfile', root), 'utf8');
for (const expected of ['wordpress:6.9.1-php8.3-apache','woocommerce.','railway-entrypoint']) if (!dockerfile.includes(expected)) failures.push(`Dockerfile missing ${expected}.`);
const env = await readFile(new URL('.env.example', root), 'utf8');
if (/password=(?!replace|\$\{\{)/i.test(env)) failures.push('.env.example appears to contain a real password.');

if (failures.length) { console.error(failures.join('\n')); process.exit(1); }
console.log(`Validated ${files.length} files: PHP/JSON syntax, branding, assets, responsive CSS, Docker, and env safety passed.`);
