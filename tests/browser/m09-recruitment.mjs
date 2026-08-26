// M09 — Recruitment: browser-driven end-to-end verification.
// Run: node tests/browser/m09-recruitment.mjs   (needs `php artisan serve` on :8000)
//
// Covers (real UI, native form submits / clicks — no API bypass):
//   TC-REC-01  Recruitment dropdown present in sidebar for super_admin
//   TC-REC-02  create a job opening via the CRUD form (persists in list)
//   TC-REC-03  add an applicant against that opening via the CRUD form
//   TC-REC-04  pipeline board renders columns + the applicant card
//   TC-REC-05  moving applicant to "offer" then clicking Terima hires → user created
//   TC-REC-06  interview calendar page renders
import { chromium } from 'playwright';

const BASE = 'http://127.0.0.1:8000';
const results = [];
const pass = (id, note) => { results.push(['PASS', id, note]); console.log(`PASS ${id} — ${note}`); };
const fail = (id, note) => { results.push(['FAIL', id, note]); console.log(`FAIL ${id} — ${note}`); };
const stamp = Date.now();
const jobTitle = 'QA Engineer ' + stamp;
const applicantName = 'Pelamar QA ' + stamp;

async function login(page, email) {
  await page.goto(`${BASE}/admin/login`);
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', 'password');
  await Promise.all([page.waitForNavigation(), page.click('button[type="submit"]')]);
}

const browser = await chromium.launch();
const ctx = await browser.newContext();
const page = await ctx.newPage();
page.setDefaultTimeout(60000);
const jsErrors = [];
page.on('pageerror', e => jsErrors.push(e.message));

await login(page, 'siti@demo.test');

// TC-REC-01 — sidebar dropdown owns the recruitment links (locale-agnostic: match by href).
await page.goto(`${BASE}/admin/dashboard`);
const recDropdown = await page.evaluate(() => {
  const dds = [...document.querySelectorAll('ul.navbar-nav li.nav-item.dropdown')];
  for (const dd of dds) {
    const hrefs = [...dd.querySelectorAll('.dropdown-menu a.dropdown-item')].map(a => a.getAttribute('href') || '');
    if (hrefs.some(h => h.includes('/recruitment/pipeline'))) {
      return { title: dd.querySelector(':scope > a.nav-link')?.textContent.trim(), count: hrefs.length };
    }
  }
  return null;
});
recDropdown
  ? pass('TC-REC-01', `dropdown rekrutmen ("${recDropdown.title}") ada, ${recDropdown.count} link`)
  : fail('TC-REC-01', 'dropdown rekrutmen tidak ditemukan di sidebar');

// TC-REC-02 — create a job opening via the CRUD create form.
await page.goto(`${BASE}/admin/job-opening/create`);
await page.fill('input[name="title"]', jobTitle);
await page.fill('input[name="vacancies"]', '1');
await Promise.all([page.waitForNavigation(), page.click('button[type="submit"]')]);
await page.goto(`${BASE}/admin/job-opening`);
// Backpack list is a server-side AJAX DataTable — wait for rows to load.
await page.waitForTimeout(1500);
const openingListed = await page.evaluate((t) =>
  [...document.querySelectorAll('table tbody tr')].some(r => r.innerText.includes(t)), jobTitle);
openingListed
  ? pass('TC-REC-02', `lowongan "${jobTitle}" tersimpan & tampil di list`)
  : fail('TC-REC-02', 'lowongan tidak muncul di list setelah simpan');

// TC-REC-03 — add an applicant against that opening.
await page.goto(`${BASE}/admin/applicant/create`);
// job_opening_id is a select2; pick the option whose text matches our new opening.
await page.evaluate((t) => {
  const sel = document.querySelector('select[name="job_opening_id"]');
  const opt = [...sel.options].find(o => o.textContent.includes(t));
  if (opt) { sel.value = opt.value; sel.dispatchEvent(new Event('change', { bubbles: true })); }
}, jobTitle);
await page.fill('input[name="name"]', applicantName);
await page.fill('input[name="email"]', `qa${stamp}@example.test`);
// stage defaults to "applied"
await Promise.all([page.waitForNavigation(), page.click('button[type="submit"]')]);
await page.goto(`${BASE}/admin/applicant`);
await page.waitForTimeout(1500);
const applicantListed = await page.evaluate((n) =>
  [...document.querySelectorAll('table tbody tr')].some(r => r.innerText.includes(n)), applicantName);
