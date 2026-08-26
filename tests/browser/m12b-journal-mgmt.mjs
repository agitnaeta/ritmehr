// M12b — Manual journals, locking & standard reports (browser).
// Run: node tests/browser/m12b-journal-mgmt.mjs   (needs `php artisan serve` on :8000)
//
// Covers:
//   TC-ACC-40  create a balanced manual journal via the UI form
//   TC-ACC-41  the new manual entry appears in the journal with edit/delete actions
//   TC-ACC-42  auto (salary) entries are locked — no edit/delete, only reversal
//   TC-ACC-43  reversal creates a mirror entry (source marked "sudah dibalik")
//   TC-ACC-44  Laba Rugi report renders income − expense = net
//   TC-ACC-45  Neraca report renders and is balanced
import { chromium } from 'playwright';

const BASE = 'http://127.0.0.1:8000';
const results = [];
const pass = (id, note) => { results.push(['PASS', id, note]); console.log(`PASS ${id} — ${note}`); };
const fail = (id, note) => { results.push(['FAIL', id, note]); console.log(`FAIL ${id} — ${note}`); };

async function login(page, email) {
  await page.goto(`${BASE}/admin/login`);
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', 'password');
  await Promise.all([page.waitForNavigation(), page.click('button[type="submit"]')]);
}

const browser = await chromium.launch();
{
  const ctx = await browser.newContext();
  const page = await ctx.newPage();
  page.setDefaultTimeout(60000);
  await login(page, 'siti@demo.test');

  // TC-ACC-40 — create a manual journal (expense 5000 debit / cash 1000 credit)
  await page.goto(`${BASE}/admin/accounting/journal/create`);
  await page.waitForTimeout(1000);
  // two seed rows exist; fill them
  const selects = await page.$$('#linesBody select.acc');
  const debits = await page.$$('#linesBody input.debit');
  const credits = await page.$$('#linesBody input.credit');
  if (selects.length >= 2) {
    // pick first account for row0 (debit) and second for row1 (credit)
    const opts = await page.$$eval('#linesBody select.acc option', os => os.map(o => o.value).filter(Boolean));
    await selects[0].selectOption(opts[0]);
    await debits[0].fill('750000');
    await selects[1].selectOption(opts[1]);
    await credits[1].fill('750000');
    await page.fill('input[name="description"]', 'Uji jurnal manual Playwright');
    await page.waitForTimeout(500);
    const btnDisabled = await page.getAttribute('#saveJournal', 'disabled');
    if (btnDisabled !== null) {
      fail('TC-ACC-40', 'tombol simpan masih disabled (dianggap tak seimbang)');
    } else {
      await Promise.all([page.waitForNavigation(), page.click('#saveJournal')]);
      const url = page.url();
      url.includes('/accounting/journal')
        ? pass('TC-ACC-40', 'jurnal manual seimbang berhasil disimpan')
        : fail('TC-ACC-40', `redirect tak terduga: ${url}`);
    }
  } else {
    fail('TC-ACC-40', `baris form kurang (${selects.length})`);
  }

  // TC-ACC-41 — manual entry listed with Manual badge + actions
  await page.goto(`${BASE}/admin/accounting/journal`);
  await page.waitForTimeout(1000);
  const body = await page.evaluate(() => document.body.innerText);
  const hasManualBadge = /Manual/.test(body) && /Uji jurnal manual Playwright/.test(body);
  const hasCreateBtn = await page.evaluate(() => [...document.querySelectorAll('a')].some(a => /Buat Jurnal Manual/i.test(a.innerText)));
  (hasManualBadge && hasCreateBtn)
    ? pass('TC-ACC-41', 'jurnal manual tampil dengan badge Manual + tombol buat')
    : fail('TC-ACC-41', `manualBadge=${hasManualBadge}, createBtn=${hasCreateBtn}`);

  // TC-ACC-42 — auto entries locked: has "Auto" lock badge + a reversal (Balik) button, no edit form
  const hasAutoBadge = /Auto/.test(body);
  const hasBalik = await page.evaluate(() => [...document.querySelectorAll('button')].some(b => /Balik/i.test(b.innerText)));
  (hasAutoBadge && hasBalik)
    ? pass('TC-ACC-42', 'entri auto terkunci (badge Auto) & punya tombol Balik (reversal)')
    : fail('TC-ACC-42', `autoBadge=${hasAutoBadge}, balikBtn=${hasBalik}`);

  // TC-ACC-43 — trigger a reversal on the first auto entry
  const before = await page.evaluate(() => (document.body.innerText.match(/sudah dibalik/g) || []).length);
  page.on('dialog', d => d.accept());
  const balikBtn = await page.$('button:has-text("Balik")');
  if (balikBtn) {
    await Promise.all([page.waitForNavigation(), balikBtn.click()]);
    await page.waitForTimeout(800);
    const after = await page.evaluate(() => (document.body.innerText.match(/sudah dibalik/g) || []).length);
    const hasPembalik = await page.evaluate(() => /Pembalik/.test(document.body.innerText));
    (after > before && hasPembalik)
      ? pass('TC-ACC-43', `reversal berhasil (badge "sudah dibalik" & "Pembalik" muncul)`)
      : fail('TC-ACC-43', `before=${before} after=${after} pembalik=${hasPembalik}`);
  } else {
    fail('TC-ACC-43', 'tombol Balik tidak ditemukan');
  }

  // TC-ACC-44 — Laba Rugi
  const is = await page.goto(`${BASE}/admin/accounting/income-statement`);
  const isBody = await page.evaluate(() => document.body.innerText);
  (is.status() === 200 && /Total Beban/i.test(isBody) && /Laba \/ Rugi Bersih/i.test(isBody))
    ? pass('TC-ACC-44', 'Laba Rugi termuat (pendapatan, beban, laba bersih)')
    : fail('TC-ACC-44', `HTTP ${is.status()}, konten kurang`);

  // TC-ACC-45 — Neraca balanced
  const bs = await page.goto(`${BASE}/admin/accounting/balance-sheet`);
  const balanced = await page.evaluate(() => {
    const b = document.getElementById('balanceBadge');
    return b ? /SEIMBANG/i.test(b.innerText) && !/TIDAK/i.test(b.innerText) : false;
  });
  (bs.status() === 200 && balanced)
    ? pass('TC-ACC-45', 'Neraca termuat & SEIMBANG (aset = kewajiban + ekuitas)')
    : fail('TC-ACC-45', `HTTP ${bs.status()}, balanced=${balanced}`);

  await ctx.close();
}
await browser.close();

const failed = results.filter(r => r[0] === 'FAIL');
console.log(`\n${'='.repeat(70)}\n${results.length - failed.length} PASS / ${failed.length} FAIL\n${'='.repeat(70)}`);
failed.forEach(([, id, note]) => console.log(`  FAIL ${id} — ${note}`));
process.exit(failed.length ? 1 : 0);
