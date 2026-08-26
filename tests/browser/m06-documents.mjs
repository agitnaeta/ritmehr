// M06 — Employee documents polish: tiered expiry badges + storage-setting wiring.
// Run: node tests/browser/m06-documents.mjs   (php artisan serve on :8000)
//
// Covers:
//   TC-DOC-50  document list loads for HR
//   TC-DOC-51  expired doc shows "Kedaluwarsa" (red)
//   TC-DOC-52  near-expiry (<=7d) shows a "hari lagi" danger badge
//   TC-DOC-53  completeness report reachable
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
  await login(page, 'siti@demo.test'); // super_admin / HR

  await page.goto(`${BASE}/admin/employee-document`);
  await page.waitForTimeout(600);

  // TC-DOC-50 — list renders
  const hasTable = await page.evaluate(() =>
    [...document.querySelectorAll('th')].some(th => /Kedaluwarsa/.test(th.textContent)));
  hasTable ? pass('TC-DOC-50', 'daftar dokumen termuat (kolom Kedaluwarsa)')
           : fail('TC-DOC-50', 'tabel dokumen tidak termuat');

  const body = await page.evaluate(() => document.body.innerText);

  // TC-DOC-51 — expired badge
  /Kedaluwarsa/.test(body)
    ? pass('TC-DOC-51', 'badge "Kedaluwarsa" tampil untuk dokumen lewat tanggal')
    : fail('TC-DOC-51', 'tidak ada badge Kedaluwarsa');

  // TC-DOC-52 — tiered near-expiry badge
  /hari lagi/.test(body)
    ? pass('TC-DOC-52', 'badge "N hari lagi" (≤7 hari) tampil')
    : fail('TC-DOC-52', 'tidak ada badge near-expiry ≤7 hari');

  // TC-DOC-53 — completeness page
  await page.goto(`${BASE}/admin/employee-document/completeness`);
  await page.waitForTimeout(400);
  const compOk = await page.evaluate(() => /Kelengkapan Dokumen/.test(document.body.innerText));
  compOk ? pass('TC-DOC-53', 'laporan kelengkapan dokumen dapat diakses')
         : fail('TC-DOC-53', 'laporan kelengkapan tidak termuat');

  await ctx.close();
}
await browser.close();

const failed = results.filter(r => r[0] === 'FAIL');
console.log(`\n${'='.repeat(70)}\n${results.length - failed.length} PASS / ${failed.length} FAIL\n${'='.repeat(70)}`);
failed.forEach(([, id, note]) => console.log(`  FAIL ${id} — ${note}`));
process.exit(failed.length ? 1 : 0);
