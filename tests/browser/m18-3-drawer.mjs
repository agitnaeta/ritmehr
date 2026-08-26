// M18-3 — Drawer detail pelamar: klik ikon detail → offcanvas muncul dengan
// CV preview, skor AI + rincian kriteria, dan timeline tahap.
// Run: node tests/browser/m18-3-drawer.mjs   (needs `php artisan serve` on :8000)
import { chromium } from 'playwright';
import { execSync } from 'node:child_process';
import path from 'node:path';

const BASE = 'http://127.0.0.1:8000';
const CV = path.resolve('tests/browser/fixtures/dummy-cv.pdf');
const results = [];
const pass = (id, n) => { results.push(['PASS', id, n]); console.log(`PASS ${id} — ${n}`); };
const fail = (id, n) => { results.push(['FAIL', id, n]); console.log(`FAIL ${id} — ${n}`); };
const stamp = Date.now();
const q = (php) => execSync(`php artisan tinker --execute=${JSON.stringify(php)}`, { cwd: process.cwd() }).toString().trim().split('\n').pop().trim();
const seedScript = (action, ...args) => execSync(`php tests/browser/_m18_helper.php ${action} ${args.join(' ')}`, { cwd: process.cwd() }).toString().trim().split('\n').pop().trim();

const seed = seedScript('seed_opening', stamp, 'DrawerRole');
const [openingId, slug] = seed.split('|');

const browser = await chromium.launch();

// Candidate applies with a real CV.
const cc = await browser.newContext();
const cp = await cc.newPage(); cp.setDefaultTimeout(60000);
await cp.goto(`${BASE}/karir/daftar`);
await cp.fill('input[name="name"]', 'Drawer Cand');
await cp.fill('input[name="email"]', `drw${stamp}@ex.test`);
await cp.fill('input[name="password"]', 'password123');
await cp.fill('input[name="password_confirmation"]', 'password123');
await Promise.all([cp.waitForNavigation(), cp.click('#btn-register')]);
await cp.goto(`${BASE}/karir/lowongan/${slug}`);
await cp.setInputFiles('input[name="cv"]', CV);
await Promise.all([cp.waitForNavigation(), cp.click('#btn-submit-apply')]);
await cc.close();

const appId = seedScript('app_id', openingId);
// Seed AI reasoning + a stage transition so drawer has rich content.
seedScript('enrich_ai', appId);

// HR opens pipeline, clicks detail button, drawer appears.
const ac = await browser.newContext();
const ap = await ac.newPage(); ap.setDefaultTimeout(60000);
const jsErr = [];
ap.on('pageerror', e => jsErr.push(e.message));
await ap.goto(`${BASE}/admin/login`);
await ap.fill('input[name="email"]', 'siti@demo.test');
await ap.fill('input[name="password"]', 'password');
await Promise.all([ap.waitForNavigation(), ap.click('button[type="submit"]')]);
await ap.goto(`${BASE}/admin/recruitment/pipeline?job_opening_id=${openingId}`);
await ap.waitForTimeout(1200);

const detailBtn = ap.locator(`.btn-detail[data-id="${appId}"]`);
(await detailBtn.count()) ? pass('TC-DRW-01', 'tombol detail muncul di kartu')
                          : fail('TC-DRW-01', 'tombol detail tak ada');

await detailBtn.click();
await ap.waitForTimeout(1500);

const drawerVisible = await ap.locator('#applicantDrawer.show').count();
drawerVisible ? pass('TC-DRW-02', 'drawer offcanvas terbuka')
              : fail('TC-DRW-02', 'drawer tidak terbuka');

const nameOk = (await ap.locator('#drawer-name').innerText()).includes('Drawer Cand');
nameOk ? pass('TC-DRW-03', 'nama pelamar tampil di drawer')
       : fail('TC-DRW-03', 'nama tidak tampil');

const scoreOk = (await ap.locator('#drawer-ai-score').innerText()).includes('91');
scoreOk ? pass('TC-DRW-04', 'skor AI (91) tampil di drawer')
        : fail('TC-DRW-04', 'skor AI tidak tampil');

const critOk = (await ap.locator('#drawer-ai-criteria').innerText()).includes('Laravel');
critOk ? pass('TC-DRW-05', 'rincian kriteria AI (Laravel) tampil — bukan sekadar tooltip')
       : fail('TC-DRW-05', 'rincian kriteria tidak tampil');

const cvSrc = await ap.locator('#drawer-cv').getAttribute('src');
(cvSrc && cvSrc.includes(`/applicant/${appId}/cv`))
  ? pass('TC-DRW-06', 'iframe CV menunjuk ke stream CV pelamar')
  : fail('TC-DRW-06', `iframe CV src salah: ${cvSrc}`);

const tlOk = (await ap.locator('#drawer-timeline').innerText()).includes('Seleksi Berkas');
tlOk ? pass('TC-DRW-07', 'timeline tahap tampil (→ Seleksi Berkas)')
     : fail('TC-DRW-07', 'timeline tidak tampil');

if (jsErr.length) fail('TC-DRW-JS', 'JS error: ' + jsErr.join(' | '));
else pass('TC-DRW-JS', 'tidak ada JS error');

await ac.close();
await browser.close();

seedScript('cleanup', openingId, stamp);

const failed = results.filter(r => r[0] === 'FAIL');
console.log(`\n${'='.repeat(70)}\n${results.length - failed.length} PASS / ${failed.length} FAIL\n${'='.repeat(70)}`);
failed.forEach(([, id, n]) => console.log(`  FAIL ${id} — ${n}`));
process.exit(failed.length ? 1 : 0);
