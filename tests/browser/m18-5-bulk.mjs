// M18-5 — Bulk actions: pilih beberapa pelamar → tolak massal. + toggle Tabel.
// Run: node tests/browser/m18-5-bulk.mjs   (needs `php artisan serve` on :8000)
import { chromium } from 'playwright';
import { execSync } from 'node:child_process';
import path from 'node:path';

const BASE = 'http://127.0.0.1:8000';
const CV = path.resolve('tests/browser/fixtures/dummy-cv.pdf');
const results = [];
const pass = (id, n) => { results.push(['PASS', id, n]); console.log(`PASS ${id} — ${n}`); };
const fail = (id, n) => { results.push(['FAIL', id, n]); console.log(`FAIL ${id} — ${n}`); };
const stamp = Date.now();
const h = (action, ...args) => execSync(`php tests/browser/_m18_helper.php ${action} ${args.join(' ')}`, { cwd: process.cwd() }).toString().trim().split('\n').pop().trim();

const seed = h('seed_opening', stamp, 'BulkRole');
const [openingId, slug] = seed.split('|');

const browser = await chromium.launch();

// 3 candidates apply.
for (let i = 1; i <= 3; i++) {
  const cc = await browser.newContext();
  const cp = await cc.newPage(); cp.setDefaultTimeout(60000);
  await cp.goto(`${BASE}/karir/daftar`);
  await cp.fill('input[name="name"]', `Bulk Cand ${i}`);
  await cp.fill('input[name="email"]', `bulk${i}_${stamp}@ex.test`);
  await cp.fill('input[name="password"]', 'password123');
  await cp.fill('input[name="password_confirmation"]', 'password123');
  await Promise.all([cp.waitForNavigation(), cp.click('#btn-register')]);
  await cp.goto(`${BASE}/karir/lowongan/${slug}`);
  await cp.setInputFiles('input[name="cv"]', CV);
  await Promise.all([cp.waitForNavigation(), cp.click('#btn-submit-apply')]);
  await cc.close();
}

// HR opens pipeline, selects all 3, bulk reject.
const ac = await browser.newContext();
const ap = await ac.newPage(); ap.setDefaultTimeout(60000);
const jsErr = [];
ap.on('pageerror', e => jsErr.push(e.message));
ap.on('dialog', async d => { await d.accept(); });
await ap.goto(`${BASE}/admin/login`);
await ap.fill('input[name="email"]', 'siti@demo.test');
await ap.fill('input[name="password"]', 'password');
await Promise.all([ap.waitForNavigation(), ap.click('button[type="submit"]')]);
await ap.goto(`${BASE}/admin/recruitment/pipeline?job_opening_id=${openingId}`);
await ap.waitForTimeout(1200);

// toggle table view link exists
const tableLink = await ap.locator('#btn-table-view').count();
tableLink ? pass('TC-BLK-01', 'toggle "Tampilan Tabel" tersedia')
          : fail('TC-BLK-01', 'toggle tabel tidak ada');

const checks = ap.locator('.bulk-check');
const n = await checks.count();
(n >= 3) ? pass('TC-BLK-02', `checkbox pilih muncul di kartu (${n})`)
         : fail('TC-BLK-02', `checkbox kurang: ${n}`);

// select all 3
for (let i = 0; i < n; i++) await checks.nth(i).check();
await ap.waitForTimeout(400);
const barVisible = await ap.locator('#bulk-bar').isVisible();
const countTxt = await ap.locator('#bulk-count').innerText();
(barVisible && countTxt === String(n))
  ? pass('TC-BLK-03', `bar aksi massal muncul (${countTxt} dipilih)`)
  : fail('TC-BLK-03', `bar aksi massal salah: visible=${barVisible} count=${countTxt}`);

// bulk reject
await ap.locator('#bulk-reject-btn').click();
await ap.waitForTimeout(2500);

const rejectedCount = h('stage_count_rejected', openingId);
(parseInt(rejectedCount) === n)
  ? pass('TC-BLK-04', `${rejectedCount} pelamar ditolak massal (semua terpilih)`)
  : fail('TC-BLK-04', `rejected=${rejectedCount}, harusnya ${n}`);

if (jsErr.length) fail('TC-BLK-JS', 'JS error: ' + jsErr.join(' | '));
else pass('TC-BLK-JS', 'tidak ada JS error');

await ac.close();
await browser.close();

h('cleanup', openingId, stamp);

const failed = results.filter(r => r[0] === 'FAIL');
console.log(`\n${'='.repeat(70)}\n${results.length - failed.length} PASS / ${failed.length} FAIL\n${'='.repeat(70)}`);
failed.forEach(([, id, n]) => console.log(`  FAIL ${id} — ${n}`));
process.exit(failed.length ? 1 : 0);
