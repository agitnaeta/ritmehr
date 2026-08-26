// M03 — WhatsApp via WAHA settings (browser).
// Run: node tests/browser/m03-waha.mjs   (php artisan serve on :8000)
//
// Covers:
//   TC-WA-30  WhatsApp enable toggle present (WAHA-only, no provider selector)
//   TC-WA-31  WAHA config fields present (url, session, api key)
//   TC-WA-32  "Kirim Tes" WhatsApp form present
//   TC-WA-33  test send in log mode returns an info flash (no crash)
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
  await page.goto(`${BASE}/admin/settings`);
  await page.waitForTimeout(600);

  // TC-WA-30 — WhatsApp enable toggle present; provider selector must be GONE.
  const waState = await page.evaluate(() => ({
    enable: !!document.querySelector('[name="whatsapp_enabled"]'),
    noProvider: !document.querySelector('select[name="whatsapp_provider"]'),
    noFonnte: !document.querySelector('[name="fonnte_token"]'),
  }));
  (waState.enable && waState.noProvider && waState.noFonnte)
    ? pass('TC-WA-30', 'toggle WA ada; selector provider & field Fonnte sudah dihapus')
    : fail('TC-WA-30', JSON.stringify(waState));

  // TC-WA-31 — WAHA fields
  const fields = await page.evaluate(() => ({
    url: !!document.querySelector('[name="waha_url"]'),
    session: !!document.querySelector('[name="waha_session"]'),
    key: !!document.querySelector('[name="waha_api_key"]'),
  }));
  (fields.url && fields.session && fields.key)
    ? pass('TC-WA-31', 'field WAHA (url, session, api key) lengkap')
    : fail('TC-WA-31', JSON.stringify(fields));

  // TC-WA-32 — test form
  const hasTestForm = await page.evaluate(() =>
    !!document.querySelector('form[action*="test-whatsapp"] input[name="phone"]'));
  hasTestForm ? pass('TC-WA-32', 'form "Kirim Tes" WhatsApp tersedia')
             : fail('TC-WA-32', 'form tes tidak ada');

  // TC-WA-33 — submit test in log mode (default demo: no waha_url → log)
  if (hasTestForm) {
    await page.fill('form[action*="test-whatsapp"] input[name="phone"]', '08123456789');
    await Promise.all([
      page.waitForNavigation(),
      page.click('form[action*="test-whatsapp"] button[type="submit"]'),
    ]);
    await page.waitForTimeout(400);
    const body = await page.evaluate(() => document.body.innerText);
    // Either a live success or a log-mode info — both are non-error outcomes.
    (/mode log/i.test(body) || /berhasil dikirim/i.test(body))
      ? pass('TC-WA-33', 'kirim tes menghasilkan notifikasi (mode log / sukses), tanpa crash')
      : fail('TC-WA-33', 'tidak ada flash hasil tes');
  } else {
    fail('TC-WA-33', 'skip — form tidak ada');
  }

  await ctx.close();
}
await browser.close();

const failed = results.filter(r => r[0] === 'FAIL');
console.log(`\n${'='.repeat(70)}\n${results.length - failed.length} PASS / ${failed.length} FAIL\n${'='.repeat(70)}`);
failed.forEach(([, id, note]) => console.log(`  FAIL ${id} — ${note}`));
process.exit(failed.length ? 1 : 0);
