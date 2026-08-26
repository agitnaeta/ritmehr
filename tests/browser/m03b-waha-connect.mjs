// M03b — In-app WhatsApp connection page (browser).
// Run: node tests/browser/m03b-waha-connect.mjs   (php artisan serve on :8000)
//
// Covers:
//   TC-WAC-40  page loads with status card + action buttons
//   TC-WAC-41  status polling runs and degrades gracefully when WAHA unreachable
//   TC-WAC-42  "Sambungkan" button present; QR area hidden until scanning
//   TC-WAC-43  menu item "Koneksi WhatsApp" present for super admin
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

  await page.goto(`${BASE}/admin/whatsapp`);
  await page.waitForTimeout(600);

  // TC-WAC-40 — status card + buttons
  const els = await page.evaluate(() => ({
    statusBox: !!document.getElementById('waStatusBox'),
    connect: !!document.getElementById('btnConnect'),
    refresh: !!document.getElementById('btnRefresh'),
  }));
  (els.statusBox && els.connect && els.refresh)
    ? pass('TC-WAC-40', 'kartu status + tombol Sambungkan/Segarkan tampil')
    : fail('TC-WAC-40', JSON.stringify(els));

  // TC-WAC-41 — polling updates the label (WAHA dummy is unreachable → graceful)
  await page.waitForTimeout(1500);
  const label = await page.evaluate(() => document.getElementById('waStateLabel')?.innerText || '');
  (label && label !== 'Memeriksa…')
    ? pass('TC-WAC-41', `status ter-update via polling ("${label}")`)
    : fail('TC-WAC-41', `label masih "${label}" (polling tidak jalan?)`);

  // TC-WAC-42 — QR area hidden while not scanning
  const qrHidden = await page.evaluate(() => {
    const el = document.getElementById('waQrArea');
    return el ? getComputedStyle(el).display === 'none' : false;
  });
  qrHidden ? pass('TC-WAC-42', 'area QR tersembunyi saat belum mode scan')
           : fail('TC-WAC-42', 'area QR mestinya tersembunyi');

  // TC-WAC-43 — menu item present
  const hasMenu = await page.evaluate(() =>
    [...document.querySelectorAll('a')].some(a => /Koneksi WhatsApp/i.test(a.textContent)));
  hasMenu ? pass('TC-WAC-43', 'menu "Koneksi WhatsApp" tampil')
          : fail('TC-WAC-43', 'menu tidak ada');

  await ctx.close();
}

// Guard: non-super-admin blocked from status endpoint
{
  const ctx = await browser.newContext();
  const page = await ctx.newPage();
  await login(page, 'budi@demo.test'); // manager
  const res = await page.goto(`${BASE}/admin/whatsapp/status`);
  (res.status() === 403)
    ? pass('TC-WAC-44', 'manager diblokir dari endpoint status (403)')
    : fail('TC-WAC-44', `status HTTP ${res.status()} (harusnya 403)`);
  await ctx.close();
}

await browser.close();

const failed = results.filter(r => r[0] === 'FAIL');
console.log(`\n${'='.repeat(70)}\n${results.length - failed.length} PASS / ${failed.length} FAIL\n${'='.repeat(70)}`);
failed.forEach(([, id, note]) => console.log(`  FAIL ${id} — ${note}`));
process.exit(failed.length ? 1 : 0);
