// RV5-01 — Audit header 2-baris GLOBAL lintas semua halaman list admin.
// READ-ONLY: tidak mengubah data. Hanya mengukur struktur header tiap list page
// dan menandai mana yang pecah (overflow horizontal, overlap search vs tombol,
// header kosong, dll). Jalankan setelah `php artisan serve` di :8000.
import { chromium } from 'playwright';

const BASE = 'http://127.0.0.1:8000';

// Slug list CRUD (backed by override list.blade.php). Custom pages (dashboard,
// accounting, report, recruitment, org-chart, settings, dst) TIDAK diikutkan —
// mereka bukan list Backpack, jadi header 2-baris tidak berlaku.
// CATATAN: training / notification / employee-document JUGA non-CRUD (Controller
// biasa + Blade custom) — mereka dijaga terpisah oleh rv5-01c-noncrud-header.mjs
// (RV5-01-C5), jadi TIDAK diikutkan di sini.
const SLUGS = [
  'user','leave-request', // sudah diverifikasi UM-03 (baseline)
  'account','applicant','approval','approval-flow','approval-flow-step',
  'audit-log','bpjs-rate','branch','company-profile','day','department',
  'document-type','employee-salary-allowance','interview',
  'job-opening','kpi','leave-balance','leave-type','loan','loan-payment',
  'national-holiday','permission','position','pph21-bracket',
  'presence','ptkp-rate','review-cycle','role','salary','salary-allowance-type',
  'salary-recap','schedule','schedule-day-off','tax-profile','ter-rate',
];

async function login(page) {
  await page.goto(`${BASE}/admin/login`);
  await page.fill('input[name="email"]', 'siti@demo.test');
  await page.fill('input[name="password"]', 'password');
  await Promise.all([page.waitForNavigation(), page.click('button[type="submit"]')]);
}

async function auditPage(page, slug) {
  const out = { slug, status: 'OK', issues: [], meta: {} };
  let resp;
  try {
    resp = await page.goto(`${BASE}/admin/${slug}`, { waitUntil: 'domcontentloaded' });
  } catch (e) {
    out.status = 'ERR'; out.issues.push(`goto gagal: ${e.message}`); return out;
  }
  const code = resp.status();
  out.meta.http = code;
  if (code !== 200) { out.status = code === 403 ? 'FORBID' : 'ERR'; out.issues.push(`HTTP ${code}`); return out; }

  // beri waktu AJAX crudTable + DataTables render header
  await page.waitForTimeout(1500);
  try {
    await page.waitForFunction(
      () => document.querySelectorAll('#crudTable tbody tr').length > 0
        || document.querySelector('.dataTables_empty'), { timeout: 15000 });
  } catch { /* biar tetap ukur header walau rows lambat */ }
  await page.waitForTimeout(500);

  const m = await page.evaluate(() => {
    const r = {};
    const docW = document.documentElement.clientWidth;
    r.htmlScrollW = document.documentElement.scrollWidth;
    r.clientW = docW;
    r.horizOverflow = document.documentElement.scrollWidth > docW + 2;

    // header custom kita (override) — cari elemen kunci
    const bc = document.querySelector('.breadcrumb, ol.breadcrumb, [bp-section*="breadcrumb"]');
    const h1 = document.querySelector('.um-page-header h1, header h1, .page-header h1, h1');
    const search = document.querySelector('#datatable_search_stack input, input[type="search"]');
    const btnStack = document.querySelector('[bp-section="page-header-actions"], .um-header-actions, .with-border .btn, a.btn[href$="/create"]');
    const dropdownBtn = document.querySelector('#userActionsDropdownBtn, .um-header-actions .dropdown-toggle');
    const infoStack = document.querySelector('#datatable_info_stack, .dataTables_info');

    r.hasBreadcrumb = !!bc;
    r.hasH1 = !!h1;
    r.h1text = h1 ? h1.innerText.trim().slice(0, 40) : null;
    r.hasSearch = !!search;
    r.hasCreateOrBtn = !!btnStack;
    r.hasDropdown = !!dropdownBtn;

    // overlap: search box vs tombol aksi header (bounding box intersection)
    function bb(el){ if(!el) return null; const b=el.getBoundingClientRect(); return {x:b.x,y:b.y,w:b.width,h:b.height,r:b.right,btm:b.bottom}; }
    const sb = bb(search), ab = bb(btnStack);
    r.searchBox = sb; r.btnBox = ab;
    if (sb && ab) {
      const overlap = !(sb.r <= ab.x || ab.r <= sb.x || sb.btm <= ab.y || ab.btm <= sb.y);
      r.searchBtnOverlap = overlap;
    } else r.searchBtnOverlap = null;

    // info stack display (harus inline-grid utk wrap yang benar — pitfall #27)
    if (infoStack) {
      const isStack = document.querySelector('#datatable_info_stack');
      r.infoDisplay = isStack ? getComputedStyle(isStack).display : '(no #datatable_info_stack)';
    } else r.infoDisplay = '(no info)';

    // rows
    const rows = document.querySelectorAll('#crudTable tbody tr').length;
    r.rows = rows;
    return r;
  });
  out.meta = { ...out.meta, ...m };

  // Evaluasi issue
  if (m.horizOverflow) out.issues.push(`HORIZ-OVERFLOW scrollW=${m.htmlScrollW}>${m.clientW}`);
  if (m.searchBtnOverlap === true) out.issues.push('OVERLAP search×tombol header');
  if (!m.hasH1) out.issues.push('tak ada H1/heading');
  if (!m.hasSearch) out.issues.push('tak ada search box');
  if (m.infoDisplay && !['inline-grid','(no info)','(no #datatable_info_stack)'].includes(m.infoDisplay))
    out.issues.push(`info display=${m.infoDisplay} (bukan inline-grid)`);

  if (out.issues.length) out.status = 'ISSUE';
  return out;
}

