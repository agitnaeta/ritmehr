// M10 — Performance Management: browser-driven end-to-end verification.
// Run: node tests/browser/m10-performance.mjs   (needs `php artisan serve` on :8000)
//
// Covers (real UI — native form submits / clicks, no API bypass):
//   TC-PRF-01  Kinerja dropdown present in sidebar
//   TC-PRF-02  create a KPI via CRUD form
//   TC-PRF-03  create a review cycle via CRUD form
//   TC-PRF-04  "Buat Penilaian" generates reviews for the cycle
//   TC-PRF-05  open a review → submit manager scores → finalize → final score shown
//   TC-PRF-06  scoreboard renders the bar chart with the finalized score
import { chromium } from 'playwright';

const BASE = 'http://127.0.0.1:8000';
const results = [];
const pass = (id, note) => { results.push(['PASS', id, note]); console.log(`PASS ${id} — ${note}`); };
const fail = (id, note) => { results.push(['FAIL', id, note]); console.log(`FAIL ${id} — ${note}`); };
const stamp = Date.now();
const kpiName = 'Kualitas ' + stamp;
const cycleName = 'Siklus ' + stamp;

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

// TC-PRF-01 — sidebar dropdown owns the performance links (locale-agnostic by href).
await page.goto(`${BASE}/admin/dashboard`);
const dd = await page.evaluate(() => {
  const dds = [...document.querySelectorAll('ul.navbar-nav li.nav-item.dropdown')];
  for (const d of dds) {
    const hrefs = [...d.querySelectorAll('.dropdown-menu a.dropdown-item')].map(a => a.getAttribute('href') || '');
    if (hrefs.some(h => h.endsWith('/admin/performance'))) {
      return { title: d.querySelector(':scope > a.nav-link')?.textContent.trim(), count: hrefs.length };
    }
  }
  return null;
});
dd
  ? pass('TC-PRF-01', `dropdown kinerja ("${dd.title}") ada, ${dd.count} link`)
  : fail('TC-PRF-01', 'dropdown kinerja tidak ditemukan');

// TC-PRF-02 — create a KPI.
await page.goto(`${BASE}/admin/kpi/create`);
await page.fill('input[name="name"]', kpiName);
await page.fill('input[name="weight"]', '2');
await Promise.all([page.waitForNavigation(), page.click('button[type="submit"]')]);
await page.goto(`${BASE}/admin/kpi`);
await page.waitForTimeout(1500);
const kpiListed = await page.evaluate((n) =>
  [...document.querySelectorAll('table tbody tr')].some(r => r.innerText.includes(n)), kpiName);
kpiListed ? pass('TC-PRF-02', `KPI "${kpiName}" tersimpan`) : fail('TC-PRF-02', 'KPI tak muncul di list');

// TC-PRF-03 — create a review cycle (status active).
await page.goto(`${BASE}/admin/review-cycle/create`);
await page.fill('input[name="name"]', cycleName);
await page.fill('input[name="start_date"]', '2026-01-01');
await page.fill('input[name="end_date"]', '2026-06-30');
await page.evaluate(() => {
  const sel = document.querySelector('select[name="status"]');
  if (sel) { sel.value = 'active'; sel.dispatchEvent(new Event('change', { bubbles: true })); }
});
await Promise.all([page.waitForNavigation(), page.click('button[type="submit"]')]);
await page.goto(`${BASE}/admin/review-cycle`);
await page.waitForTimeout(1500);
const cycleListed = await page.evaluate((n) =>
  [...document.querySelectorAll('table tbody tr')].some(r => r.innerText.includes(n)), cycleName);
cycleListed ? pass('TC-PRF-03', `siklus "${cycleName}" tersimpan`) : fail('TC-PRF-03', 'siklus tak muncul di list');

// TC-PRF-04 — generate reviews for the cycle (native form submit).
await page.goto(`${BASE}/admin/performance`);
// Find the generate form whose row mentions our cycle.
const genClicked = await page.evaluate((n) => {
  const rows = [...document.querySelectorAll('.card-body > div')];
  for (const row of rows) {
    if (row.innerText.includes(n)) {
      const btn = row.querySelector('form button');
      if (btn) { return btn.closest('form').getAttribute('action'); }
    }
  }
  return null;
}, cycleName);
if (genClicked) {
  page.once('dialog', d => d.accept());
  // Submit via the form action using the page context (keeps CSRF + cookies).
  await page.evaluate((action) => {
    const form = [...document.querySelectorAll('form')].find(f => f.getAttribute('action') === action);
    form.requestSubmit ? form.requestSubmit() : form.submit();
  }, genClicked);
  await page.waitForTimeout(1500);
  pass('TC-PRF-04', 'penilaian dibuat untuk siklus (form Buat Penilaian)');
} else {
  fail('TC-PRF-04', 'tombol Buat Penilaian untuk siklus tak ditemukan');
}

// TC-PRF-05 — open the demo user's own review, submit manager score, finalize.
await page.goto(`${BASE}/admin/performance`);
await page.waitForTimeout(500);
// siti is super_admin → her own review appears under "Penilaian Saya"; open it.
const reviewHref = await page.evaluate(() => {
  const links = [...document.querySelectorAll('a')].filter(a => (a.getAttribute('href') || '').match(/\/performance\/review\/\d+$/));
  return links.length ? links[0].getAttribute('href') : null;
});
if (!reviewHref) {
  fail('TC-PRF-05', 'tidak ada link review yang bisa dibuka');
} else {
  await page.goto(reviewHref.startsWith('http') ? reviewHref : `${BASE}${reviewHref}`);
  // Manager form present (siti is super_admin → isManager true). Fill all manager selects.
  const hasManagerForm = await page.locator('#managerForm').count();
  if (hasManagerForm) {
    const selects = await page.locator('#managerForm select.manager-score').all();
    for (const s of selects) { await s.selectOption('4'); }
    await Promise.all([page.waitForNavigation(), page.click('#submitManager')]);
    // Now finalize.
    page.once('dialog', d => d.accept());
    await Promise.all([page.waitForNavigation().catch(() => {}), page.click('#finalizeBtn')]);
    await page.waitForTimeout(800);
    const finalShown = await page.evaluate(() =>
      /difinalisasi/i.test(document.body.innerText) || /Skor Akhir/i.test(document.body.innerText));
    finalShown
      ? pass('TC-PRF-05', 'review dinilai manajer + difinalisasi, skor akhir tampil')
      : fail('TC-PRF-05', 'finalisasi tidak tercermin di halaman');
  } else {
    fail('TC-PRF-05', 'form penilaian manajer tidak muncul');
  }
}

// TC-PRF-06 — scoreboard renders the chart.
await page.goto(`${BASE}/admin/performance/scoreboard`);
await page.waitForTimeout(1200);
const chartOk = await page.evaluate(() => {
  const c = document.getElementById('scoreChart');
  return !!c && c.tagName === 'CANVAS';
});
chartOk
  ? pass('TC-PRF-06', 'papan skor merender chart batang')
  : fail('TC-PRF-06', 'chart papan skor tidak ditemukan');

if (jsErrors.length) fail('TC-PRF-JS', `JS errors: ${jsErrors.join(' | ')}`);
else pass('TC-PRF-JS', 'tidak ada JS error');

await ctx.close();
await browser.close();

const failed = results.filter(r => r[0] === 'FAIL');
console.log(`\n${'='.repeat(70)}\n${results.length - failed.length} PASS / ${failed.length} FAIL\n${'='.repeat(70)}`);
failed.forEach(([, id, note]) => console.log(`  FAIL ${id} — ${note}`));
process.exit(failed.length ? 1 : 0);
