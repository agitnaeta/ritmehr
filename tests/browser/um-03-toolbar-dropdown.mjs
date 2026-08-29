// tests/browser/um-03-toolbar-dropdown.mjs
// UM-03: Verifikasi toolbar presisi — 2 tombol (Tambah user + dropdown ⋯)
// dengan 3 item (Export, Import, Cetak Semua ID)

import { launch, session, BASE } from './lib.mjs';

const results = [];

async function test(name, fn) {
    try {
        await fn();
        results.push({ name, pass: true });
        console.log(`  ✅ ${name}`);
    } catch (e) {
        results.push({ name, pass: false, err: e.message });
        console.log(`  ❌ ${name}: ${e.message}`);
    }
}

function assert(cond, msg) { if (!cond) throw new Error(msg); }

const browser = await launch();
const { page } = await session(browser, 'siti@demo.test');
await page.goto(`${BASE}/admin/user`);
await page.waitForSelector('#crudTable', { timeout: 8000 });

// Test 1: Tombol "Tambah user" ada
await test('Tombol Tambah user ada', async () => {
    const btn = await page.$('a[href*="/admin/user/create"]');
    assert(btn, 'Tombol Tambah user tidak ditemukan');
    const text = await btn.textContent();
    assert(text.includes('Tambah user'), `Label salah: "${text}"`);
});

// Test 2: Dropdown ⋯ ada (btn-group dengan dropdown-toggle)
await test('Dropdown ⋯ ada', async () => {
    const dd = await page.$('#userActionsDropdownBtn');
    assert(dd, 'Dropdown button tidak ditemukan');
    const toggle = await dd.getAttribute('data-bs-toggle');
    assert(toggle === 'dropdown', `data-bs-toggle: "${toggle}"`);
});

// Test 3: Tidak ada tombol Export/Import/Cetak terpisah di toolbar
await test('Tidak ada tombol terpisah di toolbar (hanya dalam dropdown)', async () => {
    // Top-level buttons (direct child of row, not inside dropdown-menu)
    const exportBtn = await page.$('.col-md-12 > .row > a[href*="/export"]');
    const importBtn = await page.$('.col-md-12 > .row > a[href*="import"]');
    const printAllBtn = await page.$('.col-md-12 > .row > a[href*="print-all"]');
    assert(!exportBtn, 'Tombol Export masih terpisah di toolbar');
    assert(!importBtn, 'Tombol Import masih terpisah di toolbar');
    assert(!printAllBtn, 'Tombol Cetak Semua masih terpisah di toolbar');
});

// Test 4: Dropdown punya 3 item dengan href benar
await test('Dropdown berisi 3 item (Export, Import, Cetak Semua ID)', async () => {
    // Open dropdown via JS (Playwright click kadang tidak trigger Bootstrap)
    await page.evaluate(() => {
        const btn = document.getElementById('userActionsDropdownBtn');
        btn.click();
    });
    await page.waitForTimeout(300);

    const items = await page.$$('.dropdown-menu.show .dropdown-item');
    assert(items.length === 3, `Expected 3 items, got ${items.length}`);

    // Verify each item
    const texts = [];
    for (const item of items) {
        texts.push((await item.textContent()).trim());
    }

    assert(texts.some(t => t.includes('User Export')), `Missing "User Export" in: ${texts}`);
    assert(texts.some(t => t.includes('Import Excel')), `Missing "Import Excel" in: ${texts}`);
    assert(texts.some(t => t.includes('Cetak Semua ID')), `Missing "Cetak Semua ID" in: ${texts}`);
});

// Test 5: Dropdown item hrefs benar
await test('Dropdown item hrefs benar', async () => {
    const exportLink = await page.$('.dropdown-menu .dropdown-item[href*="/export"]');
    const importLink = await page.$('.dropdown-menu .dropdown-item[href*="import"]');
    const printLink = await page.$('.dropdown-menu .dropdown-item[href*="print"]');

    assert(exportLink, 'Export link tidak ditemukan di dropdown');
    assert(importLink, 'Import link tidak ditemukan di dropdown');
    assert(printLink, 'Print link tidak ditemukan di dropdown');

    const exportHref = await exportLink.getAttribute('href');
    const importHref = await importLink.getAttribute('href');
    const printHref = await printLink.getAttribute('href');

    assert(exportHref.includes('/admin/user/export'), `Export href: "${exportHref}"`);
    assert(importHref.includes('import'), `Import href: "${importHref}"`);
    assert(printHref.includes('print'), `Print href: "${printHref}"`);
});

// Test 6: Mobile viewport — dropdown tetap berfungsi
await test('Mobile: dropdown tetap berfungsi (375px)', async () => {
    await page.setViewportSize({ width: 375, height: 812 });
    await page.goto(`${BASE}/admin/user`);
    await page.waitForSelector('#crudTable', { timeout: 8000 });

    const dd = await page.$('#userActionsDropdownBtn');
    assert(dd, 'Dropdown tidak ditemukan di mobile');
    const visible = await dd.isVisible();
    assert(visible, 'Dropdown tidak visible di mobile');

    // Reset viewport
    await page.setViewportSize({ width: 1280, height: 800 });
});

await browser.close();

// Summary
const pass = results.filter(r => r.pass).length;
const fail = results.filter(r => !r.pass).length;
console.log(`\n${pass} pass / ${fail} fail`);
process.exit(fail > 0 ? 1 : 0);