(async () => {
  const browser = await chromium.launch();
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
  const page = await ctx.newPage();
  page.setDefaultNavigationTimeout(60000);
  page.setDefaultTimeout(60000);
  await login(page);

  const report = [];
  for (const slug of SLUGS) {
    const r = await auditPage(page, slug);
    report.push(r);
    const tag = { OK:'OK  ', ISSUE:'ISSUE', FORBID:'FORB', ERR:'ERR ' }[r.status] || r.status;
    const iss = r.issues.length ? ' :: ' + r.issues.join(' | ') : '';
    console.log(`${tag}  ${slug.padEnd(26)} http=${r.meta.http} rows=${r.meta.rows ?? '-'} info=${r.meta.infoDisplay ?? '-'}${iss}`);
  }

  console.log('\n' + '='.repeat(80));
  const issues = report.filter(r => r.status === 'ISSUE');
  const forbid = report.filter(r => r.status === 'FORBID');
  const err = report.filter(r => r.status === 'ERR');
  console.log(`TOTAL ${report.length}  |  OK ${report.filter(r=>r.status==='OK').length}  |  ISSUE ${issues.length}  |  FORBID ${forbid.length}  |  ERR ${err.length}`);
  if (issues.length) {
    console.log('\n--- HALAMAN BERMASALAH ---');
    for (const r of issues) console.log(`  ${r.slug}: ${r.issues.join(' | ')}`);
  }
  if (err.length) { console.log('\n--- ERROR ---'); for (const r of err) console.log(`  ${r.slug}: ${r.issues.join(' | ')}`); }
  if (forbid.length) console.log(`\n(FORBID = butuh role lain / tak ada akses super_admin: ${forbid.map(r=>r.slug).join(', ')})`);

  // dump JSON utk dokumentasi
  const fs = await import('fs');
  fs.writeFileSync('tests/browser/rv5-01-report.json', JSON.stringify(report, null, 2));
  console.log('\nJSON: tests/browser/rv5-01-report.json');

  await browser.close();

  // RV5-01-A: jadikan guard permanen — exit non-zero kalau ada CRUD page pecah/error.
  const bad = issues.length + err.length;
  console.log('\n' + (bad === 0
    ? `RV5-01-A PASS — ${report.length} halaman list CRUD konsisten pola header 2-baris`
    : `RV5-01-A FAIL — ${bad} halaman bermasalah`));
  process.exit(bad === 0 ? 0 : 1);
})();
