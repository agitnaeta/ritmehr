// M04 — Portal polish: attendance calendar + payslip print (browser).
// Run: node tests/browser/m04-portal-polish.mjs   (php artisan serve on :8000)
//
// Covers:
//   TC-MY-30  attendance page shows calendar grid by default
//   TC-MY-31  toggle to table view works, and back to calendar
//   TC-MY-32  payslip detail has "Unduh / Cetak Slip" button
//   TC-MY-33  print page renders standalone payslip (SLIP GAJI + net)
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
  // ahmad@demo.test is an employee → lands in /my portal.
  await login(page, 'ahmad@demo.test');

  // TC-MY-30 — attendance calendar visible by default
  await page.goto(`${BASE}/my/attendance`);
  await page.waitForTimeout(600);
  const calVisible = await page.evaluate(() => {
    const el = document.getElementById('viewCalendar');
    return el && getComputedStyle(el).display !== 'none';
  });
  const hasGrid = await page.evaluate(() =>
    [...document.querySelectorAll('thead th')].some(th => th.textContent.trim() === 'Sen'));
  (calVisible && hasGrid)
    ? pass('TC-MY-30', 'kalender kehadiran tampil default (grid 7 hari)')
    : fail('TC-MY-30', `calVisible=${calVisible}, grid=${hasGrid}`);

  // TC-MY-31 — toggle to table then back
  await page.click('#btnViewTable');
  await page.waitForTimeout(300);
  const tableShown = await page.evaluate(() => {
    const t = document.getElementById('viewTable');
    const c = document.getElementById('viewCalendar');
    return getComputedStyle(t).display !== 'none' && getComputedStyle(c).display === 'none';
  });
  await page.click('#btnViewCalendar');
  await page.waitForTimeout(300);
  const calBack = await page.evaluate(() => {
    const c = document.getElementById('viewCalendar');
    return getComputedStyle(c).display !== 'none';
  });
  (tableShown && calBack)
    ? pass('TC-MY-31', 'toggle Tabel/Kalender berfungsi dua arah')
    : fail('TC-MY-31', `tableShown=${tableShown}, calBack=${calBack}`);

  // TC-MY-32 — payslip detail print button
  await page.goto(`${BASE}/my/salary`);
  await page.waitForTimeout(500);
  const firstShow = await page.evaluate(() => {
    const a = [...document.querySelectorAll('a')].find(x => /salary\/\d+$/.test(x.getAttribute('href') || ''));
    return a ? a.getAttribute('href') : null;
  });
  if (!firstShow) { fail('TC-MY-32', 'tidak ada slip untuk diuji'); fail('TC-MY-33', 'skip'); }
  else {
    const showPath = firstShow.startsWith('http') ? new URL(firstShow).pathname : firstShow;
    await page.goto(`${BASE}${showPath}`);
    await page.waitForTimeout(400);
    const printLink = await page.evaluate(() =>
      [...document.querySelectorAll('a')].some(a => /print/.test(a.getAttribute('href') || '') && /Cetak|Unduh/i.test(a.textContent)));
    printLink ? pass('TC-MY-32', 'tombol "Unduh / Cetak Slip" ada di detail slip')
             : fail('TC-MY-32', 'tombol cetak tidak ditemukan');

    // TC-MY-33 — print page renders
    await page.goto(`${BASE}${showPath}/print`);
    await page.waitForTimeout(400);
    const body = await page.evaluate(() => document.body.innerText);
    (/SLIP GAJI/.test(body) && /DITERIMA \(NET\)/.test(body))
      ? pass('TC-MY-33', 'halaman cetak slip tampil (SLIP GAJI + net)')
      : fail('TC-MY-33', 'halaman cetak tidak lengkap');
  }

  await ctx.close();
}
await browser.close();

const failed = results.filter(r => r[0] === 'FAIL');
console.log(`\n${'='.repeat(70)}\n${results.length - failed.length} PASS / ${failed.length} FAIL\n${'='.repeat(70)}`);
failed.forEach(([, id, note]) => console.log(`  FAIL ${id} — ${note}`));
process.exit(failed.length ? 1 : 0);
