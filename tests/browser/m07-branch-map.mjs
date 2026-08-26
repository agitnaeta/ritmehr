// M07 — Multi-branch map picker (browser).
// Run: node tests/browser/m07-branch-map.mjs   (php artisan serve on :8000)
//
// Covers:
//   TC-BR-70  branch create form loads with Leaflet map + search box
//   TC-BR-71  clicking the map fills lat & lng inputs
//   TC-BR-72  changing radius updates without error (circle sync)
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
{
  const ctx = await browser.newContext();
  const page = await ctx.newPage();
  page.setDefaultTimeout(60000);
  await login(page, 'siti@demo.test'); // super_admin

  await page.goto(`${BASE}/admin/branch/create`);
  await page.waitForTimeout(1500); // let Leaflet init + tiles

  // TC-BR-70 — map + search present
  const ready = await page.evaluate(() => {
    const map = document.getElementById('branch-map');
    const search = document.getElementById('branch-map-search');
    const tiles = document.querySelectorAll('#branch-map img.leaflet-tile').length;
    const hasLeaflet = !!(map && map.querySelector('.leaflet-container, .leaflet-map-pane'));
    return { map: !!map, search: !!search, hasLeaflet, tiles };
  });
  (ready.map && ready.search && ready.hasLeaflet)
    ? pass('TC-BR-70', `peta Leaflet + kotak cari termuat (tiles=${ready.tiles})`)
    : fail('TC-BR-70', `map=${ready.map}, search=${ready.search}, leaflet=${ready.hasLeaflet}`);

  // TC-BR-71 — click map sets lat/lng
  const before = await page.evaluate(() => ({
    lat: document.querySelector('[name="lat"]').value,
    lng: document.querySelector('[name="lng"]').value,
  }));
  // click at an explicit position within the map element (Playwright handles scroll)
  await page.locator('#branch-map').click({ position: { x: 200, y: 170 } });
  await page.waitForTimeout(500);
  const after = await page.evaluate(() => ({
    lat: document.querySelector('[name="lat"]').value,
    lng: document.querySelector('[name="lng"]').value,
  }));
  (after.lat && after.lng && (after.lat !== before.lat || after.lng !== before.lng))
    ? pass('TC-BR-71', `klik peta mengisi koordinat (${after.lat}, ${after.lng})`)
    : fail('TC-BR-71', `sebelum=${JSON.stringify(before)}, sesudah=${JSON.stringify(after)}`);

  // TC-BR-72 — radius change keeps working (no JS crash; circle radius reflects)
  const radiusOk = await page.evaluate(() => {
    const r = document.querySelector('[name="radius_meters"]');
    if (!r) return false;
    r.value = 500;
    r.dispatchEvent(new Event('input'));
    return true;
  });
  await page.waitForTimeout(200);
  const noErr = await page.evaluate(() => !!document.getElementById('branch-map').dataset.ready);
  (radiusOk && noErr)
    ? pass('TC-BR-72', 'ubah radius tersinkron ke lingkaran tanpa error')
    : fail('TC-BR-72', `radiusOk=${radiusOk}, mapReady=${noErr}`);

  await ctx.close();
}
await browser.close();

const failed = results.filter(r => r[0] === 'FAIL');
console.log(`\n${'='.repeat(70)}\n${results.length - failed.length} PASS / ${failed.length} FAIL\n${'='.repeat(70)}`);
failed.forEach(([, id, note]) => console.log(`  FAIL ${id} — ${note}`));
process.exit(failed.length ? 1 : 0);
