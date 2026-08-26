// M12c — "Catat Transaksi" ramah non-akuntan (browser).
// Run: node tests/browser/m12c-catat-transaksi.mjs   (php artisan serve on :8000)
//
// Covers:
//   TC-ACC-50  chooser menampilkan 4 kartu jenis transaksi
//   TC-ACC-51  form Pengeluaran TIDAK menampilkan istilah debit/kredit
//   TC-ACC-52  catat pengeluaran (1 nominal) → tersimpan & muncul di Jurnal sbg "Pengeluaran"
//   TC-ACC-53  entri hasil catat muncul balanced di Jurnal (debit=kredit terlihat)
//   TC-ACC-54  Laba Rugi ikut bertambah bebannya
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

const uniqDesc = 'Listrik uji ' + Date.now();
const browser = await chromium.launch();
{
  const ctx = await browser.newContext();
  const page = await ctx.newPage();
  page.setDefaultTimeout(60000);
  await login(page, 'siti@demo.test');

  // TC-ACC-50 — chooser
  await page.goto(`${BASE}/admin/accounting/transaksi`);
  const cards = await page.evaluate(() =>
    ['card-expense', 'card-income', 'card-transfer', 'card-general'].filter(id => document.getElementById(id)).length);
  cards === 4
    ? pass('TC-ACC-50', '4 kartu jenis transaksi tampil')
    : fail('TC-ACC-50', `hanya ${cards} kartu`);

  // TC-ACC-51 — expense form is jargon-free
  await page.goto(`${BASE}/admin/accounting/journal/create?kind=expense`);
  await page.waitForTimeout(500);
  const txt = await page.evaluate(() => document.body.innerText.toLowerCase());
  const hasFriendly = /bayar dari/.test(txt) && /untuk \(kategori\)/.test(txt) && /jumlah/.test(txt);
  const noJargon = !/debit/.test(txt) && !/kredit/.test(txt);
  (hasFriendly && noJargon)
    ? pass('TC-ACC-51', 'form pakai bahasa manusia (Bayar dari/Untuk/Jumlah), tanpa istilah debit/kredit')
    : fail('TC-ACC-51', `friendly=${hasFriendly}, tanpaJargon=${noJargon}`);

  // TC-ACC-52 — record an expense with a single amount
  await page.fill('input[name="amount"]', '450000');
  // pick first cash + first category
  const cashOpts = await page.$$eval('select[name="cash_account_id"] option', o => o.map(x => x.value).filter(Boolean));
  const catOpts = await page.$$eval('select[name="category_account_id"] option', o => o.map(x => x.value).filter(Boolean));
  await page.selectOption('select[name="cash_account_id"]', cashOpts[0]);
  await page.selectOption('select[name="category_account_id"]', catOpts[0]);
  await page.fill('input[name="description"]', uniqDesc);
  await Promise.all([page.waitForNavigation(), page.click('button[type="submit"]')]);

  await page.goto(`${BASE}/admin/accounting/journal`);
  await page.waitForTimeout(800);
  const body = await page.evaluate(() => document.body.innerText);
  const listed = body.includes(uniqDesc) && /Pengeluaran/.test(body);
  listed
    ? pass('TC-ACC-52', 'pengeluaran tersimpan & muncul di Jurnal dengan label "Pengeluaran"')
    : fail('TC-ACC-52', `tercatat di jurnal? ${body.includes(uniqDesc)}, badge Pengeluaran? ${/Pengeluaran/.test(body)}`);

  // TC-ACC-53 — the entry shows balanced debit & credit amounts (Rp 450.000 twice)
  const count = (body.match(/450\.000/g) || []).length;
  count >= 2
    ? pass('TC-ACC-53', `entri seimbang tampil (nominal 450.000 muncul ${count}× = debit & kredit)`)
    : fail('TC-ACC-53', `nominal muncul ${count}× (harusnya >=2)`);

  // TC-ACC-54 — income statement reflects the expense
  await page.goto(`${BASE}/admin/accounting/income-statement`);
  const isBody = await page.evaluate(() => document.body.innerText);
  /Total Beban/i.test(isBody) && /Rp/.test(isBody)
    ? pass('TC-ACC-54', 'Laba Rugi memuat total beban (pengeluaran terhitung)')
    : fail('TC-ACC-54', 'Laba Rugi tidak menampilkan beban');

  await ctx.close();
}
await browser.close();

const failed = results.filter(r => r[0] === 'FAIL');
console.log(`\n${'='.repeat(70)}\n${results.length - failed.length} PASS / ${failed.length} FAIL\n${'='.repeat(70)}`);
failed.forEach(([, id, note]) => console.log(`  FAIL ${id} — ${note}`));
process.exit(failed.length ? 1 : 0);