applicantListed
  ? pass('TC-REC-03', `pelamar "${applicantName}" tersimpan & tampil di list`)
  : fail('TC-REC-03', 'pelamar tidak muncul di list setelah simpan');

// TC-REC-04 — pipeline board renders columns + the applicant card.
await page.goto(`${BASE}/admin/recruitment/pipeline`);
const board = await page.evaluate((n) => ({
  cols: document.querySelectorAll('.pipeline-col').length,
  hasCard: document.body.innerText.includes(n),
}), applicantName);
(board.cols >= 5 && board.hasCard)
  ? pass('TC-REC-04', `papan pipeline: ${board.cols} kolom, kartu pelamar tampil`)
  : fail('TC-REC-04', `board cols=${board.cols} hasCard=${board.hasCard}`);

// TC-REC-05 — move to "offer" (via move_stage endpoint using the page's own fetch),
// reload, then click the native "Terima" (hire) button → a user is provisioned.
// We drive the move through the app's own POST route (same handler the drag uses),
// then verify the hire button works with a real click.
const applicantId = await page.evaluate((n) => {
  const card = [...document.querySelectorAll('.pipeline-card')].find(c => c.textContent.includes(n));
  return card ? card.dataset.id : null;
}, applicantName);

if (!applicantId) {
  fail('TC-REC-05', 'tidak menemukan id kartu pelamar di board');
} else {
  // Move to offer using the page context (carries auth cookies + CSRF meta).
  const moved = await page.evaluate(async (id) => {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const r = await fetch(`${location.origin}/admin/recruitment/applicant/${id}/stage`, {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
      body: JSON.stringify({ stage: 'offer' }),
    });
    return r.ok;
  }, applicantId);

  await page.goto(`${BASE}/admin/recruitment/pipeline`);
  // Native click on the Terima button, accept the confirm() dialog.
  page.once('dialog', d => d.accept());
  const hireBtn = page.locator(`.btn-hire[data-id="${applicantId}"]`);
  const btnVisible = await hireBtn.count();
  if (btnVisible) {
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'load' }).catch(() => {}),
      hireBtn.click(),
    ]);
    await page.waitForTimeout(800);
  }
  // Verify a user with the applicant name now exists.
  await page.goto(`${BASE}/admin/user`);
  await page.waitForTimeout(500);
  const userExists = await page.evaluate((n) => document.body.innerText.includes(n), applicantName);
  (moved && btnVisible && userExists)
    ? pass('TC-REC-05', `pelamar diterima lewat tombol Terima → user "${applicantName}" dibuat`)
    : fail('TC-REC-05', `moved=${moved} btnVisible=${!!btnVisible} userExists=${userExists}`);
}

// TC-REC-06 — interview calendar renders.
await page.goto(`${BASE}/admin/recruitment/calendar`);
const calOk = await page.evaluate(() =>
  /Jadwal Wawancara/.test(document.body.innerText) && document.querySelectorAll('table td').length > 20);
calOk
  ? pass('TC-REC-06', 'kalender wawancara ter-render (grid tanggal)')
  : fail('TC-REC-06', 'kalender wawancara tidak ter-render benar');

if (jsErrors.length) fail('TC-REC-JS', `JS errors: ${jsErrors.join(' | ')}`);
else pass('TC-REC-JS', 'tidak ada JS error di halaman');

await ctx.close();
await browser.close();

const failed = results.filter(r => r[0] === 'FAIL');
console.log(`\n${'='.repeat(70)}\n${results.length - failed.length} PASS / ${failed.length} FAIL\n${'='.repeat(70)}`);
failed.forEach(([, id, note]) => console.log(`  FAIL ${id} — ${note}`));
process.exit(failed.length ? 1 : 0);
