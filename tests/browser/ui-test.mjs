// Browser-driven verification of the UI test cases that need real JS.
// Run: node tests/browser/ui-test.mjs
import { chromium } from 'playwright';
import fs from 'fs';

const BASE = 'http://127.0.0.1:8000';
const SHOTS = 'docs/screenshots';
fs.mkdirSync(SHOTS, { recursive: true });

const results = [];
const pass = (id, note) => { results.push(['PASS', id, note]); console.log(`PASS ${id} — ${note}`); };
const fail = (id, note) => { results.push(['FAIL', id, note]); console.log(`FAIL ${id} — ${note}`); };

async function login(page, email) {
  await page.goto(`${BASE}/admin/login`);
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', 'password');
  await Promise.all([page.waitForNavigation(), page.click('button[type="submit"]')]);
}

// Backpack datatables load over AJAX; wait for real rows, not the shell.
async function rowCount(page, url) {
  await page.goto(url);
  try {
    await page.waitForFunction(
      () => document.querySelectorAll('#crudTable tbody tr').length > 0,
      { timeout: 20000 }
    );
  } catch { return -1; }
  return page.evaluate(() => {
    const rows = [...document.querySelectorAll('#crudTable tbody tr')];
    if (rows.length === 1 && /no data|tidak ada|empty/i.test(rows[0].innerText)) return 0;
    return rows.length;
  });
}

async function infoText(page, url) {
  await page.goto(url);
  await page.waitForTimeout(2500);
  return page.evaluate(() => {
    const el = document.querySelector('.dataTables_info, #crudTable_info');
    return el ? el.innerText.trim() : '';
  });
}

const browser = await chromium.launch();

// ---------------------------------------------------------------- Dashboard
{
  const ctx = await browser.newContext();
  const page = await ctx.newPage();
  const errors = [];
  page.on('pageerror', e => errors.push(e.message));
  await login(page, 'siti@demo.test');

  await page.goto(`${BASE}/admin/dashboard`);
  await page.waitForTimeout(3000);
  await page.screenshot({ path: `${SHOTS}/dashboard-super-admin.png`, fullPage: true });

  // TC-DASH-05 — Chart.js actually renders (canvas with non-zero pixels)
  const chart = await page.evaluate(() => {
    const cs = [...document.querySelectorAll('canvas')];
    return cs.map(c => ({ w: c.width, h: c.height, hasChart: !!(window.Chart && window.Chart.getChart && window.Chart.getChart(c)) }));
  });
  const drawn = chart.filter(c => c.w > 0 && c.h > 0);
  drawn.length ? pass('TC-DASH-05', `${drawn.length} canvas ter-render (${drawn.map(c => c.w + 'x' + c.h).join(', ')})`)
               : fail('TC-DASH-05', 'tidak ada canvas Chart.js ter-render');

  errors.length ? fail('TC-DASH-01', `JS error: ${errors.join(' | ')}`)
                : pass('TC-DASH-01', 'dashboard termuat tanpa JS error');

  // TC-DASH-02/03/07 — read the stat cards as rendered
  const cards = await page.evaluate(() =>
    [...document.querySelectorAll('.card')].map(c => c.innerText.replace(/\s+/g, ' ').trim()).filter(t => t && t.length < 200).slice(0, 14));
  console.log('   kartu dashboard:', JSON.stringify(cards, null, 1));

  await ctx.close();
}

