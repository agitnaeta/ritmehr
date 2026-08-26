// M02 — Leave calendar polish (browser).
// Run: node tests/browser/m02-leave-calendar.mjs   (php artisan serve on :8000)
//
// Covers:
//   TC-LV-20  calendar loads with month grid
//   TC-LV-21  color legend of leave types is shown
//   TC-LV-22  department filter present
//   TC-LV-23  an approved leave chip renders with a background color
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

  // Navigate to the month that has the demo leave (current month).
  await page.goto(`${BASE}/admin/leave-calendar`);
  await page.waitForTimeout(700);

  // TC-LV-20 — grid present (7 day-of-week headers)
  const headers = await page.evaluate(() =>
    [...document.querySelectorAll('thead th')].map(th => th.innerText.trim()).filter(Boolean).length);
  headers === 7
    ? pass('TC-LV-20', 'grid kalender termuat (7 kolom hari)')
    : fail('TC-LV-20', `header hari = ${headers}`);

  // TC-LV-21 — legend
  const legend = await page.evaluate(() => document.body.innerText.includes('Keterangan:'));
  const legendBadges = await page.evaluate(() =>
    [...document.querySelectorAll('.card-body.border-bottom .badge')].length);
  (legend && legendBadges > 0)
    ? pass('TC-LV-21', `legend jenis cuti tampil (${legendBadges} badge)`)
    : fail('TC-LV-21', `legend=${legend}, badges=${legendBadges}`);

  // TC-LV-22 — department filter
  const hasDeptFilter = await page.evaluate(() => !!document.querySelector('select[name="department_id"]'));
  hasDeptFilter ? pass('TC-LV-22', 'filter departemen tersedia')
               : fail('TC-LV-22', 'filter departemen tidak ada');

  // TC-LV-23 — a colored leave chip inside the grid
  const chip = await page.evaluate(() => {
    const cells = [...document.querySelectorAll('tbody td')];
    for (const c of cells) {
      const el = c.querySelector('div[style*="background"]');
      if (el) {
        const bg = getComputedStyle(el).backgroundColor;
        // non-transparent, non-white
        if (bg && bg !== 'rgba(0, 0, 0, 0)' && bg !== 'rgb(255, 255, 255)') {
          return { text: el.innerText.trim().slice(0, 30), bg };
        }
      }
    }
    return null;
  });
  chip
    ? pass('TC-LV-23', `chip cuti berwarna tampil ("${chip.text}", ${chip.bg})`)
    : fail('TC-LV-23', 'tidak ada chip cuti berwarna di grid (cek data approved bulan ini)');

  await ctx.close();
}
await browser.close();

const failed = results.filter(r => r[0] === 'FAIL');
console.log(`\n${'='.repeat(70)}\n${results.length - failed.length} PASS / ${failed.length} FAIL\n${'='.repeat(70)}`);
failed.forEach(([, id, note]) => console.log(`  FAIL ${id} — ${note}`));
process.exit(failed.length ? 1 : 0);
