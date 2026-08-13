#!/usr/bin/env node
/** Build reproducible USD market estimates for every normalized catalog row. */
import { readFile, writeFile } from 'node:fs/promises';

const root = new URL('../', import.meta.url);
const input = new URL('data/products.csv', root);
const verifiedFile = new URL('data/verified-prices.json', root);
const output = new URL('data/estimated-prices.json', root);

const parseCsv = (text) => {
  const rows = []; let row = [], field = '', quoted = false;
  for (let i = 0; i < text.length; i++) {
    const char = text[i];
    if (char === '"' && quoted && text[i + 1] === '"') { field += '"'; i++; }
    else if (char === '"') quoted = !quoted;
    else if (char === ',' && !quoted) { row.push(field); field = ''; }
    else if ((char === '\n' || char === '\r') && !quoted) {
      if (char === '\r' && text[i + 1] === '\n') i++;
      row.push(field); field = '';
      if (row.some(Boolean)) rows.push(row); row = [];
    } else field += char;
  }
  if (field || row.length) { row.push(field); rows.push(row); }
  const headers = rows.shift();
  return rows.map(values => Object.fromEntries(headers.map((header, index) => [header, values[index] || ''])));
};

const families = [
  ['truck-bed-rack', /truck bed rack|ladder rack/i, 699.99, 'medium'],
  ['towing-mirrors-folding', /(?:power folding|power fold).*(?:tow|towing) mirror|(?:tow|towing) mirror.*(?:power folding|power fold)/i, 599.99, 'high'],
  ['towing-mirrors', /tow(?:ing)? mirrors?/i, 349.99, 'high'],
  ['running-boards', /running boards?|nerf bars?|side steps?|step bars?/i, 329.99, 'high'],
  ['lighting-combo', /headlights?.*(?:tail lights?|grille)|(?:tail lights?|grille).*headlights?/i, 499.99, 'medium'],
  ['headlights-led-projector', /(?:led|drl).*(?:projector )?headlights?|headlights?.*(?:led|drl)/i, 349.99, 'high'],
  ['headlights-projector', /projector headlights?/i, 299.99, 'high'],
  ['headlights-replacement-single', /(?:left|right|driver|passenger) side replacement headlight/i, 109.99, 'high'],
  ['headlights', /headlights?|headlamp/i, 189.99, 'high'],
  ['tail-lights-led', /led tail lights?|tube led tail lights?/i, 269.99, 'high'],
  ['tail-lights', /tail lights?|altezza lights?/i, 179.99, 'high'],
  ['fog-lights', /fog lights?/i, 99.99, 'high'],
  ['light-bar', /light bars?|off-road lights?/i, 159.99, 'medium'],
  ['led-bulbs', /led (?:headlight )?bulbs?|conversion kit/i, 129.99, 'high'],
  ['grille-guard', /grille guard|brush guard|bull bars?/i, 399.99, 'high'],
  ['grille-premium', /(?:denali|mesh|billet|vertical).*(?:grille|grill)|(?:grille|grill).*(?:denali|mesh|billet|vertical)/i, 249.99, 'high'],
  ['grille', /grilles?|grills?/i, 199.99, 'high'],
  ['coilovers', /coilovers?/i, 99.99, 'high'],
  ['lowering-springs', /lowering springs?/i, 89.99, 'high'],
  ['cold-air-intake', /cold air intake/i, 169.99, 'high'],
  ['short-ram-intake', /short ram intake/i, 129.99, 'high'],
  ['intake', /intake system|air intake/i, 149.99, 'medium'],
  ['headers', /headers?/i, 159.99, 'high'],
  ['radiator', /radiators?/i, 139.99, 'high'],
  ['catalytic-converter', /catalytic converter/i, 179.99, 'medium'],
  ['exhaust', /exhaust|muffler|test pipe/i, 199.99, 'medium'],
  ['side-mirrors', /side mirrors?|power mirrors?|manual mirrors?/i, 149.99, 'medium'],
  ['fender-flares', /fender flares?/i, 219.99, 'medium'],
  ['fender', /fenders?/i, 299.99, 'medium'],
  ['bumper', /bumpers?/i, 299.99, 'medium'],
  ['spoiler', /spoilers?|wings?/i, 159.99, 'medium'],
  ['body-lip', /front lip|rear lip|body lip/i, 109.99, 'medium'],
  ['hood', /hoods?/i, 449.99, 'medium'],
  ['window-visors', /window visors?|deflectors?/i, 69.99, 'high'],
  ['door-handles', /door handles?/i, 59.99, 'medium'],
  ['gauge', /gauge cluster|gauge face/i, 59.99, 'high'],
  ['strut-bar', /strut bars?/i, 59.99, 'high'],
  ['camber-kit', /camber kits?/i, 129.99, 'high'],
  ['antenna', /antennas?/i, 59.99, 'medium'],
  ['general-accessory', /.*/i, 149.99, 'low'],
];

const estimate = (title) => {
  const [family, , base, confidence] = families.find(([, pattern]) => pattern.test(title));
  let price = base;
  if (/carbon fiber/i.test(title)) price += 100;
  if (/power heated/i.test(title) && !/tow(?:ing)? mirrors?/i.test(title)) price += 50;
  if (/complete kit|combo|and .* set/i.test(title) && !/lighting-combo/.test(family)) price += 50;
  price = Math.max(29.99, Math.min(899.99, Math.round(price) - 0.01));
  return { price: price.toFixed(2), family, confidence };
};

const products = parseCsv(await readFile(input, 'utf8'));
const verified = JSON.parse(await readFile(verifiedFile, 'utf8')).records || {};
const records = {};
for (const product of products) {
  if (verified[product.source_id]?.price) {
    records[product.source_id] = { price: String(verified[product.source_id].price), basis: 'source-verified', confidence: 'verified', source_page: verified[product.source_id].source_page };
  } else {
    const value = estimate(product.title);
    records[product.source_id] = { price: value.price, basis: `market-estimate:${value.family}`, confidence: value.confidence };
  }
}
const result = {
  currency: 'USD', estimated_at: new Date().toISOString(), methodology: 'Keyword product-family medians calibrated against current public TopGearAutoSport and aftermarket-retailer prices; exact verified records override estimates.', record_count: Object.keys(records).length, records,
};
await writeFile(output, JSON.stringify(result) + '\n');
console.log(JSON.stringify({ record_count: result.record_count, verified: Object.values(records).filter(row => row.basis === 'source-verified').length, estimated: Object.values(records).filter(row => row.basis !== 'source-verified').length }, null, 2));