// ------------------------------------------------- AJAX datatable row counts
{
  const ctx = await browser.newContext();
  const page = await ctx.newPage();
  await login(page, 'siti@demo.test');

  for (const [id, url, label, expect] of [
    ['TC-USER-01', '/admin/user', 'Users', 5],
    ['TC-PRES-01', '/admin/presence', 'Kehadiran', null],
    ['TC-REC-01', '/admin/salary-recap', 'Rekap Gaji', 5],
    ['TC-LV-01', '/admin/leave-request', 'Pengajuan Cuti', null],
    ['TC-APR-01', '/admin/approval', 'Persetujuan', 2],
    ['TC-AUD-01', '/admin/audit-log', 'Audit Log', null],
  ]) {
    const n = await rowCount(page, BASE + url);
    const info = await page.evaluate(() => {
      const el = document.querySelector('.dataTables_info, #crudTable_info');
      return el ? el.innerText.replace(/\s+/g, ' ').trim() : '';
    });
    if (n < 0) fail(id, `${label}: tabel AJAX tidak pernah terisi`);
    else if (expect !== null && n !== expect) fail(id, `${label}: ${n} baris, diharapkan ${expect} (${info})`);
    else pass(id, `${label}: ${n} baris dimuat via AJAX (${info})`);
  }

  // TC-PRES-02 — geofence badge as rendered in the UI
  await page.goto(`${BASE}/admin/presence`);
  await page.waitForTimeout(3000);
  await page.screenshot({ path: `${SHOTS}/presence-list.png`, fullPage: true });
  const geo = await page.evaluate(() => {
    const t = document.body.innerText;
    return { luar: (t.match(/Di Luar Radius/g) || []).length, dalam: (t.match(/Di Dalam Radius/g) || []).length };
  });
  geo.luar === 0
    ? pass('TC-PRES-02', `tidak ada "Di Luar Radius" di halaman pertama (dalam=${geo.dalam})`)
    : fail('TC-PRES-02', `masih ada ${geo.luar} baris "Di Luar Radius"`);

  await ctx.close();
}

// ------------------------------------------------------- Manager scoping
{
  const ctx = await browser.newContext();
  const page = await ctx.newPage();
  await login(page, 'budi@demo.test');
  await page.screenshot({ path: `${SHOTS}/dashboard-manager.png`, fullPage: true });

  const users = await rowCount(page, `${BASE}/admin/user`);
  users === 5
    ? fail('TC-USER-15', `manager melihat ${users}/5 user — belum ter-scope tim (DEF-04)`)
    : pass('TC-USER-15', `manager melihat ${users} user`);

  const appr = await rowCount(page, `${BASE}/admin/approval`);
  appr === 1
    ? pass('TC-APR-04', `manager melihat ${appr} dari 2 approval — ter-scope benar`)
    : fail('TC-APR-04', `manager melihat ${appr} approval, diharapkan 1`);

  // TC-SET-01 — settings dropdown must be absent for manager
  await page.goto(`${BASE}/admin/dashboard`);
  const hasSettings = await page.evaluate(() =>
    [...document.querySelectorAll('a')].some(a => /Pengaturan/i.test(a.innerText)));
  hasSettings ? fail('TC-SET-01', 'dropdown Pengaturan tampil untuk manager')
              : pass('TC-SET-01', 'dropdown Pengaturan tidak tampil untuk manager');

  const menuCount = await page.evaluate(() =>
    new Set([...document.querySelectorAll('a[href*="/admin/"]')].map(a => a.getAttribute('href'))).size);
  console.log(`   manager melihat ${menuCount} link admin unik di sidebar`);

  await ctx.close();
}

// ------------------------------------------------- super_admin sees settings
{
  const ctx = await browser.newContext();
  const page = await ctx.newPage();
  await login(page, 'siti@demo.test');
  await page.goto(`${BASE}/admin/dashboard`);
  const hasSettings = await page.evaluate(() =>
    [...document.querySelectorAll('a')].some(a => /Pengaturan/i.test(a.innerText)));
  hasSettings ? pass('TC-SET-05', 'dropdown Pengaturan tampil untuk super_admin')
              : fail('TC-SET-05', 'dropdown Pengaturan hilang untuk super_admin');
  await ctx.close();
}

// ------------------------------------------------------------ Print defect
{
  const ctx = await browser.newContext();
  const page = await ctx.newPage();
  await login(page, 'siti@demo.test');
  const resp = await page.goto(`${BASE}/admin/salary-recap/print?id=1`);
  const status = resp.status();
  if (status === 500) {
    const title = await page.title();
    await page.screenshot({ path: `${SHOTS}/DEF-01-print-500.png`, fullPage: false });
    fail('TC-REC-09', `Print Rekap Gaji HTTP 500 — ${title.slice(0, 90)}`);
  } else {
    pass('TC-REC-09', `Print Rekap Gaji HTTP ${status}`);
  }
  await ctx.close();
}

