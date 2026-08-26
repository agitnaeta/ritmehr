// M08 — Unified Reports ("Laporan") menu: browser-driven verification.
// Run: node tests/browser/m08-reports-menu.mjs   (needs `php artisan serve` on :8000)
//
// Note: demo super_admin's locale may be EN, so the dropdown title renders
// "Reports" (id: "Laporan"). Tests match by structure/href, not label text.
//
// Covers:
//   TC-RPT-01  a single Reports dropdown exists and holds all report links
//   TC-RPT-02  it contains every report (attendance/salary/loan/headcount/tax/bpjs/leave) by href
//   TC-RPT-03  report links are NOT duplicated in the old Leave / Tax menus
//   TC-RPT-04  each report link opens (HTTP 200, real page render)
//   TC-RPT-05  dashboard money uses the configured currency symbol (Rp)
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

await login(page, 'siti@demo.test');
await page.goto(`${BASE}/admin/dashboard`);

// Snapshot the sidebar: for each top-level dropdown, its title + item hrefs.
const menu = await page.evaluate(() => {
  const dropdowns = [...document.querySelectorAll('ul.navbar-nav li.nav-item.dropdown')];
  return dropdowns.map(dd => {
    const toggle = dd.querySelector(':scope > a.nav-link.dropdown-toggle');
    return {
      title: toggle ? toggle.textContent.trim() : '',
      hrefs: [...dd.querySelectorAll('.dropdown-menu a.dropdown-item')]
        .map(a => (a.getAttribute('href') || '').replace('http://127.0.0.1:8000', '')),
    };
  });
});

// The Reports dropdown = the one that contains /admin/report/attendance.
const reportDropdowns = menu.filter(d => d.hrefs.some(h => h.includes('/report/attendance')));

// TC-RPT-01 — exactly one dropdown owns the report links.
reportDropdowns.length === 1
  ? pass('TC-RPT-01', `satu dropdown laporan ("${reportDropdowns[0].title}") memuat semua link report`)
  : fail('TC-RPT-01', `dropdown pemilik report ditemukan ${reportDropdowns.length}x (harusnya 1)`);

// TC-RPT-02 — it contains every expected report path.
const rpt = reportDropdowns[0] || { hrefs: [] };
const wantedPaths = [
  '/report/attendance', '/report/salary', '/report/loan', '/report/headcount',
  '/tax-report/annual', '/tax-report/bpjs', '/leave-report',
];
const missing = wantedPaths.filter(w => !rpt.hrefs.some(h => h.includes(w)));
missing.length === 0
  ? pass('TC-RPT-02', `dropdown laporan lengkap: ${rpt.hrefs.join(', ')}`)
  : fail('TC-RPT-02', `path kurang: ${missing.join(', ')} — yang ada: ${rpt.hrefs.join(', ')}`);

// TC-RPT-03 — old duplicates removed from Leave (leave-report) & Tax (tax-report/*) menus.
const otherDropdowns = menu.filter(d => d !== reportDropdowns[0]);
const leakLeave = otherDropdowns.filter(d => d.hrefs.some(h => h.includes('/leave-report')));
const leakTax = otherDropdowns.filter(d => d.hrefs.some(h => h.includes('/tax-report/')));
(leakLeave.length === 0 && leakTax.length === 0)
  ? pass('TC-RPT-03', 'link laporan tidak lagi terduplikasi di menu Cuti/Pajak')
  : fail('TC-RPT-03', `duplikat tersisa — leave di ${leakLeave.map(d=>d.title)}, tax di ${leakTax.map(d=>d.title)}`);

// TC-RPT-04 — every report link opens with HTTP 200.
let allOk = true;
const notes = [];
for (const path of wantedPaths) {
  const resp = await page.goto(`${BASE}/admin${path}`);
  const ok = resp.status() === 200;
  if (!ok) allOk = false;
  notes.push(`${path}=${resp.status()}`);
}
allOk
  ? pass('TC-RPT-04', `semua laporan terbuka 200: ${notes.join(', ')}`)
  : fail('TC-RPT-04', `ada laporan gagal: ${notes.join(', ')}`);

// TC-RPT-05 — dashboard money shows the configured currency (IDR → "Rp").
await page.goto(`${BASE}/admin/dashboard`);
const hasRp = await page.evaluate(() => /Rp\s?[\d.]/.test(document.body.innerText));
hasRp
  ? pass('TC-RPT-05', 'angka uang dashboard pakai simbol mata uang tersetting (Rp)')
  : fail('TC-RPT-05', 'tidak menemukan format mata uang di dashboard');

await ctx.close();
await browser.close();

const failed = results.filter(r => r[0] === 'FAIL');
console.log(`\n${'='.repeat(70)}\n${results.length - failed.length} PASS / ${failed.length} FAIL\n${'='.repeat(70)}`);
failed.forEach(([, id, note]) => console.log(`  FAIL ${id} — ${note}`));
process.exit(failed.length ? 1 : 0);
