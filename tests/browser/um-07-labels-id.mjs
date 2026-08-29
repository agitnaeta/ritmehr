// tests/browser/um-07-labels-id.mjs
// UM-07: Label Show & form Create berbahasa Indonesia (nol label Inggris bocor),
// status kepegawaian ter-lokalisasi (Aktif, bukan active).

import { launch, session, BASE } from './lib.mjs';

const results = [];
async function test(name, fn) {
    try { await fn(); results.push({ name, pass: true }); console.log(`  ✅ ${name}`); }
    catch (e) { results.push({ name, pass: false, err: e.message }); console.log(`  ❌ ${name}: ${e.message}`); }
}
function assert(cond, msg) { if (!cond) throw new Error(msg); }

const browser = await launch();
const { page } = await session(browser, 'siti@demo.test');

const LEAK_EN = ['Employment status', 'Join date', 'Employee:', 'Locale', 'Created:', 'Updated:', 'Name:', 'Password'];

// TC1: Show — nol label Inggris bocor
await test('Show: nol label Inggris bocor', async () => {
    await page.goto(`${BASE}/admin/user/2/show`);
    await page.waitForSelector('table', { timeout: 8000 });
    const bodyText = await page.evaluate(() => document.querySelector('main').innerText);

    for (const leak of LEAK_EN) {
        assert(!bodyText.includes(leak), `Label Inggris bocor di Show: "${leak}"`);
    }
});

// TC2: Show — label ID inti hadir + status ter-lokalisasi
await test('Show: label ID hadir & status = Aktif (bukan active)', async () => {
    const bodyText = await page.evaluate(() => document.querySelector('main').innerText);

    for (const id of ['Nama:', 'Bahasa:', 'NIK / NIP:', 'Tanggal Bergabung:', 'Status Kepegawaian:', 'No. Telepon:', 'Dibuat:', 'Diperbarui:']) {
        assert(bodyText.includes(id), `Label ID tak ada di Show: "${id}"`);
    }
    assert(bodyText.includes('Aktif'), 'Status "Aktif" tidak tampil');
    assert(!/\bactive\b/.test(bodyText), 'Status mentah "active" masih bocor');
    // Bahasa tampil "Indonesia" bukan kode "id"
    assert(bodyText.includes('Indonesia'), 'Bahasa "Indonesia" tidak tampil');
});

// TC3: Form Create — label field berbahasa ID
await test('Create: label field berbahasa Indonesia', async () => {
    await page.goto(`${BASE}/admin/user/create`);
    await page.waitForSelector('form', { timeout: 8000 });
    const labels = await page.$$eval('form label', ls => ls.map(l => l.textContent.trim()));
    const joined = labels.join(' | ');

    for (const id of ['Nama', 'Email', 'Bahasa', 'Kata Sandi', 'Jadwal', 'NIK / NIP', 'Tanggal Bergabung', 'Status Kepegawaian', 'No. Telepon', 'Alamat']) {
        assert(labels.some(l => l.includes(id)), `Label form ID tak ada: "${id}". Labels: ${joined}`);
    }
    // Nol label Inggris umum
    for (const leak of ['Schedule', 'Password', 'Employment status', 'Join date']) {
        assert(!labels.some(l => l === leak || l.includes(leak)), `Label Inggris bocor di form: "${leak}"`);
    }
});

await browser.close();

const pass = results.filter(r => r.pass).length;
const fail = results.filter(r => !r.pass).length;
console.log(`\n${pass} pass / ${fail} fail`);
process.exit(fail > 0 ? 1 : 0);
