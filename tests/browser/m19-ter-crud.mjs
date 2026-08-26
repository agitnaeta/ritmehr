// M19-6 — TER rate CRUD loads for admin, shows seeded brackets, menu item present.
// Run: node tests/browser/m19-ter-crud.mjs   (needs `php artisan serve` on :8000 + TER seeded)
import { chromium } from 'playwright';

const BASE = 'http://127.0.0.1:8000';
const results = [];
const pass = (id, n) => { results.push(['PASS', id, n]); console.log(`PASS ${id} — ${n}`); };
const fail = (id, n) => { results.push(['FAIL', id, n]); console.log(`FAIL ${id} — ${n}`); };

const b = await chromium.launch();
const p = await b.newContext().then(c => c.newPage());
p.setDefaultTimeout(30000);
const jsErr = [];
p.on('pageerror', e => jsErr.push(e.message));

await p.goto(`${BASE}/admin/login`);
await p.fill('input[name="email"]', 'siti@demo.test');
await p.fill('input[name="password"]', 'password');
await Promise.all([p.waitForNavigation(), p.click('button[type="submit"]')]);

// 1. Menu item present under Tax & BPJS
await p.goto(`${BASE}/admin/dashboard`); await p.waitForTimeout(600);
const hasMenu = await p.evaluate(() =>
  [...document.querySelectorAll('a')].some(a => a.getAttribute('href')?.includes('/ter-rate')));
hasMenu ? pass('TC-TER-01', 'menu "Tarif TER" ada di sidebar')
        : fail('TC-TER-01', 'menu TER tidak ada');

// 2. List page loads (DataTable, server-side)
await p.goto(`${BASE}/admin/ter-rate`); await p.waitForTimeout(1800);
const code = await p.evaluate(() => document.title);
const bodyText = await p.evaluate(() => document.body.innerText);
(!bodyText.includes('403') && !bodyText.includes('Whoops'))
  ? pass('TC-TER-02', 'halaman /admin/ter-rate load tanpa error')
  : fail('TC-TER-02', 'halaman error/403');

// 3. Seeded TER data visible (category A/B/C) — DataTable loads via AJAX.
await p.waitForTimeout(2500);
// wait for DataTable rows to populate
await p.waitForFunction(() => {
  const t = document.querySelector('table#crudTable tbody');
  return t && t.innerText.trim().length > 0 && !/No data|Tidak ada data/i.test(t.innerText);
}, { timeout: 15000 }).catch(() => {});
const tableText = await p.evaluate(() => {
  const t = document.querySelector('table#crudTable') || document.querySelector('table');
  return t ? t.innerText : '';
});
const hasData = /TER A|TER B|TER C/.test(tableText);
hasData ? pass('TC-TER-03', 'data TER (kategori A/B/C) tampil di tabel')
        : fail('TC-TER-03', 'data TER tidak tampil');

// 4. Search box available (free-tier DataTable) + can search by category
const hasSearch = await p.evaluate(() =>
  !!document.querySelector('input[type="search"], .dataTables_filter input'));
hasSearch ? pass('TC-TER-04', 'kolom pencarian tabel tersedia')
          : fail('TC-TER-04', 'pencarian tidak ada');

if (jsErr.length) fail('TC-TER-JS', 'JS error: ' + jsErr.join(' | '));
else pass('TC-TER-JS', 'tidak ada JS error');

await b.close();

const failed = results.filter(r => r[0] === 'FAIL');
console.log(`\n${'='.repeat(64)}\n${results.length - failed.length} PASS / ${failed.length} FAIL\n${'='.repeat(64)}`);
failed.forEach(([, id, n]) => console.log(`  FAIL ${id} — ${n}`));
process.exit(failed.length ? 1 : 0);
