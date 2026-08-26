// M17-3 — CV extraction: apply with real CV, then admin searches by CV text.
// Run: node tests/browser/m17-3-cv-search.mjs   (needs `php artisan serve` on :8000)
import { chromium } from 'playwright';
import { execSync } from 'node:child_process';
import path from 'node:path';

const BASE = 'http://127.0.0.1:8000';
const CV = path.resolve('tests/browser/fixtures/dummy-cv.pdf');
const results = [];
const pass = (id, n) => { results.push(['PASS', id, n]); console.log(`PASS ${id} — ${n}`); };
const fail = (id, n) => { results.push(['FAIL', id, n]); console.log(`FAIL ${id} — ${n}`); };
const stamp = Date.now();
const email = `cvcand${stamp}@example.test`;
// Unique token we KNOW is inside the dummy CV text ("Laravel"), plus the candidate name.
const cvKeyword = 'Laravel';

const slug = execSync(
  `php artisan tinker --execute="\\$o = App\\Models\\JobOpening::create(['title'=>'CV Search Role ${stamp}','vacancies'=>1,'status'=>'open','is_published'=>true,'published_at'=>now()]); echo \\$o->slug;"`,
  { cwd: process.cwd() }
).toString().trim().split('\n').pop().trim();

const browser = await chromium.launch();
const ctx = await browser.newContext();
const page = await ctx.newPage();
page.setDefaultTimeout(60000);

// Register candidate + apply with the real CV (this triggers inline extraction).
await page.goto(`${BASE}/karir/daftar`);
await page.fill('input[name="name"]', 'Budi Santoso');
await page.fill('input[name="email"]', email);
await page.fill('input[name="password"]', 'password123');
await page.fill('input[name="password_confirmation"]', 'password123');
await Promise.all([page.waitForNavigation(), page.click('#btn-register')]);
await page.goto(`${BASE}/karir/lowongan/${slug}`);
await page.setInputFiles('input[name="cv"]', CV);
await Promise.all([page.waitForNavigation(), page.click('#btn-submit-apply')]);
const applied = await page.evaluate(() => /berhasil dikirim/i.test(document.body.innerText));
applied ? pass('TC-CV-01', 'kandidat melamar dengan CV nyata (ekstraksi inline berjalan)')
        : fail('TC-CV-01', 'apply gagal');

await ctx.close();

// Verify cv_text was extracted (DB check via artisan).
const cvLen = execSync(
  `php artisan tinker --execute="echo strlen(optional(App\\Models\\Applicant::where('email','${email}')->first())->cv_text ?? '');"`,
  { cwd: process.cwd() }
).toString().trim().split('\n').pop().trim();
(parseInt(cvLen) > 20)
  ? pass('TC-CV-02', `cv_text terekstrak ke DB (${cvLen} karakter)`)
  : fail('TC-CV-02', `cv_text kosong/pendek (len=${cvLen})`);

// Admin logs in and searches the applicant list by a CV keyword ("Laravel").
const actx = await browser.newContext();
const ap = await actx.newPage();
ap.setDefaultTimeout(60000);
await ap.goto(`${BASE}/admin/login`);
await ap.fill('input[name="email"]', 'siti@demo.test');
await ap.fill('input[name="password"]', 'password');
await Promise.all([ap.waitForNavigation(), ap.click('button[type="submit"]')]);

await ap.goto(`${BASE}/admin/applicant`);
await ap.waitForTimeout(1500);
// Backpack DataTable search box.
const searchBox = ap.locator('input[type="search"]').first();
await searchBox.fill(cvKeyword);
await ap.waitForTimeout(2000);
const foundByCv = await ap.evaluate((mail) =>
  [...document.querySelectorAll('table tbody tr')].some(r => r.innerText.includes(mail)), email);
foundByCv
  ? pass('TC-CV-03', `admin menemukan pelamar via kata di CV ("${cvKeyword}") — cv_text searchable`)
  : fail('TC-CV-03', `pencarian CV keyword "${cvKeyword}" tidak menemukan pelamar`);

await actx.close();
await browser.close();

const failed = results.filter(r => r[0] === 'FAIL');
console.log(`\n${'='.repeat(70)}\n${results.length - failed.length} PASS / ${failed.length} FAIL\n${'='.repeat(70)}`);
failed.forEach(([, id, n]) => console.log(`  FAIL ${id} — ${n}`));
process.exit(failed.length ? 1 : 0);
