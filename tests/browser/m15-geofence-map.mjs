// M07/M15 — Global geofence map picker on the Settings "Lokasi" tab.
// Run: node tests/browser/m15-geofence-map.mjs   (needs `php artisan serve` on :8000)
//
// Covers:
//   TC-GEO-01  Lokasi tab renders the Leaflet map container + address search box
//   TC-GEO-02  clicking the map fills #fld-office_lat / #fld-office_lng with real coords
//   TC-GEO-03  changing radius keeps working (circle sync, no JS error)
//   TC-GEO-04  clicked coordinates persist after Simpan + reload
import { chromium } from 'playwright';

const BASE = 'http://127.0.0.1:8000';
const results = [];
const pass = (id, note) => { results.push(['PASS', id, note]); console.log(`PASS ${id} — ${note}`); };
const fail = (id, note) => { results.push(['FAIL', id, note]); console.log(`FAIL ${id} — ${note}`); };

async function login(page, email) {
  await page.goto(`${BASE}/admin/login`);
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', 'password');
  await Promise.all([page.waitForNavigation(), page.click('button[type="submit"]')]);
}

const browser = await chromium.launch();
const ctx = await browser.newContext();
const page = await ctx.newPage();
page.setDefaultTimeout(60000);
const jsErrors = [];
page.on('pageerror', e => jsErrors.push(e.message));

await login(page, 'siti@demo.test');
await page.goto(`${BASE}/admin/settings`);
await page.click('#tab-lokasi');

// TC-GEO-01 — map container + search box present and map initialised (Leaflet tiles).
await page.waitForTimeout(1500); // let Leaflet boot + invalidateSize
const mapReady = await page.evaluate(() => {
  const el = document.getElementById('office-map');
  const search = document.getElementById('office-map-search');
  const tiles = document.querySelectorAll('#office-map .leaflet-tile').length;
  return { hasEl: !!el, ready: el && el.dataset.ready === '1', hasSearch: !!search, tiles };
});
(mapReady.hasEl && mapReady.ready && mapReady.hasSearch && mapReady.tiles > 0)
  ? pass('TC-GEO-01', `peta Leaflet termuat di tab Lokasi (${mapReady.tiles} tile), kotak cari ada`)
  : fail('TC-GEO-01', `map=${JSON.stringify(mapReady)}`);

// TC-GEO-02 — clicking the map fills lat/lng with real coordinates.
const before = {
  lat: await page.inputValue('#fld-office_lat'),
  lng: await page.inputValue('#fld-office_lng'),
};
await page.locator('#office-map').click({ position: { x: 200, y: 170 } });
await page.waitForTimeout(300);
const after = {
  lat: await page.inputValue('#fld-office_lat'),
  lng: await page.inputValue('#fld-office_lng'),
};
const filled = after.lat && after.lng
  && !isNaN(parseFloat(after.lat)) && !isNaN(parseFloat(after.lng))
  && (after.lat !== before.lat || after.lng !== before.lng);
filled
  ? pass('TC-GEO-02', `klik peta mengisi koordinat: lat=${after.lat}, lng=${after.lng}`)
  : fail('TC-GEO-02', `koordinat tidak terisi. before=${JSON.stringify(before)} after=${JSON.stringify(after)}`);

// TC-GEO-03 — changing radius updates the circle without error.
await page.fill('#fld-office_radius', '250');
await page.dispatchEvent('#fld-office_radius', 'input');
await page.waitForTimeout(200);
const radiusOk = await page.evaluate(() => document.querySelectorAll('#office-map .leaflet-interactive').length > 0);
radiusOk
  ? pass('TC-GEO-03', 'ubah radius tersinkron ke lingkaran peta tanpa error')
  : fail('TC-GEO-03', 'lingkaran radius tidak ditemukan setelah ubah radius');

// TC-GEO-04 — clicked coords persist after Simpan + reload.
const savedLat = after.lat, savedLng = after.lng;
await Promise.all([page.waitForNavigation(), page.click('#saveSettings')]);
await page.goto(`${BASE}/admin/settings`);
await page.click('#tab-lokasi');
const reloadedLat = await page.inputValue('#fld-office_lat');
const reloadedLng = await page.inputValue('#fld-office_lng');
// Compare to ~5 decimal places (stored value round-trips as string).
const close = (a, b) => Math.abs(parseFloat(a) - parseFloat(b)) < 0.0001;
(close(reloadedLat, savedLat) && close(reloadedLng, savedLng))
  ? pass('TC-GEO-04', `koordinat hasil klik tersimpan & tampil ulang (${reloadedLat}, ${reloadedLng})`)
  : fail('TC-GEO-04', `setelah reload lat=${reloadedLat} lng=${reloadedLng}, diharapkan ~${savedLat}, ${savedLng}`);

if (jsErrors.length) fail('TC-GEO-JS', `JS errors: ${jsErrors.join(' | ')}`);
else pass('TC-GEO-JS', 'tidak ada JS error di halaman');

await ctx.close();
await browser.close();

const failed = results.filter(r => r[0] === 'FAIL');
console.log(`\n${'='.repeat(70)}\n${results.length - failed.length} PASS / ${failed.length} FAIL\n${'='.repeat(70)}`);
failed.forEach(([, id, note]) => console.log(`  FAIL ${id} — ${note}`));
process.exit(failed.length ? 1 : 0);