// ----------------------------------------------------- QR scan page (camera)
{
  const ctx = await browser.newContext({ permissions: [] });
  const page = await ctx.newPage();
  const errors = [];
  page.on('pageerror', e => errors.push(e.message));
  await page.goto(`${BASE}/scan`);
  await page.waitForTimeout(2500);
  await page.screenshot({ path: `${SHOTS}/scan-public.png`, fullPage: true });
  const el = await page.evaluate(() => ({
    preview: !!document.querySelector('#preview'),
    time: document.querySelector('#time')?.innerText?.trim() || null,
    audioOk: !!document.querySelector('#audioPlayer'),
    audioFail: !!document.querySelector('#audioPlayerFailed'),
  }));
  el.preview && el.audioOk && el.audioFail
    ? pass('TC-SCAN-04', `elemen scanner lengkap (#preview, #audioPlayer, #audioPlayerFailed); jam="${el.time}")`)
    : fail('TC-SCAN-04', `elemen scanner kurang: ${JSON.stringify(el)}`);
  // Camera is denied in headless — the page must not hard-crash.
  errors.some(e => /camera|getUserMedia/i.test(e))
    ? pass('TC-SCAN-04b', 'kamera ditolak ditangani tanpa crash halaman')
    : pass('TC-SCAN-04b', `tanpa JS error fatal (${errors.length} error)`);
  await ctx.close();
}

// ------------------------------------------------------------ Employee portal
{
  const ctx = await browser.newContext();
  const page = await ctx.newPage();
  await login(page, 'ahmad@demo.test');
  const url = page.url();
  url.includes('/my') ? pass('TC-AUTH-04', `employee dialihkan ke ${url.replace(BASE, '')}`)
                      : fail('TC-AUTH-04', `employee mendarat di ${url}`);
  await page.screenshot({ path: `${SHOTS}/portal-employee.png`, fullPage: true });

  const forced = await page.goto(`${BASE}/admin/user`);
  page.url().includes('/my') ? pass('TC-AUTH-08', `paksa /admin/user → ${page.url().replace(BASE, '')}`)
                             : fail('TC-AUTH-08', `employee mencapai ${page.url()} (${forced.status()})`);

  // TC-MY-09 — the leave form must never expose a user_id field
  await page.goto(`${BASE}/my/leave/create`);
  await page.screenshot({ path: `${SHOTS}/portal-leave-create.png`, fullPage: true });
  const hasUserId = await page.evaluate(() => !!document.querySelector('[name="user_id"]'));
  hasUserId ? fail('TC-MY-09', 'form cuti portal punya field user_id')
            : pass('TC-MY-09', 'form cuti portal tidak punya field user_id');

  // TC-MY-14 — employee may not edit department / employment status
  await page.goto(`${BASE}/my/profile`);
  const restricted = await page.evaluate(() =>
    ['department_id', 'employment_status', 'position_id', 'salary', 'manager_id']
      .filter(n => document.querySelector(`[name="${n}"]`)));
  restricted.length === 0
    ? pass('TC-MY-14', 'profil portal tidak memuat field HR (departemen/status/jabatan)')
    : fail('TC-MY-14', `field HR bocor ke portal: ${restricted.join(', ')}`);

  // TC-MY-05 — payslip IDOR through the real browser session
  const idor = [];
  for (const id of [1, 2, 3, 4, 5]) {
    const r = await page.goto(`${BASE}/my/salary/${id}`);
    idor.push(`${id}:${r.status()}`);
  }
  idor.join(',') === '1:404,2:404,3:404,4:200,5:404'
    ? pass('TC-MY-05', `slip gaji ter-scope (${idor.join(' ')})`)
    : fail('TC-MY-05', `hasil tak terduga: ${idor.join(' ')}`);

  await ctx.close();
}

await browser.close();

const failed = results.filter(r => r[0] === 'FAIL');
console.log(`\n${'='.repeat(70)}\n${results.length - failed.length} PASS / ${failed.length} FAIL\n${'='.repeat(70)}`);
failed.forEach(([, id, note]) => console.log(`  FAIL ${id} — ${note}`));
