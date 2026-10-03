// RV5-01-C5 — Regression guard: 3 halaman non-CRUD memakai pola header 2-baris.
// Assert struktur .um-* hadir, breadcrumb + actions di baris 1, tools di baris 2,
// tanpa overflow/overlap, dan ZERO btn-sm/form-control-sm di dalam header.
// Exit non-zero jika ada yang gagal (bisa dipakai sbagai CI gate).
import { chromium } from 'playwright';
const BASE = 'http://127.0.0.1:8000';

// slug → { expectActions: apakah halaman ini punya tombol aksi baris-1 saat login super_admin }
const PAGES = [
  { slug: 'training',          heading: 'Pelatihan',        expectActions: true,  expectTools: true },
  { slug: 'notification',      heading: 'Notifikasi',       expectActions: false, expectTools: true }, // Tandai Semua muncul hanya bila ada unread
  { slug: 'employee-document', heading: 'Dokumen Karyawan', expectActions: true,  expectTools: true },
];

async function login(page){
  await page.goto(`${BASE}/admin/login`);
  await page.fill('input[name="email"]','siti@demo.test');
  await page.fill('input[name="password"]','password');
  await Promise.all([page.waitForNavigation(), page.click('button[type="submit"]')]);
}

let failures = 0;
function assert(cond, msg){ if(!cond){ failures++; console.log(`  FAIL: ${msg}`); } }

(async () => {
  const browser = await chromium.launch();
  const ctx = await browser.newContext({ viewport:{ width:1280, height:900 } });
  const page = await ctx.newPage();
  page.setDefaultTimeout(60000); page.setDefaultNavigationTimeout(60000);
  await login(page);

  for (const p of PAGES) {
    await page.goto(`${BASE}/admin/${p.slug}`, { waitUntil:'domcontentloaded' });
    await page.waitForTimeout(800);
    const m = await page.evaluate(() => {
      const docW = document.documentElement.clientWidth;
      const bb = el => { if(!el) return null; const b=el.getBoundingClientRect(); return {x:b.x,y:b.y,r:b.right,btm:b.bottom}; };
      const actions = document.querySelector('.um-header-actions');
      const tools = document.querySelector('.um-header-tools');
      const ab = bb(actions), tb = bb(tools);
      let overlap = null;
      if (ab && tb) overlap = !(ab.r <= tb.x || tb.r <= ab.x || ab.btm <= tb.y || tb.btm <= ab.y);
      return {
        headerTop: !!document.querySelector('.um-page-header .um-header-top'),
        headerTitle: !!document.querySelector('.um-header-title'),
        breadcrumb: !!document.querySelector('.um-header-top .breadcrumb'),
        h1: document.querySelector('.um-header-title h1')?.innerText?.trim() || null,
        hasActions: !!actions,
        hasTools: !!tools,
        horizOverflow: document.documentElement.scrollWidth > docW + 2,
        overlap,
        btnSm: document.querySelectorAll('.um-page-header .btn-sm, .um-page-header .btn-group-sm').length,
        ctrlSm: document.querySelectorAll('.um-page-header .form-control-sm, .um-page-header .form-select-sm, .um-page-header .input-group-sm').length,
      };
    });
    console.log(`[${p.slug}] h1=${m.h1} bc=${m.breadcrumb} act=${m.hasActions} tools=${m.hasTools} overflow=${m.horizOverflow} overlap=${m.overlap} btnSm=${m.btnSm} ctrlSm=${m.ctrlSm}`);
    assert(m.headerTop, `${p.slug}: .um-header-top tidak ada`);
    assert(m.headerTitle, `${p.slug}: .um-header-title tidak ada`);
    assert(m.breadcrumb, `${p.slug}: breadcrumb tidak ada di baris 1`);
    assert(m.h1 === p.heading, `${p.slug}: heading '${m.h1}' != '${p.heading}'`);
    if (p.expectActions) assert(m.hasActions, `${p.slug}: .um-header-actions diharapkan ada`);
    if (p.expectTools)   assert(m.hasTools, `${p.slug}: .um-header-tools diharapkan ada`);
    assert(!m.horizOverflow, `${p.slug}: ada overflow horizontal`);
    assert(m.overlap !== true, `${p.slug}: actions overlap tools`);
    assert(m.btnSm === 0, `${p.slug}: masih ada ${m.btnSm} .btn-sm di header`);
    assert(m.ctrlSm === 0, `${p.slug}: masih ada ${m.ctrlSm} .form-*-sm di header`);
  }

  await browser.close();
  console.log('\n' + (failures === 0 ? 'RV5-01-C5 PASS — 3 halaman non-CRUD konsisten pola 2-baris' : `RV5-01-C5 FAIL — ${failures} assertion gagal`));
  process.exit(failures === 0 ? 0 : 1);
})();
