// M17-1 — Career portal: candidate register → login → apply → apply-once guard.
// Run: node tests/browser/m17-1-career.mjs   (needs `php artisan serve` on :8000)
//
// Real browser, native form submits + real file upload. No API bypass.
import { chromium } from 'playwright';
import { execSync } from 'node:child_process';
import path from 'node:path';

const BASE = 'http://127.0.0.1:8000';
const CV = path.resolve('tests/browser/fixtures/dummy-cv.pdf');
const results = [];
const pass = (id, n) => { results.push(['PASS', id, n]); console.log(`PASS ${id} — ${n}`); };
const fail = (id, n) => { results.push(['FAIL', id, n]); console.log(`FAIL ${id} — ${n}`); };
const stamp = Date.now();
const email = `cand${stamp}@example.test`;

// Seed one published opening via artisan tinker (returns slug).
const slug = execSync(
  `php artisan tinker --execute="` +
  `\\$o = App\\Models\\JobOpening::create(['title'=>'QA Browser Role ${stamp}','vacancies'=>1,'status'=>'open','is_published'=>true,'published_at'=>now()]); echo \\$o->slug;"`,
  { cwd: process.cwd() }
).toString().trim().split('\n').pop().trim();
console.log('seeded opening slug:', slug);

const browser = await chromium.launch();
const ctx = await browser.newContext();
const page = await ctx.newPage();
page.setDefaultTimeout(60000);
const jsErrors = [];
page.on('pageerror', e => jsErrors.push(e.message));

// TC-CAR-01 — public careers page lists the opening.
await page.goto(`${BASE}/karir`);
const listed = await page.evaluate((t) => document.body.innerText.includes(t), `QA Browser Role ${stamp}`);
listed ? pass('TC-CAR-01', 'lowongan tampil di portal karir publik')
       : fail('TC-CAR-01', 'lowongan tidak tampil di /karir');

// TC-CAR-02 — guest sees "login to apply", not the apply form.
await page.goto(`${BASE}/karir/lowongan/${slug}`);
const guestSeesLogin = await page.locator('#btn-login-to-apply').count();
guestSeesLogin ? pass('TC-CAR-02', 'tamu diarahkan masuk dulu untuk melamar')
               : fail('TC-CAR-02', 'tombol "masuk untuk melamar" tak ada untuk tamu');

// TC-CAR-03 — register a new candidate account (native form).
await page.goto(`${BASE}/karir/daftar`);
await page.fill('input[name="name"]', 'Budi Santoso');
await page.fill('input[name="email"]', email);
await page.fill('input[name="phone"]', '08123456789');
await page.fill('input[name="password"]', 'password123');
await page.fill('input[name="password_confirmation"]', 'password123');
await Promise.all([page.waitForNavigation(), page.click('#btn-register')]);
const onDashboard = page.url().includes('/karir/akun');
onDashboard ? pass('TC-CAR-03', `kandidat terdaftar & login otomatis (${email})`)
            : fail('TC-CAR-03', `setelah daftar tidak di dashboard (url=${page.url()})`);

// TC-CAR-04 — apply to the opening with a real CV upload.
await page.goto(`${BASE}/karir/lowongan/${slug}`);
await page.setInputFiles('input[name="cv"]', CV);
await page.fill('input[name="expected_salary"]', '9000000');
await page.fill('textarea[name="cover_note"]', 'Saya sangat tertarik dengan posisi ini.');
await Promise.all([page.waitForNavigation(), page.click('#btn-submit-apply')]);
const appliedOk = await page.evaluate(() => /berhasil dikirim/i.test(document.body.innerText));
appliedOk ? pass('TC-CAR-04', 'lamaran + upload CV berhasil (native file upload)')
          : fail('TC-CAR-04', 'lamaran tidak terkonfirmasi di dashboard');

// TC-CAR-05 — dashboard shows the application.
await page.goto(`${BASE}/karir/akun`);
const inDash = await page.evaluate((t) =>
  [...document.querySelectorAll('#apps-table tbody tr')].some(r => r.innerText.includes(t)), `QA Browser Role ${stamp}`);
inDash ? pass('TC-CAR-05', 'lamaran muncul di dashboard kandidat')
       : fail('TC-CAR-05', 'lamaran tidak muncul di dashboard');

// TC-CAR-06 — apply-once: re-visiting the opening shows "already applied", no form.
await page.goto(`${BASE}/karir/lowongan/${slug}`);
const alreadyApplied = await page.locator('#already-applied').count();
const noForm = await page.locator('#apply-form').count();
(alreadyApplied && noForm === 0)
  ? pass('TC-CAR-06', 'apply-once ditegakkan di UI (form lamar hilang, badge "sudah melamar")')
  : fail('TC-CAR-06', `apply-once gagal: alreadyBadge=${alreadyApplied} formStillThere=${noForm}`);

if (jsErrors.length) fail('TC-CAR-JS', `JS errors: ${jsErrors.join(' | ')}`);
else pass('TC-CAR-JS', 'tidak ada JS error');

await ctx.close();
await browser.close();

const failed = results.filter(r => r[0] === 'FAIL');
console.log(`\n${'='.repeat(70)}\n${results.length - failed.length} PASS / ${failed.length} FAIL\n${'='.repeat(70)}`);
failed.forEach(([, id, n]) => console.log(`  FAIL ${id} — ${n}`));
process.exit(failed.length ? 1 : 0);
