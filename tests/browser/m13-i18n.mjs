// M13 — Multi-language switcher (browser).
// Run: node tests/browser/m13-i18n.mjs   (php artisan serve on :8000)
//
// Covers:
//   TC-I18N-60  language switcher tampil di topbar
//   TC-I18N-61  default menu Bahasa Indonesia (Akuntansi, Gajian)
//   TC-I18N-62  switch ke EN → menu berubah (Accounting, Payroll)
//   TC-I18N-63  preferensi bahasa persist setelah pindah halaman
//   TC-I18N-64  balik ke ID → menu kembali Bahasa Indonesia
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
const menuText = (page) => page.evaluate(() => document.body.innerText);

const browser = await chromium.launch();
{
  const ctx = await browser.newContext();
  const page = await ctx.newPage();
  page.setDefaultTimeout(60000);
  await login(page, 'siti@demo.test');
  await page.goto(`${BASE}/admin/dashboard`);
  await page.waitForTimeout(500);

  // TC-I18N-60 — switcher present
  const hasSwitcher = await page.evaluate(() =>
    [...document.querySelectorAll('a')].some(a => /\/locale\/en$/.test(a.getAttribute('href') || '')));
  hasSwitcher ? pass('TC-I18N-60', 'language switcher ada di topbar')
             : fail('TC-I18N-60', 'switcher tidak ditemukan');

  // TC-I18N-61 — default ID
  let m = await menuText(page);
  (/Akuntansi/.test(m) && /Gajian/.test(m))
    ? pass('TC-I18N-61', 'menu default Bahasa Indonesia (Akuntansi, Gajian)')
    : fail('TC-I18N-61', 'menu ID tidak sesuai');

  // TC-I18N-62 — switch to EN
  await page.goto(`${BASE}/locale/en`);
  await page.waitForTimeout(300);
  await page.goto(`${BASE}/admin/dashboard`);
  await page.waitForTimeout(500);
  m = await menuText(page);
  const enOk = /Accounting/.test(m) && /Payroll/.test(m) && !/Gajian/.test(m);
  enOk ? pass('TC-I18N-62', 'setelah switch EN, menu jadi Inggris (Accounting, Payroll)')
       : fail('TC-I18N-62', `menu EN tidak sesuai: acc=${/Accounting/.test(m)} pay=${/Payroll/.test(m)}`);

  // TC-I18N-63 — persist across navigation
  await page.goto(`${BASE}/admin/account`);
  await page.waitForTimeout(800);
  m = await menuText(page);
  /Accounting/.test(m)
    ? pass('TC-I18N-63', 'preferensi EN persist di halaman lain')
    : fail('TC-I18N-63', 'preferensi tidak persist');

  // TC-I18N-64 — back to ID
  await page.goto(`${BASE}/locale/id`);
  await page.waitForTimeout(300);
  await page.goto(`${BASE}/admin/dashboard`);
  await page.waitForTimeout(500);
  m = await menuText(page);
  (/Akuntansi/.test(m) && !/Accounting/.test(m))
    ? pass('TC-I18N-64', 'balik ke ID, menu kembali Bahasa Indonesia')
    : fail('TC-I18N-64', 'gagal balik ke ID');

  await ctx.close();
}
await browser.close();

const failed = results.filter(r => r[0] === 'FAIL');
console.log(`\n${'='.repeat(70)}\n${results.length - failed.length} PASS / ${failed.length} FAIL\n${'='.repeat(70)}`);
failed.forEach(([, id, note]) => console.log(`  FAIL ${id} — ${note}`));
process.exit(failed.length ? 1 : 0);
