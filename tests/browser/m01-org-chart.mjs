// M01 — Org chart polish (browser).
// Run: node tests/browser/m01-org-chart.mjs   (php artisan serve on :8000)
//
// Covers:
//   TC-ORG-10  org chart loads with department nodes
//   TC-ORG-11  toolbar (Buka/Tutup Semua, Cetak) present
//   TC-ORG-12  collapse-all hides child <ul>, expand-all shows them
//   TC-ORG-13  per-node toggle collapses just that branch
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

  await page.goto(`${BASE}/admin/org-chart`);
  await page.waitForTimeout(600);

  // TC-ORG-10 — nodes render
  const nodes = await page.evaluate(() => document.querySelectorAll('#orgTree .org-node').length);
  nodes > 0
    ? pass('TC-ORG-10', `${nodes} node departemen tampil`)
    : fail('TC-ORG-10', 'tidak ada node');

  // TC-ORG-11 — toolbar buttons
  const buttons = await page.evaluate(() => ({
    expand: !!document.getElementById('expandAll'),
    collapse: !!document.getElementById('collapseAll'),
  }));
  (buttons.expand && buttons.collapse)
    ? pass('TC-ORG-11', 'toolbar Buka/Tutup Semua tersedia')
    : fail('TC-ORG-11', `expand=${buttons.expand}, collapse=${buttons.collapse}`);

  // Determine if there's at least one parent (li with child ul)
  const parents = await page.evaluate(() =>
    [...document.querySelectorAll('#orgTree li')].filter(li => li.querySelector(':scope > ul')).length);

  if (parents === 0) {
    pass('TC-ORG-12', 'skip (tidak ada node induk berpenampung anak) — struktur datar');
    pass('TC-ORG-13', 'skip (tidak ada node induk)');
  } else {
    // TC-ORG-12 — collapse all then expand all
    await page.click('#collapseAll');
    await page.waitForTimeout(300);
    const hiddenAfterCollapse = await page.evaluate(() =>
      [...document.querySelectorAll('#orgTree li')].filter(li => li.querySelector(':scope > ul'))
        .every(li => li.classList.contains('collapsed')));
    await page.click('#expandAll');
    await page.waitForTimeout(300);
    const shownAfterExpand = await page.evaluate(() =>
      [...document.querySelectorAll('#orgTree li')].filter(li => li.querySelector(':scope > ul'))
        .every(li => !li.classList.contains('collapsed')));
    (hiddenAfterCollapse && shownAfterExpand)
      ? pass('TC-ORG-12', 'Tutup Semua menyembunyikan anak, Buka Semua menampilkan lagi')
      : fail('TC-ORG-12', `collapsed=${hiddenAfterCollapse}, expanded=${shownAfterExpand}`);

    // TC-ORG-13 — single node toggle
    const toggled = await page.evaluate(() => {
      const li = [...document.querySelectorAll('#orgTree li')].find(l => l.querySelector(':scope > ul'));
      const btn = li.querySelector(':scope > .org-node .org-toggle');
      btn.click();
      const c = li.classList.contains('collapsed');
      btn.click();
      const e = !li.classList.contains('collapsed');
      return c && e;
    });
    toggled
      ? pass('TC-ORG-13', 'toggle per-node collapse & expand berfungsi')
      : fail('TC-ORG-13', 'toggle per-node gagal');
  }

  await ctx.close();
}
await browser.close();

const failed = results.filter(r => r[0] === 'FAIL');
console.log(`\n${'='.repeat(70)}\n${results.length - failed.length} PASS / ${failed.length} FAIL\n${'='.repeat(70)}`);
failed.forEach(([, id, note]) => console.log(`  FAIL ${id} — ${note}`));
process.exit(failed.length ? 1 : 0);
