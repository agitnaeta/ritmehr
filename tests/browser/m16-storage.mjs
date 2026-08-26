// M16 — Pluggable storage (Fase 1): storage settings + conditional S3 fields + test.
// Run: node tests/browser/m16-storage.mjs   (php artisan serve on :8000)
//
// Covers:
//   TC-STG-60  storage tab has provider dropdown (local + s3)
//   TC-STG-61  S3 credential fields hidden when provider=local, shown when s3
//   TC-STG-62  "Tes Koneksi Penyimpanan" button present
//   TC-STG-63  test-connection (local default) returns success flash
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
  await page.waitForTimeout(500);
  // switch to Penyimpanan tab
  await page.evaluate(() => {
    const t = [...document.querySelectorAll('button[data-bs-toggle="tab"]')].find(b => /Penyimpanan/i.test(b.textContent));
    if (t) t.click();
  });
  await page.waitForTimeout(400);

  // TC-STG-60 — provider dropdown
  const opts = await page.evaluate(() => {
    const sel = document.getElementById('fld-storage_provider');
    return sel ? [...sel.options].map(o => o.value) : null;
  });
  (opts && opts.includes('local') && opts.includes('s3') && opts.includes('google') && opts.includes('webdav'))
    ? pass('TC-STG-60', `dropdown provider ada (${opts.join(', ')})`)
    : fail('TC-STG-60', `opsi provider: ${JSON.stringify(opts)}`);

  // TC-STG-61 — conditional fields
  const hiddenWhenLocal = await page.evaluate(() => {
    const sel = document.getElementById('fld-storage_provider');
    sel.value = 'local'; sel.dispatchEvent(new Event('change'));
    const row = document.querySelector('[data-setting-row="storage_s3_key"]');
    return row ? getComputedStyle(row).display === 'none' : false;
  });
  const shownWhenS3 = await page.evaluate(() => {
    const sel = document.getElementById('fld-storage_provider');
    sel.value = 's3'; sel.dispatchEvent(new Event('change'));
    const row = document.querySelector('[data-setting-row="storage_s3_key"]');
    return row ? getComputedStyle(row).display !== 'none' : false;
  });
  (hiddenWhenLocal && shownWhenS3)
    ? pass('TC-STG-61', 'field S3 kondisional: sembunyi saat local, muncul saat s3')
    : fail('TC-STG-61', `hiddenLocal=${hiddenWhenLocal}, shownS3=${shownWhenS3}`);

  // TC-STG-62 — test button
  const hasTestBtn = await page.evaluate(() => !!document.getElementById('btnTestStorage'));
  hasTestBtn ? pass('TC-STG-62', 'tombol "Tes Koneksi Penyimpanan" ada')
            : fail('TC-STG-62', 'tombol tes tidak ada');

  // TC-STG-63 — run test connection (provider is local by default in DB → success)
  if (hasTestBtn) {
    await Promise.all([
      page.waitForNavigation(),
      page.click('#btnTestStorage'),
    ]);
    await page.waitForTimeout(400);
    const body = await page.evaluate(() => document.body.innerText);
    /Koneksi penyimpanan OK|Penyimpanan lokal aktif/i.test(body)
      ? pass('TC-STG-63', 'tes koneksi (local) menghasilkan flash sukses')
      : fail('TC-STG-63', 'tidak ada flash sukses tes koneksi');
  } else {
    fail('TC-STG-63', 'skip — tombol tidak ada');
  }

  // TC-STG-64 — Google Drive fields conditional
  await page.goto(`${BASE}/admin/settings`);
  await page.waitForTimeout(400);
  await page.evaluate(() => {
    const t = [...document.querySelectorAll('button[data-bs-toggle="tab"]')].find(b => /Penyimpanan/i.test(b.textContent));
    if (t) t.click();
  });
  await page.waitForTimeout(300);
  const gdriveConditional = await page.evaluate(() => {
    const sel = document.getElementById('fld-storage_provider');
    // local → gdrive hidden, s3 fields hidden
    sel.value = 'local'; sel.dispatchEvent(new Event('change'));
    const gRowLocal = document.querySelector('[data-setting-row="storage_gdrive_refresh_token"]');
    const hiddenLocal = gRowLocal ? getComputedStyle(gRowLocal).display === 'none' : false;
    // google → gdrive shown, s3 hidden
    sel.value = 'google'; sel.dispatchEvent(new Event('change'));
    const gRow = document.querySelector('[data-setting-row="storage_gdrive_refresh_token"]');
    const s3Row = document.querySelector('[data-setting-row="storage_s3_key"]');
    const shownG = gRow ? getComputedStyle(gRow).display !== 'none' : false;
    const hiddenS3 = s3Row ? getComputedStyle(s3Row).display === 'none' : false;
    return hiddenLocal && shownG && hiddenS3;
  });
  gdriveConditional
    ? pass('TC-STG-64', 'field Google Drive muncul saat provider=google, tersembunyi saat lain')
    : fail('TC-STG-64', 'field Google Drive tidak kondisional dengan benar');

  // TC-STG-65 — WebDAV (Nextcloud) fields conditional
  const webdavConditional = await page.evaluate(() => {
    const sel = document.getElementById('fld-storage_provider');
    sel.value = 'webdav'; sel.dispatchEvent(new Event('change'));
    const wRow = document.querySelector('[data-setting-row="storage_webdav_base_uri"]');
    const gRow = document.querySelector('[data-setting-row="storage_gdrive_refresh_token"]');
    const shownW = wRow ? getComputedStyle(wRow).display !== 'none' : false;
    const hiddenG = gRow ? getComputedStyle(gRow).display === 'none' : false;
    return shownW && hiddenG;
  });
  webdavConditional
    ? pass('TC-STG-65', 'field Nextcloud/WebDAV muncul saat provider=webdav, provider lain tersembunyi')
    : fail('TC-STG-65', 'field WebDAV tidak kondisional dengan benar');

  await ctx.close();
}
await browser.close();

const failed = results.filter(r => r[0] === 'FAIL');
console.log(`\n${'='.repeat(70)}\n${results.length - failed.length} PASS / ${failed.length} FAIL\n${'='.repeat(70)}`);
failed.forEach(([, id, note]) => console.log(`  FAIL ${id} — ${note}`));
process.exit(failed.length ? 1 : 0);
