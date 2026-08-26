// M15 — Platform Settings: browser-driven verification.
// Run: node tests/browser/m15-settings.mjs   (needs `php artisan serve` on :8000)
//
// Covers:
//   TC-SET-05  super_admin sees "Pengaturan Sistem" in the menu & page loads
//   TC-SET-10  settings page renders grouped tabs + integration status panel
//   TC-SET-11  saving a value (office_lat) persists and is reflected on reload
//   TC-SET-12  secret field (fonnte token) is never rendered back to the browser
//   TC-SET-13  saving a masked secret (unchanged) does NOT wipe the stored token
//   TC-SET-01  manager (non-super-admin) cannot reach /admin/settings (403/redirect)
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

// ---------------------------------------------------------- super_admin flow
{
  const ctx = await browser.newContext();
  const page = await ctx.newPage();
  page.setDefaultTimeout(60000);
  await login(page, 'siti@demo.test');

  // TC-SET-05 — menu link present
  await page.goto(`${BASE}/admin/dashboard`);
  const hasLink = await page.evaluate(() =>
    [...document.querySelectorAll('a')].some(a => /Pengaturan Sistem/i.test(a.innerText)));
  hasLink ? pass('TC-SET-05', 'menu "Pengaturan Sistem" tampil untuk super_admin')
          : fail('TC-SET-05', 'menu "Pengaturan Sistem" tidak ditemukan');

  // TC-SET-10 — page loads with tabs + status panel
  const resp = await page.goto(`${BASE}/admin/settings`);
  const okStatus = resp.status();
  const tabs = await page.evaluate(() =>
    [...document.querySelectorAll('.nav-tabs .nav-link')].map(t => t.innerText.trim()));
  const hasStatus = await page.evaluate(() =>
    ['status-acc', 'status-whatsapp', 'status-storage'].every(id => !!document.getElementById(id)));
  (okStatus === 200 && tabs.length >= 5 && hasStatus)
    ? pass('TC-SET-10', `halaman termuat (HTTP ${okStatus}), ${tabs.length} tab: ${tabs.join(', ')}; panel status OK`)
    : fail('TC-SET-10', `HTTP ${okStatus}, tab=${JSON.stringify(tabs)}, status=${hasStatus}`);

  // TC-SET-12 — secret field must not expose the real token value.
  // First seed a token via the form, then confirm it comes back masked.
  // Fields live in grouped tabs, so switch to each tab like a real user.
  await page.goto(`${BASE}/admin/settings`);
  await page.click('#tab-notifikasi');
  await page.fill('#fld-waha_api_key', 'TESTKEY-XYZ-123');
  await page.click('#tab-lokasi');
  await page.fill('#fld-office_lat', '-6.211111');
  await Promise.all([page.waitForNavigation(), page.click('#saveSettings')]);

  // TC-SET-11 — office_lat persisted and shown on reload
  await page.goto(`${BASE}/admin/settings`);
  await page.click('#tab-lokasi');
  const latVal = await page.inputValue('#fld-office_lat');
  latVal === '-6.211111'
    ? pass('TC-SET-11', `office_lat tersimpan & tampil ulang (${latVal})`)
    : fail('TC-SET-11', `office_lat = "${latVal}", diharapkan -6.211111`);

  await page.click('#tab-notifikasi');
  const tokenVal = await page.inputValue('#fld-waha_api_key');
  (tokenVal === '********' || tokenVal === '')
    ? pass('TC-SET-12', `secret WAHA tidak dibocorkan ke browser (nilai field = "${tokenVal}")`)
    : fail('TC-SET-12', `secret bocor ke browser: "${tokenVal}"`);

  // TC-SET-13 — re-save with masked token must NOT wipe the stored secret.
  await page.click('#tab-lokasi');
  await page.fill('#fld-office_lat', '-6.222222');   // change something else
  await Promise.all([page.waitForNavigation(), page.click('#saveSettings')]);
  await ctx.close();
}

// verify token survived, via a fresh check page load (status badge stays OK-capable)
{
  const ctx = await browser.newContext();
  const page = await ctx.newPage();
  await login(page, 'siti@demo.test');
  await page.goto(`${BASE}/admin/settings`);
  await page.click('#tab-lokasi');
  const latVal = await page.inputValue('#fld-office_lat');
  await page.click('#tab-notifikasi');
  const tokenVal = await page.inputValue('#fld-waha_api_key');
  (latVal === '-6.222222' && tokenVal === '********')
    ? pass('TC-SET-13', 'setelah simpan ulang: office_lat berubah & token lama tetap ada (masked)')
    : fail('TC-SET-13', `lat="${latVal}" token="${tokenVal}" — token mungkin terhapus`);
  await ctx.close();
}

// ------------------------------------------------------- non-super-admin block
{
  const ctx = await browser.newContext();
  const page = await ctx.newPage();
  await login(page, 'budi@demo.test'); // manager
  const resp = await page.goto(`${BASE}/admin/settings`);
  const status = resp.status();
  const url = page.url();
  const blocked = status === 403 || !url.includes('/admin/settings');
  blocked
    ? pass('TC-SET-01', `manager diblokir dari /admin/settings (HTTP ${status}, url=${url.replace(BASE, '')})`)
    : fail('TC-SET-01', `manager BISA membuka settings (HTTP ${status})`);

  const hasLink = await page.evaluate(() =>
    [...document.querySelectorAll('a')].some(a => /Pengaturan Sistem/i.test(a.innerText)));
  hasLink ? fail('TC-SET-01b', 'menu "Pengaturan Sistem" bocor ke manager')
          : pass('TC-SET-01b', 'menu "Pengaturan Sistem" tidak tampil untuk manager');
  await ctx.close();
}

await browser.close();

const failed = results.filter(r => r[0] === 'FAIL');
console.log(`\n${'='.repeat(70)}\n${results.length - failed.length} PASS / ${failed.length} FAIL\n${'='.repeat(70)}`);
failed.forEach(([, id, note]) => console.log(`  FAIL ${id} — ${note}`));
process.exit(failed.length ? 1 : 0);
