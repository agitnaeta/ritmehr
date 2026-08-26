// M18-4 — Jadwalkan wawancara dari drawer (pelamar auto-terisi, tanpa dropdown re-pick).
// Run: node tests/browser/m18-4-interview.mjs   (needs `php artisan serve` on :8000)
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

const seed = h('seed_opening', stamp, 'IVRole');
const [openingId, slug] = seed.split('|');

const browser = await chromium.launch();

// Candidate applies.
const cc = await browser.newContext();
const cp = await cc.newPage(); cp.setDefaultTimeout(60000);
await cp.goto(`${BASE}/karir/daftar`);
await cp.fill('input[name="name"]', 'IV Cand');
await cp.fill('input[name="email"]', `iv${stamp}@ex.test`);
await cp.fill('input[name="password"]', 'password123');
await cp.fill('input[name="password_confirmation"]', 'password123');
await Promise.all([cp.waitForNavigation(), cp.click('#btn-register')]);
await cp.goto(`${BASE}/karir/lowongan/${slug}`);
await cp.setInputFiles('input[name="cv"]', CV);
await Promise.all([cp.waitForNavigation(), cp.click('#btn-submit-apply')]);
await cc.close();

const appId = h('app_id', openingId);

// HR opens pipeline → drawer → schedule interview.
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

await ap.locator(`.btn-detail[data-id="${appId}"]`).click();
await ap.waitForTimeout(1200);

// Reveal the inline scheduling form.
await ap.locator('#drawer-iv-toggle').click();
await ap.waitForTimeout(300);
const formVisible = await ap.locator('#drawer-iv-form').isVisible();
formVisible ? pass('TC-IV-01', 'form jadwal wawancara muncul di drawer')
            : fail('TC-IV-01', 'form jadwal tidak muncul');

// Applicant id is prefilled (no dropdown to re-pick).
const prefilled = await ap.locator('#iv-applicant-id').inputValue();
(prefilled === String(appId))
  ? pass('TC-IV-02', 'pelamar auto-terisi (tanpa pilih ulang dari dropdown)')
  : fail('TC-IV-02', `applicant id tidak prefilled: ${prefilled}`);

// Fill and submit.
await ap.fill('#iv-scheduled-at', '2026-09-01T10:00');
await ap.selectOption('#iv-mode', 'online');
await ap.fill('#iv-location', 'https://meet.example/abc');
await ap.locator('#iv-submit').click();
await ap.waitForTimeout(2000);

// Interview appears in drawer list.
const listOk = (await ap.locator('#drawer-interviews').innerText()).match(/Online/i);
listOk ? pass('TC-IV-03', 'wawancara baru muncul di daftar drawer')
       : fail('TC-IV-03', 'wawancara tidak muncul di drawer');

// DB confirms.
const dbCount = h('iv_count', appId);
(parseInt(dbCount) >= 1) ? pass('TC-IV-04', `wawancara tercatat di DB (${dbCount})`)
                         : fail('TC-IV-04', `DB tidak ada wawancara (${dbCount})`);

// Applicant advanced to interview stage.
const stage = h('stage', appId);
(stage === 'interview') ? pass('TC-IV-05', 'pelamar otomatis maju ke tahap Wawancara')
                        : fail('TC-IV-05', `stage=${stage}, harusnya interview`);

if (jsErr.length) fail('TC-IV-JS', 'JS error: ' + jsErr.join(' | '));
else pass('TC-IV-JS', 'tidak ada JS error');

await ac.close();
await browser.close();

h('cleanup', openingId, stamp);

const failed = results.filter(r => r[0] === 'FAIL');
console.log(`\n${'='.repeat(70)}\n${results.length - failed.length} PASS / ${failed.length} FAIL\n${'='.repeat(70)}`);
failed.forEach(([, id, n]) => console.log(`  FAIL ${id} — ${n}`));
process.exit(failed.length ? 1 : 0);
