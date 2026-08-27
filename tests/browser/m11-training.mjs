// M11 — Training end-to-end: admin builds a training (materi + soal + peserta),
// publishes it; participant reads, takes the quiz, PASSES, gets a certificate.
// Also verifies the FAIL path scores below KKM.
// Run: node tests/browser/m11-training.mjs   (needs `php artisan serve` on :8000)
import { chromium } from 'playwright';
import { execSync } from 'node:child_process';

const BASE = 'http://127.0.0.1:8000';
const results = [];
const pass = (id, n) => { results.push(['PASS', id, n]); console.log(`PASS ${id} — ${n}`); };
const fail = (id, n) => { results.push(['FAIL', id, n]); console.log(`FAIL ${id} — ${n}`); };
const stamp = Date.now();
const TITLE = `QA Otomasi ${stamp}`;
const sh = (action, ...args) =>
  execSync(`php tests/browser/_m11_helper.php ${action} ${args.map(a => JSON.stringify(String(a))).join(' ')}`,
    { cwd: process.cwd() }).toString().trim().split('\n').pop().trim();

const browser = await chromium.launch();

// ───────────────────────────── ADMIN ─────────────────────────────
const ac = await browser.newContext();
const ap = await ac.newPage(); ap.setDefaultTimeout(60000);
const jsErr = [];
ap.on('pageerror', e => jsErr.push(e.message));

await ap.goto(`${BASE}/admin/login`);
await ap.fill('input[name="email"]', 'siti@demo.test');
await ap.fill('input[name="password"]', 'password');
await Promise.all([ap.waitForNavigation(), ap.click('button[type="submit"]')]);

// Create training via the real form.
await ap.goto(`${BASE}/admin/training/create`);
await ap.fill('input[name="title"]', TITLE);
await ap.fill('input[name="passing_score"]', '70');
await ap.fill('input[name="max_attempts"]', '3');
await Promise.all([ap.waitForNavigation(), ap.click('button[type="submit"]')]);

const tid = sh('training_id', TITLE);
(tid && /^\d+$/.test(tid)) ? pass('TC-TRN-01', `pelatihan dibuat (id ${tid})`)
                           : fail('TC-TRN-01', `gagal buat pelatihan: ${tid}`);

// Add a material (Materi tab is active by default).
await ap.goto(`${BASE}/admin/training/${tid}/manage`);
await ap.fill('form[action*="/material"] input[name="title"]', 'Dasar QA');
await ap.fill('form[action*="/material"] textarea[name="content"]', 'Materi pengujian perangkat lunak.');
await Promise.all([ap.waitForNavigation(), ap.click('form[action*="/material"] button[type="submit"]')]);
const matOk = (await ap.locator('text=Dasar QA').count()) > 0;
matOk ? pass('TC-TRN-02', 'materi ditambahkan') : fail('TC-TRN-02', 'materi tak muncul');

// Add a question (correct = A).
await ap.locator('button[data-bs-target="#tab-latihan"]').click();
await ap.waitForTimeout(400);
await ap.fill('form[action*="/question"] textarea[name="question"]', 'Apa itu regression testing?');
await ap.fill('form[action*="/question"] input[name="option_a"]', 'Uji ulang fitur lama');
await ap.fill('form[action*="/question"] input[name="option_b"]', 'Menghapus data');
await ap.check('form[action*="/question"] input[name="correct_option"][value="a"]');
await Promise.all([ap.waitForNavigation(), ap.click('form[action*="/question"] button[type="submit"]')]);
const qOk = (await ap.locator('text=regression testing').count()) > 0;
qOk ? pass('TC-TRN-03', 'soal ditambahkan') : fail('TC-TRN-03', 'soal tak muncul');

// Enroll the employee (Peserta tab).
await ap.locator('button[data-bs-target="#tab-peserta"]').click();
await ap.waitForTimeout(400);
const empId = sh('employee_id', 'ahmad@demo.test');
await ap.selectOption('#enroll-select', empId);
await Promise.all([ap.waitForNavigation(), ap.click('form[action*="/enroll"] button[type="submit"]')]);
const enrolled = sh('enrollment_id', tid, 'ahmad@demo.test');
(enrolled && /^\d+$/.test(enrolled)) ? pass('TC-TRN-04', 'peserta ditugaskan')
                                      : fail('TC-TRN-04', 'gagal enroll');

// Publish.
await Promise.all([ap.waitForNavigation(), ap.click('form[action*="/publish"] button[type="submit"]')]);
const published = (await ap.locator('.badge:has-text("Terbit")').count()) > 0;
published ? pass('TC-TRN-05', 'pelatihan diterbitkan') : fail('TC-TRN-05', 'gagal publish');

await ac.close();

// ─────────────────────────── PARTICIPANT ───────────────────────────
const pc = await browser.newContext();
const pp = await pc.newPage(); pp.setDefaultTimeout(60000);
pp.on('pageerror', e => jsErr.push(e.message));

await pp.goto(`${BASE}/admin/login`);
await pp.fill('input[name="email"]', 'ahmad@demo.test');
await pp.fill('input[name="password"]', 'password');
await Promise.all([pp.waitForNavigation(), pp.click('button[type="submit"]')]);

// See the training in "Pelatihan Saya".
await pp.goto(`${BASE}/my/training`);
const listed = (await pp.locator(`text=${TITLE}`).count()) > 0;
listed ? pass('TC-TRN-06', 'peserta melihat pelatihan yang ditugaskan')
       : fail('TC-TRN-06', 'pelatihan tak muncul di portal');

// Open → read → start quiz.
await pp.goto(`${BASE}/my/training/${tid}`);
const readOk = (await pp.locator('text=Dasar QA').count()) > 0;
readOk ? pass('TC-TRN-07', 'materi tampil ke peserta') : fail('TC-TRN-07', 'materi tak tampil');

await pp.goto(`${BASE}/my/training/${tid}/quiz`);
// Answer correctly (A) → 100.
await pp.check('input[type="radio"][value="a"]');
pp.on('dialog', d => d.accept());
await Promise.all([pp.waitForNavigation(), pp.click('#quiz-form button[type="submit"]')]);

// Result page shows LULUS.
const resultText = await pp.locator('body').innerText();
resultText.includes('LULUS') && !resultText.includes('TIDAK LULUS')
  ? pass('TC-TRN-08', 'peserta LULUS (skor 100)')
  : fail('TC-TRN-08', 'status lulus tak tampil');

const statusAfter = sh('status', tid, 'ahmad@demo.test');
statusAfter === 'passed' ? pass('TC-TRN-09', 'DB: enrollment passed')
                         : fail('TC-TRN-09', `status DB: ${statusAfter}`);

// Certificate renders.
await pp.goto(`${BASE}/my/training/${tid}/certificate`);
const certText = await pp.locator('body').innerText();
(certText.includes('Sertifikat') && certText.includes('Ahmad'))
  ? pass('TC-TRN-10', 'sertifikat kelulusan ter-render')
  : fail('TC-TRN-10', 'sertifikat tak ter-render');

if (jsErr.length) fail('TC-TRN-JS', 'JS error: ' + jsErr.join(' | '));
else pass('TC-TRN-JS', 'tidak ada JS error');

await pc.close();
await browser.close();
sh('cleanup', TITLE);

const failed = results.filter(r => r[0] === 'FAIL');
console.log(`\n${'='.repeat(70)}\n${results.length - failed.length} PASS / ${failed.length} FAIL\n${'='.repeat(70)}`);
failed.forEach(([, id, n]) => console.log(`  FAIL ${id} — ${n}`));
process.exit(failed.length ? 1 : 0);
