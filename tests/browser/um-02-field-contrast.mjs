// UM-02 — Kontras kolom isian (.form-control/.form-select) di bilah filter.
// Run: node tests/browser/um-02-field-contrast.mjs   (php artisan serve on :8000)
//
// Covers:
//   TC-UM-02a  field filter berlatar putih (surface), BEDA dari bilah bernada
//   TC-UM-02b  field filter punya border tegas (bukan transparent/0px)
//   TC-UM-02c  select jumlah-baris (length) juga kontras
//   TC-UM-02d  nol pageerror saat memuat halaman
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
  const jsErrors = [];
  page.on('pageerror', e => jsErrors.push(e.message));

  await login(page, 'siti@demo.test'); // super_admin
  await page.goto(`${BASE}/admin/user`);
  await page.waitForTimeout(1500);

  const m = await page.evaluate(() => {
    const field = document.querySelector('form:has(.la-filter) .form-select')
      || document.querySelector('.crud-filters-form .form-select');
    const bar = document.querySelector('form:has(.la-filter)');
    const lengthSel = document.querySelector('.dataTables_length select, #crudTable_length select');
    const cs = el => el ? getComputedStyle(el) : null;
    const f = cs(field), b = cs(bar), l = cs(lengthSel);
    return {
      field_bg: f?.backgroundColor,
      field_border_color: f?.borderTopColor,
      field_border_w: f?.borderTopWidth,
      bar_bg: b?.backgroundColor,
      length_bg: l?.backgroundColor,
      length_border_w: l?.borderTopWidth,
    };
  });

  // TC-UM-02a — field berlatar putih & beda dari bar
  const white = 'rgb(255, 255, 255)';
  (m.field_bg === white && m.field_bg !== m.bar_bg)
    ? pass('TC-UM-02a', `field ${m.field_bg} beda dari bar ${m.bar_bg}`)
    : fail('TC-UM-02a', `field ${m.field_bg} vs bar ${m.bar_bg}`);

  // TC-UM-02b — border tegas (>0px, bukan transparent)
  const w = parseFloat(m.field_border_w || '0');
  (w >= 1 && m.field_border_color && m.field_border_color !== 'rgba(0, 0, 0, 0)')
    ? pass('TC-UM-02b', `border ${m.field_border_w} ${m.field_border_color}`)
    : fail('TC-UM-02b', `border ${m.field_border_w} ${m.field_border_color}`);

  // TC-UM-02c — length select kontras
  (m.length_bg === white && parseFloat(m.length_border_w || '0') >= 1)
    ? pass('TC-UM-02c', `length select ${m.length_bg} border ${m.length_border_w}`)
    : fail('TC-UM-02c', `length select ${m.length_bg} border ${m.length_border_w}`);

  // TC-UM-02d — nol JS error
  jsErrors.length === 0
    ? pass('TC-UM-02d', 'nol pageerror')
    : fail('TC-UM-02d', `errors: ${jsErrors.join(' | ')}`);

  await ctx.close();
}
await browser.close();

const failed = results.filter(r => r[0] === 'FAIL');
console.log(`\n${results.length - failed.length} PASS / ${failed.length} FAIL`);
process.exit(failed.length ? 1 : 0);
