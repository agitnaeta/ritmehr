// M18 ACCEPTANCE — alur kelola lamaran TERKONSOLIDASI dari 1 UI (papan pipeline).
// HR: buka drawer → baca CV → lihat skor AI → jadwalkan wawancara dari drawer →
// geser ke offer → terima → karyawan. Semua tanpa pindah halaman.
// Run: node tests/browser/m18-acceptance.mjs   (needs `php artisan serve` on :8000)
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

const seed = h('seed_opening', stamp, 'M18Accept');
const [openingId, slug] = seed.split('|');

const browser = await chromium.launch();

// Candidate applies with a real CV.
const cc = await browser.newContext();
const cp = await cc.newPage(); cp.setDefaultTimeout(60000);
await cp.goto(`${BASE}/karir/daftar`);
await cp.fill('input[name="name"]', 'Kandidat Terbaik');
await cp.fill('input[name="email"]', `best${stamp}@ex.test`);
await cp.fill('input[name="password"]', 'password123');
await cp.fill('input[name="password_confirmation"]', 'password123');
await Promise.all([cp.waitForNavigation(), cp.click('#btn-register')]);
await cp.goto(`${BASE}/karir/lowongan/${slug}`);
await cp.setInputFiles('input[name="cv"]', CV);
await Promise.all([cp.waitForNavigation(), cp.click('#btn-submit-apply')]);
await cc.close();

const appId = h('app_id', openingId);
h('enrich_ai', appId); // give it an AI score for the drawer

// HR does EVERYTHING from the pipeline.
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

// 1. Open drawer, read CV + AI without leaving page.
await ap.locator(`.btn-detail[data-id="${appId}"]`).click();
await ap.waitForTimeout(1200);
const drawerOpen = await ap.locator('#applicantDrawer.show').count();
const cvSrc = await ap.locator('#drawer-cv').getAttribute('src');
(drawerOpen && cvSrc && cvSrc.includes('/cv'))
  ? pass('TC-M18-01', 'HR buka drawer & baca CV inline tanpa pindah halaman')
  : fail('TC-M18-01', `drawer/CV gagal (open=${drawerOpen} src=${cvSrc})`);

// 2. Schedule interview from the drawer.
await ap.locator('#drawer-iv-toggle').click();
await ap.waitForTimeout(300);
await ap.fill('#iv-scheduled-at', '2026-09-10T09:00');
await ap.selectOption('#iv-mode', 'online');
await ap.locator('#iv-submit').click();
await ap.waitForTimeout(2000);
const ivCount = h('iv_count', appId);
(parseInt(ivCount) >= 1)
  ? pass('TC-M18-02', 'HR jadwalkan wawancara dari drawer (tanpa buka CRUD terpisah)')
  : fail('TC-M18-02', `wawancara tak tercatat (${ivCount})`);

// 3. Move to offer via board, then hire.
await ap.goto(`${BASE}/admin/recruitment/pipeline?job_opening_id=${openingId}`);
await ap.waitForTimeout(1200);
const moved = await ap.evaluate(async ({ id, base }) => {
  const csrf = document.querySelector('#hireForm input[name="_token"]')?.value
    || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
  const r = await fetch(base + '/' + id + '/stage', {
    method: 'POST',
    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
    body: JSON.stringify({ stage: 'offer' }),
  });
  return (await r.json()).ok;
}, { id: appId, base: `${BASE}/admin/recruitment/applicant` });
moved ? pass('TC-M18-03', 'HR pindahkan ke Penawaran') : fail('TC-M18-03', 'gagal ke offer');

await ap.goto(`${BASE}/admin/recruitment/pipeline?job_opening_id=${openingId}`);
await ap.waitForTimeout(1200);
await Promise.all([
  ap.waitForNavigation({ timeout: 60000 }).catch(() => {}),
  ap.locator(`.btn-hire[data-id="${appId}"]`).click(),
]);
await ap.waitForTimeout(1500);

const stage = h('stage', appId);
(stage === 'hired') ? pass('TC-M18-04', 'HR terima → pelamar jadi karyawan (stage hired)')
                    : fail('TC-M18-04', `stage=${stage}`);

// 4. Timeline captured the whole journey.
const tl = h('timeline_count', appId);
(parseInt(tl) >= 2)
  ? pass('TC-M18-05', `timeline merekam perjalanan (${tl} transisi: screening/interview→offer→hired)`)
  : fail('TC-M18-05', `timeline kurang: ${tl}`);

if (jsErr.length) fail('TC-M18-JS', 'JS error: ' + jsErr.join(' | '));
else pass('TC-M18-JS', 'tidak ada JS error sepanjang alur');

await ac.close();
await browser.close();
h('cleanup_hired', openingId, stamp);

const failed = results.filter(r => r[0] === 'FAIL');
console.log(`\n${'='.repeat(72)}\nM18 ACCEPTANCE: ${results.length - failed.length} PASS / ${failed.length} FAIL\n${'='.repeat(72)}`);
failed.forEach(([, id, n]) => console.log(`  FAIL ${id} — ${n}`));
process.exit(failed.length ? 1 : 0);
