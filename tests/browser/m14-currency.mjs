// M14 — Multi-currency: UI follows the platform currency setting (browser).
// Run: node tests/browser/m14-currency.mjs   (php artisan serve on :8000)
//
// Covers:
//   TC-CUR-70  default IDR → accounting figures show "Rp"
//   TC-CUR-71  set currency USD via Settings → trial balance shows "$" (no "Rp")
//   TC-CUR-72  transaction form live-total uses active symbol
//   TC-CUR-73  reset to IDR → "Rp" is back
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

// Set the platform currency via the Settings form (real UI, not tinker).
async function setCurrency(page, code) {
  await page.goto(`${BASE}/admin/settings`);
  await page.waitForTimeout(600);
  // The setting lives in the "Bahasa & Mata Uang" (lokalisasi) tab.
  await page.evaluate(() => {
    const tab = [...document.querySelectorAll('a,button')].find(el => /Bahasa|Mata Uang|Lokal/i.test(el.textContent));
    if (tab) tab.click();
  });
  await page.waitForTimeout(400);
  const sel = await page.$('select[name="default_currency"]');
  if (!sel) return false;
  await sel.selectOption(code);
  await page.evaluate(() => {
    const btn = [...document.querySelectorAll('button[type=submit], input[type=submit]')].find(b => /simpan|save/i.test(b.textContent || b.value));
    if (btn) btn.click();
  });
  await page.waitForTimeout(1200);
  return true;
}

const browser = await chromium.launch();
{
  const ctx = await browser.newContext();
  const page = await ctx.newPage();
  page.setDefaultTimeout(60000);
  await login(page, 'siti@demo.test');

  // TC-CUR-70 — default IDR on trial balance
  await page.goto(`${BASE}/admin/accounting/trial-balance`);
  await page.waitForTimeout(600);
  let body = await page.evaluate(() => document.body.innerText);
  /Rp/.test(body)
    ? pass('TC-CUR-70', 'default IDR: neraca saldo menampilkan "Rp"')
    : fail('TC-CUR-70', 'tidak menemukan "Rp" pada default');

  // TC-CUR-71 — switch to USD
  const ok = await setCurrency(page, 'USD');
  if (!ok) { fail('TC-CUR-71', 'dropdown default_currency tidak ditemukan di Settings'); }
  else {
    await page.goto(`${BASE}/admin/accounting/trial-balance`);
    await page.waitForTimeout(600);
    body = await page.evaluate(() => document.body.innerText);
    const hasUsd = body.includes('$');
    const noRp = !/Rp\s?\d/.test(body); // no "Rp" directly before a number
    (hasUsd && noRp)
      ? pass('TC-CUR-71', 'setelah set USD: figur akuntansi pakai "$" (tidak ada "Rp")')
      : fail('TC-CUR-71', `usd=${hasUsd}, tanpaRp=${noRp}`);
  }

  // TC-CUR-72 — advanced journal form live total uses active symbol ($)
  await page.goto(`${BASE}/admin/accounting/journal/create?kind=general`);
  await page.waitForTimeout(800);
  const totalTxt = await page.evaluate(() => {
    const el = document.getElementById('sumDebit');
    return el ? el.innerText : '';
  });
  totalTxt.includes('$')
    ? pass('TC-CUR-72', `form jurnal (lanjutan) total pakai simbol aktif ("${totalTxt.trim()}")`)
    : fail('TC-CUR-72', `total tidak pakai "$": "${totalTxt}"`);

  // TC-CUR-73 — reset to IDR
  await setCurrency(page, 'IDR');
  await page.goto(`${BASE}/admin/accounting/trial-balance`);
  await page.waitForTimeout(600);
  body = await page.evaluate(() => document.body.innerText);
  (/Rp/.test(body) && !body.includes('$'))
    ? pass('TC-CUR-73', 'reset ke IDR: "Rp" kembali, tidak ada "$"')
    : fail('TC-CUR-73', `rp=${/Rp/.test(body)}, adaDollar=${body.includes('$')}`);

  await ctx.close();
}
await browser.close();

const failed = results.filter(r => r[0] === 'FAIL');
console.log(`\n${'='.repeat(70)}\n${results.length - failed.length} PASS / ${failed.length} FAIL\n${'='.repeat(70)}`);
failed.forEach(([, id, note]) => console.log(`  FAIL ${id} — ${note}`));
process.exit(failed.length ? 1 : 0);
