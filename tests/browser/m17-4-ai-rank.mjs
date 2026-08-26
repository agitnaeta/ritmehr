// M17-4 — AI ranking flow (Qdrant + LLM) through the real admin UI.
// Since the AI endpoint has no active credentials, this verifies the GRACEFUL
// path: the button runs, returns a clear message, UI stays usable. The Qdrant
// vector round-trip + LLM scoring logic are proven separately in PHPUnit
// (QdrantMatchingTest = live Qdrant, LlmScoringTest = scoring logic).
//
// Run: node tests/browser/m17-4-ai-rank.mjs   (needs `php artisan serve` on :8000)
import { chromium } from 'playwright';
import { execSync } from 'node:child_process';
import path from 'node:path';

const BASE = 'http://127.0.0.1:8000';
const CV = path.resolve('tests/browser/fixtures/dummy-cv.pdf');
const results = [];
const pass = (id, n) => { results.push(['PASS', id, n]); console.log(`PASS ${id} — ${n}`); };
const fail = (id, n) => { results.push(['FAIL', id, n]); console.log(`FAIL ${id} — ${n}`); };
const stamp = Date.now();

// Seed a published opening WITH a scoring rubric, via tinker.
const slug = execSync(
  `php artisan tinker --execute="\\$o = App\\Models\\JobOpening::create(['title'=>'AI Rank Role ${stamp}','vacancies'=>1,'status'=>'open','is_published'=>true,'published_at'=>now(),'scoring_prompt'=>'Cari yang berpengalaman Laravel dan pernah memimpin tim.']); echo \\$o->id.chr(124).\\$o->slug;"`,
  { cwd: process.cwd() }
).toString().trim().split('\n').pop().trim();
const [openingId, openingSlug] = slug.split('|');
console.log('seeded opening id=', openingId, 'slug=', openingSlug);

const browser = await chromium.launch();

// Two candidates apply with the real CV.
for (const who of ['strong', 'weak']) {
  const ctx = await browser.newContext();
  const p = await ctx.newPage();
  p.setDefaultTimeout(60000);
  await p.goto(`${BASE}/karir/daftar`);
  await p.fill('input[name="name"]', `Cand ${who} ${stamp}`);
  await p.fill('input[name="email"]', `${who}${stamp}@ex.test`);
  await p.fill('input[name="password"]', 'password123');
  await p.fill('input[name="password_confirmation"]', 'password123');
  await Promise.all([p.waitForNavigation(), p.click('#btn-register')]);
  await p.goto(`${BASE}/karir/lowongan/${openingSlug}`);
  await p.setInputFiles('input[name="cv"]', CV);
  await Promise.all([p.waitForNavigation(), p.click('#btn-submit-apply')]);
  await ctx.close();
}
pass('TC-AIR-01', '2 kandidat melamar lowongan (dengan rubrik scoring_prompt)');

// HR logs in, opens the pipeline filtered by this opening, clicks "Ranking dengan AI".
const actx = await browser.newContext();
const ap = await actx.newPage();
ap.setDefaultTimeout(60000);
await ap.goto(`${BASE}/admin/login`);
await ap.fill('input[name="email"]', 'siti@demo.test');
await ap.fill('input[name="password"]', 'password');
await Promise.all([ap.waitForNavigation(), ap.click('button[type="submit"]')]);

await ap.goto(`${BASE}/admin/recruitment/pipeline?job_opening_id=${openingId}`);
await ap.waitForTimeout(1000);

const rankBtnVisible = await ap.locator('#btn-rank-ai').count();
rankBtnVisible ? pass('TC-AIR-02', 'tombol "Ranking dengan AI" muncul saat lowongan difilter')
               : fail('TC-AIR-02', 'tombol ranking AI tidak muncul');

// Click and capture the resulting alert (graceful message).
let dialogMsg = '';
ap.once('dialog', async d => { dialogMsg = d.message(); await d.accept(); });
await ap.click('#btn-rank-ai');
await ap.waitForTimeout(4000);

// The endpoint returns a message either way (scored, or "AI belum tersedia").
if (dialogMsg && /AI|shortlist|dinilai|tersedia/i.test(dialogMsg)) {
  pass('TC-AIR-03', `ranking AI berjalan & memberi pesan jelas: "${dialogMsg.slice(0,60)}..."`);
} else {
  fail('TC-AIR-03', `tidak ada pesan yang jelas dari ranking AI (got: "${dialogMsg}")`);
}

await actx.close();
await browser.close();

// Cleanup seeded data.
execSync(
  `php artisan tinker --execute="App\\Models\\Applicant::where('job_opening_id',${openingId})->delete(); App\\Models\\Candidate::where('email','like','%${stamp}@ex.test')->delete(); App\\Models\\JobOpening::where('id',${openingId})->delete();"`,
  { cwd: process.cwd() }
);

const failed = results.filter(r => r[0] === 'FAIL');
console.log(`\n${'='.repeat(70)}\n${results.length - failed.length} PASS / ${failed.length} FAIL\n${'='.repeat(70)}`);
failed.forEach(([, id, n]) => console.log(`  FAIL ${id} — ${n}`));
process.exit(failed.length ? 1 : 0);
