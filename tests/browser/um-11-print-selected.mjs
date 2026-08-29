// tests/browser/um-11-print-selected.mjs
// UM-11: Cetak Terpilih — checkbox baris memunculkan tombol "Cetak ID Terpilih (N)"
// dengan count benar; dropdown "Cetak Semua ID" hadir.

import { launch, session, BASE } from './lib.mjs';

const results = [];
async function test(name, fn) {
    try { await fn(); results.push({ name, pass: true }); console.log(`  ✅ ${name}`); }
    catch (e) { results.push({ name, pass: false, err: e.message }); console.log(`  ❌ ${name}: ${e.message}`); }
}
function assert(cond, msg) { if (!cond) throw new Error(msg); }

const browser = await launch();
const { page } = await session(browser, 'siti@demo.test');

await page.goto(`${BASE}/admin/user`);
await page.waitForSelector('.crud_bulk_actions_line_checkbox', { timeout: 8000 });

// TC1: tombol tersembunyi saat tak ada seleksi
await test('Tombol Cetak Terpilih tersembunyi saat 0 seleksi', async () => {
    const disp = await page.$eval('#btnPrintSelected', el => el.style.display);
    assert(disp === 'none', `Tombol harusnya tersembunyi, display=${disp}`);
});

// TC2: centang 2 baris → tombol muncul, count 2
await test('Centang 2 baris → tombol muncul dengan count 2', async () => {
    await page.$$eval('.crud_bulk_actions_line_checkbox', els => {
        els.slice(0, 2).forEach(cb => { cb.checked = true; cb.dispatchEvent(new Event('change', { bubbles: true })); });
    });
    await page.waitForTimeout(300);
    const count = await page.$eval('#printSelectedCount', el => el.textContent);
    const disp = await page.$eval('#btnPrintSelected', el => el.style.display);
    assert(count === '2', `count harusnya 2, dapat ${count}`);
    assert(disp !== 'none', 'tombol harus tampil');
});

// TC3: uncheck → tombol sembunyi lagi
await test('Uncheck semua → tombol sembunyi lagi', async () => {
    await page.$$eval('.crud_bulk_actions_line_checkbox', els => {
        els.forEach(cb => { cb.checked = false; cb.dispatchEvent(new Event('change', { bubbles: true })); });
    });
    await page.waitForTimeout(300);
    const disp = await page.$eval('#btnPrintSelected', el => el.style.display);
    assert(disp === 'none', `tombol harusnya sembunyi, display=${disp}`);
});

// TC4: dropdown "Cetak Semua ID" hadir dengan handler filter
await test('Dropdown Cetak Semua ID hadir', async () => {
    const exists = await page.$('#printAllFiltered');
    assert(exists, 'link Cetak Semua ID tak ada');
    const href = await page.$eval('#printAllFiltered', el => el.getAttribute('href'));
    assert(href.includes('/print-all'), `href salah: ${href}`);
});

await browser.close();
const pass = results.filter(r => r.pass).length;
const fail = results.filter(r => !r.pass).length;
console.log(`\n${pass} pass / ${fail} fail`);
process.exit(fail > 0 ? 1 : 0);
