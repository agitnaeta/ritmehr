// tests/browser/um-06-user-show-tabs.mjs
// UM-06: Halaman Show karyawan bertab (Profil / Foto & QR / Riwayat Pelatihan).
// Bootstrap Tab di-trigger via bootstrap.Tab (Playwright click tak memicu event JS-nya).

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

await page.goto(`${BASE}/admin/user/2/show`);
await page.waitForSelector('.nav-tabs', { timeout: 8000 });

// TC1: 3 tab hadir
await test('3 tab hadir (Profil, Foto & QR, Riwayat Pelatihan)', async () => {
    const tabs = await page.$$eval('.nav-tabs .nav-link', els => els.map(e => e.textContent.trim()));
    assert(tabs.includes('Profil'), `Tab Profil tak ada: ${tabs}`);
    assert(tabs.some(t => t.includes('Foto')), `Tab Foto tak ada: ${tabs}`);
    assert(tabs.some(t => t.includes('Pelatihan')), `Tab Pelatihan tak ada: ${tabs}`);
});

// TC2: Tab Profil — label ID, tanpa istilah Inggris
await test('Tab Profil: label Indonesia, status ter-lokalisasi', async () => {
    const txt = await page.$eval('#tab-profil', el => el.innerText);
    for (const id of ['Nama', 'NIK / NIP', 'Departemen', 'Tanggal Bergabung', 'Bahasa']) {
        assert(txt.includes(id), `Label "${id}" tak ada di tab Profil`);
    }
    for (const en of ['Employment status', 'Join date', 'Locale']) {
        assert(!txt.includes(en), `Label Inggris "${en}" bocor`);
    }
    assert(/Aktif|Masa Percobaan|Resign|Diberhentikan/.test(txt), 'Status tak ter-lokalisasi');
});

// TC3: Tab Riwayat Pelatihan — tabel enrollment tampil dgn status ID
await test('Tab Pelatihan: tabel enrollment tampil + status Indonesia', async () => {
    await page.evaluate(() => new bootstrap.Tab(document.querySelector('[data-bs-target="#tab-pelatihan"]')).show());
    await page.waitForTimeout(200);
    const rows = await page.$$('#tab-pelatihan tbody tr');
    assert(rows.length >= 1, 'Tabel pelatihan kosong padahal ada data');
    const txt = await page.$eval('#tab-pelatihan', el => el.innerText);
    assert(/Lulus|Belum Selesai|Tidak Lulus|Terkunci/.test(txt), `Status pelatihan ID tak ada: ${txt.slice(0,120)}`);
});

// TC4: Tab Foto & QR — QR ter-generate
await test('Tab Foto & QR: QR code ter-render', async () => {
    await page.evaluate(() => new bootstrap.Tab(document.querySelector('[data-bs-target="#tab-foto"]')).show());
    await page.waitForTimeout(200);
    const hasQR = await page.$('#tab-foto img[src^="data:image/svg"]');
    assert(hasQR, 'QR code tidak ter-render di tab Foto');
});

// TC5: Nol JS pageerror
await test('Nol JavaScript pageerror', async () => {
    assert(pageErrors.length === 0, `pageerror: ${JSON.stringify(pageErrors)}`);
});

await browser.close();
const pass = results.filter(r => r.pass).length;
const fail = results.filter(r => !r.pass).length;
console.log(`\n${pass} pass / ${fail} fail`);
process.exit(fail > 0 ? 1 : 0);
