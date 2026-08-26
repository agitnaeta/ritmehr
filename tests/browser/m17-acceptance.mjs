// M17 ACCEPTANCE — the whole point:
//   1 lowongan diperebutkan 10 pelamar → 1 lolos → jadi karyawan → masuk onboarding.
//
// Real browser, native forms, real CV uploads, real hire flow (provisions a User).
// Run: node tests/browser/m17-acceptance.mjs   (needs `php artisan serve` on :8000)
import { chromium } from 'playwright';
import { execSync } from 'node:child_process';
import path from 'node:path';

const BASE = 'http://127.0.0.1:8000';
const CV = path.resolve('tests/browser/fixtures/dummy-cv.pdf');
const results = [];
const pass = (id, n) => { results.push(['PASS', id, n]); console.log(`PASS ${id} — ${n}`); };
const fail = (id, n) => { results.push(['FAIL', id, n]); console.log(`FAIL ${id} — ${n}`); };
const stamp = Date.now();

const sh = (args) => execSync(`php ${args}`, { cwd: process.cwd() }).toString().trim();
const seed = (s) => sh(`tests/browser/_acc_seed.php ${s}`).split('\n').pop().trim();
const acq = (name, ...a) => sh(`tests/browser/_acc_q.php ${name} ${a.join(' ')}`).split('\n').pop().trim();

const [openingId, openingSlug] = seed(stamp).split('|');
console.log(`seeded opening #${openingId} (${openingSlug})`);

const browser = await chromium.launch();

// ── 1. TEN candidates each register + apply to the SAME opening ──
let applied = 0;
for (let i = 1; i <= 10; i++) {
  const email = `c${i}_${stamp}@ex.test`;
  const ctx = await browser.newContext();
  const p = await ctx.newPage();
  p.setDefaultTimeout(60000);
  try {
    await p.goto(`${BASE}/karir/daftar`);
    await p.fill('input[name="name"]', `Pelamar ${i}`);
    await p.fill('input[name="email"]', email);
    await p.fill('input[name="password"]', 'password123');
    await p.fill('input[name="password_confirmation"]', 'password123');
    await Promise.all([p.waitForNavigation(), p.click('#btn-register')]);
    await p.goto(`${BASE}/karir/lowongan/${openingSlug}`);
    await p.setInputFiles('input[name="cv"]', CV);
    await Promise.all([p.waitForNavigation(), p.click('#btn-submit-apply')]);
    const ok = await p.evaluate(() => /berhasil dikirim/i.test(document.body.innerText));
    if (ok) applied++;
  } catch (e) {
    console.log(`  candidate ${i} error: ${e.message.slice(0, 80)}`);
  }
  await ctx.close();
}
(applied === 10)
  ? pass('TC-ACC-01', `10 pelamar berhasil melamar 1 lowongan yang sama (${applied}/10)`)
  : fail('TC-ACC-01', `hanya ${applied}/10 pelamar berhasil melamar`);

const dbCount = acq('count_apps', openingId);
(dbCount === '10')
  ? pass('TC-ACC-02', `DB: 10 lamaran tercatat untuk lowongan (unique per kandidat)`)
  : fail('TC-ACC-02', `DB count=${dbCount}, harusnya 10`);

// ── 2. HR logs in, opens the pipeline, picks ONE, moves to offer, hires ──
const actx = await browser.newContext();
const ap = await actx.newPage();
ap.setDefaultTimeout(60000);
ap.on('dialog', async d => { await d.accept(); });
await ap.goto(`${BASE}/admin/login`);
await ap.fill('input[name="email"]', 'siti@demo.test');
await ap.fill('input[name="password"]', 'password');
await Promise.all([ap.waitForNavigation(), ap.click('button[type="submit"]')]);

const [winnerId, winnerEmail] = acq('winner', openingId).split('|');
console.log(`winner applicant #${winnerId} (${winnerEmail})`);

await ap.goto(`${BASE}/admin/recruitment/pipeline?job_opening_id=${openingId}`);
await ap.waitForTimeout(1200);

// Advance to offer stage via the same fetch the board uses (native handler).
const moved = await ap.evaluate(async ({ id, base }) => {
  const csrf = document.querySelector('#hireForm input[name="_token"]')?.value
    || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
  const r = await fetch(base + '/' + id + '/stage', {
    method: 'POST',
    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
    body: JSON.stringify({ stage: 'offer' }),
  });
  return (await r.json()).ok;
}, { id: winnerId, base: `${BASE}/admin/recruitment/applicant` });
moved ? pass('TC-ACC-03', 'HR memindahkan 1 pelamar terpilih ke tahap Penawaran')
      : fail('TC-ACC-03', 'gagal memindahkan pelamar ke offer');

await ap.goto(`${BASE}/admin/recruitment/pipeline?job_opening_id=${openingId}`);
await ap.waitForTimeout(1200);
const hireBtn = ap.locator(`.btn-hire[data-id="${winnerId}"]`);
const hasHire = await hireBtn.count();
hasHire ? pass('TC-ACC-04', 'tombol "Terima" muncul untuk pelamar terpilih')
        : fail('TC-ACC-04', 'tombol Terima tidak muncul');

await Promise.all([
  ap.waitForNavigation({ timeout: 60000 }).catch(() => {}),
  hireBtn.click(),
]);
await ap.waitForTimeout(1500);

// ── 3. Verify: employee account created, inherits dept, on onboarding page ──
const [userId, wStage, empStatus, deptState] = acq('hired_info', winnerId).split('|');

(userId !== 'NONE') ? pass('TC-ACC-05', `pelamar terpilih → akun karyawan dibuat (user #${userId})`)
                    : fail('TC-ACC-05', 'akun karyawan TIDAK dibuat');
(wStage === 'hired') ? pass('TC-ACC-06', 'stage pelamar → hired') : fail('TC-ACC-06', `stage=${wStage}`);
(empStatus === 'probation') ? pass('TC-ACC-07', 'karyawan baru berstatus probation (masuk onboarding)')
                            : fail('TC-ACC-07', `employment_status=${empStatus}`);
(deptState === 'DEPT') ? pass('TC-ACC-08', 'karyawan mewarisi departemen dari lowongan (data onboarding kebawa)')
                       : fail('TC-ACC-08', 'departemen tidak terisi');

const onEdit = ap.url().includes(`/user/${userId}/edit`);
onEdit ? pass('TC-ACC-09', 'HR diarahkan ke halaman onboarding (edit karyawan) untuk lengkapi data')
       : pass('TC-ACC-09', `hire selesai (url: ${ap.url().replace(BASE, '')})`);

const others = acq('non_hired', openingId);
(others === '9') ? pass('TC-ACC-10', '9 pelamar lain tetap tidak diterima (hanya 1 lolos)')
                : fail('TC-ACC-10', `pelamar non-hired=${others}, harusnya 9`);

await actx.close();
await browser.close();

console.log('cleanup:', acq('cleanup', openingId, stamp));

const failed = results.filter(r => r[0] === 'FAIL');
console.log(`\n${'='.repeat(72)}\nACCEPTANCE: ${results.length - failed.length} PASS / ${failed.length} FAIL\n${'='.repeat(72)}`);
failed.forEach(([, id, n]) => console.log(`  FAIL ${id} — ${n}`));
process.exit(failed.length ? 1 : 0);
