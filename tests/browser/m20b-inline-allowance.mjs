// M20b — Tunjangan inline di form Gaji. Edit gaji karyawan existing → isi tunjangan
// via FORM asli → total auto-update + rincian muncul di halaman Show. Menu terpisah hilang.
// Run: node tests/browser/m20b-inline-allowance.mjs  (needs `php artisan serve` on :8000)
import { chromium } from 'playwright';
import { execSync } from 'node:child_process';

const BASE = 'http://127.0.0.1:8000';
const results = [];
const pass = (id, n) => { results.push(['PASS', id, n]); console.log(`PASS ${id} — ${n}`); };
const fail = (id, n) => { results.push(['FAIL', id, n]); console.log(`FAIL ${id} — ${n}`); };
const h = (action, ...args) => execSync(`php tests/browser/_m20b_helper.php ${action} ${args.map(a=>`'${a}'`).join(' ')}`, { cwd: process.cwd() }).toString().trim().split('\n').pop().trim();

const b = await chromium.launch();
const p = await b.newContext().then(c => c.newPage());
p.setDefaultTimeout(30000);
const jsErr = []; p.on('pageerror', e => jsErr.push(e.message));

await p.goto(`${BASE}/admin/login`);
await p.fill('input[name="email"]', 'siti@demo.test');
await p.fill('input[name="password"]', 'password');
await Promise.all([p.waitForNavigation(), p.click('button[type="submit"]')]);

// 0. seed a fresh allowance type
const label = 'Transport ' + Date.now();
const typeId = h('seed_type', label);
const [uid, sid] = h('user_with_salary').split(':');
const basic = h('basic', uid);

// 1. Menu "Tunjangan Karyawan" hidden, "Jenis Tunjangan" still present
await p.goto(`${BASE}/admin/dashboard`); await p.waitForTimeout(600);
const links = await p.evaluate(() => [...document.querySelectorAll('a')].map(a => a.getAttribute('href')||''));
!links.some(l => l.includes('/employee-salary-allowance')) ? pass('TC-01', 'menu Tunjangan Karyawan disembunyikan')
  : fail('TC-01', 'menu terpisah masih ada');
links.some(l => l.includes('/salary-allowance-type')) ? pass('TC-02', 'menu Jenis Tunjangan tetap ada')
  : fail('TC-02', 'menu Jenis Tunjangan hilang');

// 2. Edit salary form shows the allowance field
await p.goto(`${BASE}/admin/salary/${sid}/edit`); await p.waitForTimeout(1500);
const fieldName = `allowance[${typeId}]`;
const hasField = await p.evaluate((n) => !!document.querySelector(`[name="${n}"]`), fieldName);
hasField ? pass('TC-03', 'field tunjangan muncul di form gaji')
         : fail('TC-03', 'field tunjangan tidak muncul');

// 3. Fill the allowance + save
await p.fill(`[name="${fieldName}"]`, '600000');
await Promise.all([p.waitForNavigation().catch(()=>{}), p.click('button[type="submit"]')]);
await p.waitForTimeout(1500);
const saved = h('has_allowance', uid, typeId);
(parseInt(saved) === 600000) ? pass('TC-04', `tunjangan tersimpan via form (${saved})`)
  : fail('TC-04', `tunjangan tak tersimpan: ${saved}`);

// 4. Total auto-updated
const total = h('amount', uid);
(parseInt(total) === parseInt(basic) + 600000)
  ? pass('TC-05', `total auto-update ${basic}+600rb=${total}`)
  : fail('TC-05', `total salah: basic=${basic} total=${total}`);

// 5. Show page has the breakdown
await p.goto(`${BASE}/admin/salary/${sid}/show`); await p.waitForTimeout(1200);
const body = await p.evaluate(() => document.body.innerText);
(body.includes('Komponen Gaji') && body.includes(label))
  ? pass('TC-06', 'halaman Show nampilin rincian tunjangan')
  : fail('TC-06', 'rincian tunjangan tak muncul di Show');

// 6. Edit again → clear the allowance → row removed
await p.goto(`${BASE}/admin/salary/${sid}/edit`); await p.waitForTimeout(1500);
await p.fill(`[name="${fieldName}"]`, '');
await Promise.all([p.waitForNavigation().catch(()=>{}), p.click('button[type="submit"]')]);
await p.waitForTimeout(1500);
const afterClear = h('has_allowance', uid, typeId);
(afterClear === '0' || afterClear === '')
  ? pass('TC-07', 'kosongkan field → baris tunjangan terhapus')
  : fail('TC-07', `baris tak terhapus: ${afterClear}`);

if (jsErr.length) fail('TC-JS', 'JS error: ' + jsErr.join(' | '));
else pass('TC-JS', 'tidak ada JS error');

h('cleanup', typeId);
await b.close();

const failed = results.filter(r => r[0] === 'FAIL');
console.log(`\n${'='.repeat(64)}\n${results.length - failed.length} PASS / ${failed.length} FAIL\n${'='.repeat(64)}`);
failed.forEach(([, id, n]) => console.log(`  FAIL ${id} — ${n}`));
process.exit(failed.length ? 1 : 0);
