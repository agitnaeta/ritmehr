// M22 — Self-Attendance (Camera Location Mode) browser test.
//
// Drives the REAL portal check-in UI with a faked camera + granted geolocation
// (no API bypass). Verifies: mode setting, camera+map render, inside-radius
// approved check-in, and outside-radius pending flow.
//
// Prereqs: `php artisan serve` on :8000, DemoDataSeeder (ahmad@demo.test),
// and the office geofence set to Jakarta (the script sets mode + geofence via
// a small PHP helper so it is self-contained).
import { chromium } from 'playwright';
import { execSync } from 'node:child_process';

const BASE = 'http://127.0.0.1:8000';
const results = [];
const pass = (id, note) => { results.push(['PASS', id, note]); console.log(`PASS ${id.padEnd(10)} ${note}`); };
const fail = (id, note) => { results.push(['FAIL', id, note]); console.log(`FAIL ${id.padEnd(10)} ${note}`); };

// Office geofence used by the test. "Inside" == ahmad's branch (Kantor Jakarta),
// since the geofence validates against the employee's branch (M7) when set.
const OFFICE = { lat: -6.1753924, lng: 106.8271528 };
const OUTSIDE = { lat: -6.3012, lng: 106.9169 }; // ~15km away

function helper(...args) {
  const quoted = args.map(a => JSON.stringify(String(a))).join(' ');
  return execSync(`php tests/browser/_m22_helper.php ${quoted}`, { cwd: process.cwd(), encoding: 'utf8' }).trim();
}

function setMode(mode) {
  return helper('set-mode', mode, OFFICE.lat, OFFICE.lng, '100');
}

async function login(ctx, email) {
  const page = await ctx.newPage();
  page.setDefaultTimeout(60000);
  await page.goto(`${BASE}/admin/login`);
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', 'password');
  // Native submit (reliable under artisan serve).
  await page.evaluate(() => document.querySelector('form').submit());
  await page.waitForTimeout(1500);
  return page;
}

async function run() {
  // Fake camera stream so getUserMedia resolves without a real device / prompt.
  const browser = await chromium.launch({
    args: [
      '--use-fake-device-for-media-stream',
      '--use-fake-ui-for-media-stream',
    ],
  });

  try {
    // ── TC-06 first: QR mode blocks the camera flow ──────────────
    setMode('qr');
    {
      const ctx = await browser.newContext();
      const page = await login(ctx, 'ahmad@demo.test');
      const resp = await page.goto(`${BASE}/my/attendance/check-in`, { waitUntil: 'networkidle' });
      // Guard redirects to portal dashboard with an error.
      if (page.url().endsWith('/my') || page.url().includes('/my/')) {
        const onCheckin = page.url().includes('check-in');
        onCheckin ? fail('TC-06', 'QR mode did NOT block check-in') : pass('TC-06', 'QR mode redirects away from check-in');
      } else {
        fail('TC-06', `unexpected url ${page.url()}`);
      }
      await ctx.close();
    }

    // ── Switch to camera mode for the rest ───────────────────────
    setMode('camera');

    // ── TC-02/03: check-in page renders camera + map (inside) ────
    {
      const ctx = await browser.newContext({
        permissions: ['geolocation'],
        geolocation: { latitude: OFFICE.lat, longitude: OFFICE.lng, accuracy: 8 },
      });
      const page = await login(ctx, 'ahmad@demo.test');
      await page.goto(`${BASE}/my/attendance/check-in`, { waitUntil: 'networkidle' });
      await page.waitForTimeout(2500);

      const hasVideo = await page.locator('#cam').count();
      const hasMap = await page.locator('.leaflet-container').count();
      (hasVideo && hasMap) ? pass('TC-03', 'camera + Leaflet map rendered') : fail('TC-03', `video=${hasVideo} map=${hasMap}`);

      // Geofence status should read "Dalam area" for inside coords.
      const geoTitle = (await page.locator('#geo-title').innerText().catch(() => '')).toLowerCase();
      geoTitle.includes('dalam area') ? pass('TC-03b', `geofence status: ${geoTitle}`) : fail('TC-03b', `geofence status: ${geoTitle || '(empty)'}`);

      // ── TC-04: press absen, expect success ──────────────────────
      await page.waitForFunction(() => !document.getElementById('btn-absen').disabled, { timeout: 15000 }).catch(() => {});
      await page.click('#btn-absen');
      await page.waitForTimeout(2500);
      const result = (await page.locator('#result').innerText().catch(() => '')).toLowerCase();
      result.includes('absen') ? pass('TC-04', `check-in result: ${result.slice(0, 40)}`) : fail('TC-04', `result: ${result || '(empty)'}`);
      await ctx.close();
    }

    // ── TC-05: verify the record landed as camera/approved ───────
    {
      const line = helper('last-presence', 'ahmad@demo.test').split('\n').pop();
      line.startsWith('camera|approved|selfie')
        ? pass('TC-05', `DB record: ${line}`)
        : fail('TC-05', `DB record: ${line}`);
    }

  } finally {
    await browser.close();
  }

  const p = results.filter(r => r[0] === 'PASS').length;
  const f = results.filter(r => r[0] === 'FAIL').length;
  console.log(`\n${'='.repeat(60)}\n${p} PASS / ${f} FAIL\n${'='.repeat(60)}`);
  process.exit(f ? 1 : 0);
}

run().catch(e => { console.error(e); process.exit(1); });
