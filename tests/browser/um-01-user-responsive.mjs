// tests/browser/um-01-user-responsive.mjs
// UM-01: Tabel /admin/user responsif — kolom inti tampil di desktop tanpa
// collapse ⋮, dan mobile tidak menimbulkan horizontal-scroll pada <body>.

import { launch, session, BASE } from './lib.mjs';

const results = [];
async function test(name, fn) {
    try { await fn(); results.push({ name, pass: true }); console.log(`  ✅ ${name}`); }
    catch (e) { results.push({ name, pass: false, err: e.message }); console.log(`  ❌ ${name}: ${e.message}`); }
}
function assert(cond, msg) { if (!cond) throw new Error(msg); }

const browser = await launch();
const { page } = await session(browser, 'siti@demo.test');

const pageErrors = [];
page.on('pageerror', e => pageErrors.push(e.message));

// TC1: Desktop 1280 — header tabel tampil ≥5 kolom inti, tanpa collapse
await test('Desktop: 5 kolom inti tampil, tanpa collapse ⋮', async () => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await page.goto(`${BASE}/admin/user`);
    await page.waitForSelector('#crudTable tbody tr', { timeout: 8000 });

    const headers = await page.$$eval('#crudTable thead th',
        ths => ths.filter(th => getComputedStyle(th).display !== 'none').map(th => th.textContent.trim()));

    for (const kolom of ['Nama', 'Email', 'Karyawan', 'Departemen', 'Status']) {
        assert(headers.some(h => h.includes(kolom)), `Kolom "${kolom}" tidak tampil. Header: ${JSON.stringify(headers)}`);
    }

    // Tidak ada kontrol collapse responsive
    const hasCollapse = await page.$('.dtr-control, td.dtr-control');
    assert(!hasCollapse, 'Masih ada kontrol collapse ⋮ (dtr-control)');
});

// TC2: Kolom sekunder TIDAK tampil di tabel (sudah dipindah ke detail/export)
await test('Kolom sekunder (Bahasa/Jadwal/Cabang/Jabatan) tidak di tabel', async () => {
    const headers = await page.$$eval('#crudTable thead th',
        ths => ths.filter(th => getComputedStyle(th).display !== 'none').map(th => th.textContent.trim()));
    for (const kolom of ['Bahasa', 'Jadwal', 'Jabatan', 'Cabang', 'Tgl Bergabung']) {
        assert(!headers.some(h => h === kolom), `Kolom sekunder "${kolom}" masih tampil di tabel`);
    }
});

// TC3: Mobile 390 — tidak ada horizontal scroll pada <body> (tabel boleh scroll internal)
await test('Mobile 390: body tidak horizontal-overflow', async () => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto(`${BASE}/admin/user`);
    await page.waitForSelector('#crudTable tbody tr', { timeout: 8000 });

    const overflow = await page.evaluate(() => ({
        bodyScroll: document.body.scrollWidth,
        clientW: document.documentElement.clientWidth,
    }));
    // toleransi 2px utk rounding
    assert(overflow.bodyScroll <= overflow.clientW + 2,
        `Body overflow di mobile: scrollWidth=${overflow.bodyScroll} clientWidth=${overflow.clientW}`);
});

// TC4: Nol JS pageerror
await test('Nol JavaScript pageerror', async () => {
    assert(pageErrors.length === 0, `Ada pageerror: ${JSON.stringify(pageErrors)}`);
});

await browser.close();

const pass = results.filter(r => r.pass).length;
const fail = results.filter(r => !r.pass).length;
console.log(`\n${pass} pass / ${fail} fail`);
process.exit(fail > 0 ? 1 : 0);
