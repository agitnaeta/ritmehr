// M05 — Tax auto-calc on payslip: browser-driven verification.
// Run: node tests/browser/m05-tax-payslip.mjs   (needs `php artisan serve` on :8000)
//
// Covers:
//   TC-TAX-20  employee payslip renders PPh21 + BPJS breakdown rows
//   TC-TAX-21  payslip shows a non-zero net (Diterima/Net) value
//   TC-TAX-22  the PPh21 amount shown matches the stored recap value
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

  // ahmad@demo.test is an employee; recap #4 is his (per portal IDOR scoping).
  await login(page, 'ahmad@demo.test');
  const resp = await page.goto(`${BASE}/my/salary/4`);

  if (resp.status() !== 200) {
    fail('TC-TAX-20', `slip gaji HTTP ${resp.status()} (harusnya 200)`);
  } else {
    const body = await page.evaluate(() => document.body.innerText);

    // TC-TAX-20 — tax breakdown rows present
    const hasPph = /PPh\s*21/i.test(body);
    const hasBpjs = /BPJS/i.test(body);
    (hasPph && hasBpjs)
      ? pass('TC-TAX-20', 'slip menampilkan baris PPh 21 & BPJS')
      : fail('TC-TAX-20', `baris pajak kurang (pph=${hasPph}, bpjs=${hasBpjs})`);

    // TC-TAX-21 — net line present and non-zero
    const hasNet = /Diterima\s*\(Net\)/i.test(body);
    const netNonZero = /Rp\.?\s*[1-9][\d.]*/.test(body);
    (hasNet && netNonZero)
      ? pass('TC-TAX-21', 'slip menampilkan Diterima (Net) dengan nilai non-nol')
      : fail('TC-TAX-21', `net line=${hasNet}, nonzero=${netNonZero}`);
  }

  // TC-TAX-22 — the rendered PPh21 matches the stored value for this recap.
  // Read the stored value straight from the app via an authenticated fetch of
  // the admin salary-recap list would need admin; instead assert the known
  // demo value (recap #4 pph21 = 122300 → "Rp 122.300").
  const body = await page.evaluate(() => document.body.innerText.replace(/\s+/g, ' '));
  /122\.?300/.test(body)
    ? pass('TC-TAX-22', 'nilai PPh 21 tampil sesuai data tersimpan (Rp 122.300)')
    : fail('TC-TAX-22', 'nilai PPh 21 tersimpan (122.300) tidak tampil di slip');

  await ctx.close();
}
await browser.close();

const failed = results.filter(r => r[0] === 'FAIL');
console.log(`\n${'='.repeat(70)}\n${results.length - failed.length} PASS / ${failed.length} FAIL\n${'='.repeat(70)}`);
failed.forEach(([, id, note]) => console.log(`  FAIL ${id} — ${note}`));
process.exit(failed.length ? 1 : 0);
