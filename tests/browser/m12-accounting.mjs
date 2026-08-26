// M12 — Internal accounting ledger: browser-driven verification.
// Run: node tests/browser/m12-accounting.mjs   (needs `php artisan serve` on :8000)
//
// Covers:
//   TC-ACC-30  super_admin sees "Akuntansi" menu + Daftar Akun loads with accounts
//   TC-ACC-31  Jurnal page renders posted salary entries
//   TC-ACC-32  Buku Besar (general ledger) shows movement + running balance
//   TC-ACC-33  Neraca Saldo (trial balance) is balanced (debit == credit)
//   TC-ACC-34  manager (no accounting.view) is blocked from /admin/accounting/journal
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

async function rowCount(page, url) {
  await page.goto(url);
  try {
    await page.waitForFunction(
      () => document.querySelectorAll('#crudTable tbody tr').length > 0, { timeout: 20000 });
  } catch { return -1; }
  return page.evaluate(() => {
    const rows = [...document.querySelectorAll('#crudTable tbody tr')];
    if (rows.length === 1 && /no data|tidak ada|empty/i.test(rows[0].innerText)) return 0;
    return rows.length;
  });
}

const browser = await chromium.launch();

// ---------------------------------------------------------- super_admin flow
{
  const ctx = await browser.newContext();
  const page = await ctx.newPage();
  page.setDefaultTimeout(60000);
  await login(page, 'siti@demo.test');

  // TC-ACC-30 — menu + chart of accounts (single source of account mgmt).
  await page.goto(`${BASE}/admin/dashboard`);
  const hasMenu = await page.evaluate(() =>
    [...document.querySelectorAll('a')].some(a => /Buku Besar|Daftar Akun/i.test(a.innerText)));
  // The old separate "Pemetaan Transaksi"/"Konfigurasi Akuntansi" screen must be gone.
  const noOldMapping = await page.evaluate(() =>
    ![...document.querySelectorAll('a')].some(a => /Pemetaan Transaksi|Konfigurasi Akuntansi/i.test(a.innerText)));
  const accounts = await rowCount(page, `${BASE}/admin/account`);
  (hasMenu && noOldMapping && accounts >= 3)
    ? pass('TC-ACC-30', `menu Akuntansi tampil, mapping lama hilang, ${accounts} akun terdaftar`)
    : fail('TC-ACC-30', `menu=${hasMenu}, mappingGone=${noOldMapping}, akun=${accounts}`);

  // TC-ACC-35 — account list shows the posting role column (role-based, no mapping table).
  await page.goto(`${BASE}/admin/account`);
  await page.waitForTimeout(2500);
  const hasRole = await page.evaluate(() => /Beban Gaji|Kas \/ Bank|Piutang/i.test(document.body.innerText) && /Peran Posting/i.test(document.body.innerText));
  hasRole
    ? pass('TC-ACC-35', 'kolom Peran Posting tampil di Daftar Akun')
    : fail('TC-ACC-35', 'kolom/nilai Peran Posting tidak tampil');

  // TC-ACC-31 — journal shows posted entries
  const jr = await page.goto(`${BASE}/admin/accounting/journal`);
  const journalRows = await page.evaluate(() =>
    document.querySelectorAll('#journalTable tbody tr').length);
  const hasGajian = await page.evaluate(() => /GAJIAN/i.test(document.body.innerText));
  (jr.status() === 200 && journalRows > 0 && hasGajian)
    ? pass('TC-ACC-31', `jurnal termuat dengan ${journalRows} baris (ada entri GAJIAN)`)
    : fail('TC-ACC-31', `HTTP ${jr.status()}, baris=${journalRows}, gajian=${hasGajian}`);

  // TC-ACC-32 — general ledger has movement + balance column
  const lr = await page.goto(`${BASE}/admin/accounting/ledger?account_id=1`);
  const ledgerRows = await page.evaluate(() =>
    document.querySelectorAll('#ledgerTable tbody tr').length);
  const hasSaldo = await page.evaluate(() => /Saldo Akhir/i.test(document.body.innerText));
  (lr.status() === 200 && ledgerRows > 0 && hasSaldo)
    ? pass('TC-ACC-32', `buku besar termuat: ${ledgerRows} mutasi + saldo akhir`)
    : fail('TC-ACC-32', `HTTP ${lr.status()}, mutasi=${ledgerRows}, saldo=${hasSaldo}`);

  // TC-ACC-33 — trial balance is balanced
  const tb = await page.goto(`${BASE}/admin/accounting/trial-balance`);
  const balanced = await page.evaluate(() => {
    const badge = document.getElementById('balanceBadge');
    return badge ? /SEIMBANG/i.test(badge.innerText) && !/TIDAK/i.test(badge.innerText) : false;
  });
  const totals = await page.evaluate(() => ({
    d: document.getElementById('totalDebit')?.innerText,
    c: document.getElementById('totalCredit')?.innerText,
  }));
  (tb.status() === 200 && balanced)
    ? pass('TC-ACC-33', `neraca saldo SEIMBANG (debit=${totals.d}, kredit=${totals.c})`)
    : fail('TC-ACC-33', `HTTP ${tb.status()}, balanced=${balanced}, totals=${JSON.stringify(totals)}`);

  await ctx.close();
}

// ------------------------------------------------------- non-super-admin block
{
  const ctx = await browser.newContext();
  const page = await ctx.newPage();
  await login(page, 'budi@demo.test'); // manager, no accounting.view
  const resp = await page.goto(`${BASE}/admin/accounting/journal`);
  const status = resp.status();
  const url = page.url();
  (status === 403 || !url.includes('/accounting/journal'))
    ? pass('TC-ACC-34', `manager diblokir dari jurnal (HTTP ${status})`)
    : fail('TC-ACC-34', `manager BISA membuka jurnal (HTTP ${status})`);
  await ctx.close();
}

await browser.close();

const failed = results.filter(r => r[0] === 'FAIL');
console.log(`\n${'='.repeat(70)}\n${results.length - failed.length} PASS / ${failed.length} FAIL\n${'='.repeat(70)}`);
failed.forEach(([, id, note]) => console.log(`  FAIL ${id} — ${note}`));
process.exit(failed.length ? 1 : 0);
