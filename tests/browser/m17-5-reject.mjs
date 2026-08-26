// M17-5 — Reject flow through the real admin UI: candidate applies, HR clicks
// "Tolak" on the pipeline card → CV deleted permanently, account survives.
// Run: node tests/browser/m17-5-reject.mjs   (needs `php artisan serve` on :8000)
import { chromium } from 'playwright';
import { execSync } from 'node:child_process';
import path from 'node:path';

const BASE = 'http://127.0.0.1:8000';
const CV = path.resolve('tests/browser/fixtures/dummy-cv.pdf');
const results = [];
const pass = (id, n) => { results.push(['PASS', id, n]); console.log(`PASS ${id} — ${n}`); };
const fail = (id, n) => { results.push(['FAIL', id, n]); console.log(`FAIL ${id} — ${n}`); };
const stamp = Date.now();
const email = `rej${stamp}@ex.test`;

const seed = execSync(
  `php artisan tinker --execute="\\$o = App\\Models\\JobOpening::create(['title'=>'Reject Role ${stamp}','vacancies'=>1,'status'=>'open','is_published'=>true,'published_at'=>now()]); echo \\$o->id.chr(124).\\$o->slug;"`,
  { cwd: process.cwd() }
).toString().trim().split('\n').pop().trim();
const [openingId, openingSlug] = seed.split('|');

const browser = await chromium.launch();

// Candidate registers + applies with real CV.
const cctx = await browser.newContext();
const cp = await cctx.newPage();
cp.setDefaultTimeout(60000);
await cp.goto(`${BASE}/karir/daftar`);
await cp.fill('input[name="name"]', 'Reject Me');
await cp.fill('input[name="email"]', email);
await cp.fill('input[name="password"]', 'password123');
await cp.fill('input[name="password_confirmation"]', 'password123');
await Promise.all([cp.waitForNavigation(), cp.click('#btn-register')]);
await cp.goto(`${BASE}/karir/lowongan/${openingSlug}`);
await cp.setInputFiles('input[name="cv"]', CV);
await Promise.all([cp.waitForNavigation(), cp.click('#btn-submit-apply')]);
await cctx.close();
pass('TC-REJ-01', 'kandidat melamar dengan CV');

// Confirm CV file exists on disk before reject.
const cvBefore = execSync(
  `php artisan tinker --execute="echo App\\Models\\Applicant::where('email','${email}')->value('cv_path');"`,
  { cwd: process.cwd() }
).toString().trim().split('\n').pop().trim();
(cvBefore && cvBefore !== '')
  ? pass('TC-REJ-02', `CV tersimpan sebelum reject (${cvBefore})`)
  : fail('TC-REJ-02', 'CV path kosong sebelum reject');

// HR opens pipeline, clicks "Tolak", accepts confirm dialog.
const actx = await browser.newContext();
const ap = await actx.newPage();
ap.setDefaultTimeout(60000);
await ap.goto(`${BASE}/admin/login`);
await ap.fill('input[name="email"]', 'siti@demo.test');
await ap.fill('input[name="password"]', 'password');
await Promise.all([ap.waitForNavigation(), ap.click('button[type="submit"]')]);
await ap.goto(`${BASE}/admin/recruitment/pipeline?job_opening_id=${openingId}`);
await ap.waitForTimeout(1000);

const rejectBtn = ap.locator('.btn-reject').first();
const hasReject = await rejectBtn.count();
hasReject ? pass('TC-REJ-03', 'tombol "Tolak" muncul di kartu pelamar')
          : fail('TC-REJ-03', 'tombol Tolak tidak ada');

ap.on('dialog', async d => { await d.accept(); }); // confirm + any alert
await rejectBtn.click();
await ap.waitForTimeout(3000);

// Verify: CV purged, stage rejected, candidate account still present.
const after = execSync(
  `php artisan tinker --execute="\\$a=App\\Models\\Applicant::where('email','${email}')->first(); echo \\$a->stage.chr(124).(\\$a->cv_path===null?'NULL':'HAS').chr(124).(App\\Models\\Candidate::where('email','${email}')->exists()?'ACCT':'NOACCT');"`,
  { cwd: process.cwd() }
).toString().trim().split('\n').pop().trim();
const [stage, cvState, acct] = after.split('|');

(stage === 'rejected') ? pass('TC-REJ-04', 'stage → rejected') : fail('TC-REJ-04', `stage=${stage}`);
(cvState === 'NULL') ? pass('TC-REJ-05', 'CV dihapus permanen (cv_path NULL)') : fail('TC-REJ-05', `cv masih ada: ${cvState}`);
(acct === 'ACCT') ? pass('TC-REJ-06', 'akun kandidat tetap ada (bisa lamar lowongan lain)') : fail('TC-REJ-06', 'akun kandidat hilang!');

await actx.close();
await browser.close();

// Cleanup.
execSync(
  `php artisan tinker --execute="App\\Models\\Applicant::where('email','${email}')->delete(); App\\Models\\Candidate::where('email','${email}')->delete(); App\\Models\\JobOpening::where('id',${openingId})->delete();"`,
  { cwd: process.cwd() }
);

const failed = results.filter(r => r[0] === 'FAIL');
console.log(`\n${'='.repeat(70)}\n${results.length - failed.length} PASS / ${failed.length} FAIL\n${'='.repeat(70)}`);
failed.forEach(([, id, n]) => console.log(`  FAIL ${id} — ${n}`));
process.exit(failed.length ? 1 : 0);
