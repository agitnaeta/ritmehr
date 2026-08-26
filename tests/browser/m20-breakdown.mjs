// M20 — Salary breakdown: CRUD pages load + assigning an allowance updates the
// employee's salary total. Real browser through the admin forms.
import { chromium } from 'playwright';
import { execSync } from 'node:child_process';

const BASE = 'http://127.0.0.1:8000';
const results = [];
const pass = (id, n) => { results.push(['PASS', id, n]); console.log(`PASS ${id} — ${n}`); };
const fail = (id, n) => { results.push(['FAIL', id, n]); console.log(`FAIL ${id} — ${n}`); };
const q = (action, ...args) => execSync(`php tests/browser/_m20_helper.php ${action} ${args.map(a=>`'${a}'`).join(' ')}`, { cwd: process.cwd() }).toString().trim().split('\n').pop().trim();

const b = await chromium.launch();
const p = await b.newContext().then(c => c.newPage());
p.setDefaultTimeout(30000);
const jsErr = [];
p.on('pageerror', e => jsErr.push(e.message));

await p.goto(`${BASE}/admin/login`);
await p.fill('input[name="email"]', 'siti@demo.test');
await p.fill('input[name="password"]', 'password');
await Promise.all([p.waitForNavigation(), p.click('button[type="submit"]')]);

// 1. Menu items present
await p.goto(`${BASE}/admin/dashboard`); await p.waitForTimeout(600);
const links = await p.evaluate(() => [...document.querySelectorAll('a')].map(a => a.getAttribute('href') || ''));
links.some(l => l.includes('/salary-allowance-type')) ? pass('TC-M20-01', 'menu Jenis Tunjangan ada')
  : fail('TC-M20-01', 'menu Jenis Tunjangan hilang');
links.some(l => l.includes('/employee-salary-allowance')) ? pass('TC-M20-02', 'menu Tunjangan Karyawan ada')
  : fail('TC-M20-02', 'menu Tunjangan Karyawan hilang');

// 2. Allowance-type CRUD list loads
await p.goto(`${BASE}/admin/salary-allowance-type`); await p.waitForTimeout(1200);
let bodyTxt = await p.evaluate(() => document.body.innerText);
(!bodyTxt.includes('Whoops') && !bodyTxt.includes('403')) ? pass('TC-M20-03', 'CRUD Jenis Tunjangan load')
  : fail('TC-M20-03', 'CRUD Jenis Tunjangan error');

// 3. Create an allowance type via the real form
await p.goto(`${BASE}/admin/salary-allowance-type/create`); await p.waitForTimeout(1000);
const label = 'Tunjangan Uji ' + Date.now();
await p.fill('input[name="label"]', label);
await Promise.all([p.waitForNavigation(), p.click('button[type="submit"]')]).catch(()=>{});
await p.waitForTimeout(1000);
const typeId = q('type_id', label);
(typeId && /^[0-9]+$/.test(typeId)) ? pass('TC-M20-04', `jenis tunjangan dibuat via form (id ${typeId})`)
  : fail('TC-M20-04', 'gagal buat jenis tunjangan');

// 4. Assign allowance to an employee with a salary → total updates
// Pick an employee that has a salary row.
const uid = q('salary_user');
const before = q('amount', uid);
const basic = q('basic', uid);
await p.goto(`${BASE}/admin/employee-salary-allowance/create`); await p.waitForTimeout(1200);
// select2 fields — set via option value using native select
await p.evaluate(({ uid, typeId }) => {
  const setSel = (name, val) => {
    const s = document.querySelector(`select[name="${name}"]`);
    if (s) { s.value = val; s.dispatchEvent(new Event('change', { bubbles: true })); }
  };
  setSel('user_id', String(uid));
  setSel('salary_allowance_type_id', String(typeId));
}, { uid, typeId });
await p.fill('input[name="amount"]', '750000');
await Promise.all([p.waitForNavigation(), p.click('button[type="submit"]')]).catch(()=>{});
await p.waitForTimeout(1200);

const after = q('amount', uid);
(parseInt(after) === parseInt(basic) + 750000)
  ? pass('TC-M20-05', `total gaji auto-update ${before}→${after} (basic ${basic} + 750rb)`)
  : fail('TC-M20-05', `total tak update benar: before=${before} basic=${basic} after=${after}`);

if (jsErr.length) fail('TC-M20-JS', 'JS error: ' + jsErr.join(' | '));
else pass('TC-M20-JS', 'tidak ada JS error');

// cleanup
q('cleanup', typeId, uid);

await b.close();
const failed = results.filter(r => r[0] === 'FAIL');
console.log(`\n${'='.repeat(66)}\n${results.length - failed.length} PASS / ${failed.length} FAIL\n${'='.repeat(66)}`);
failed.forEach(([, id, n]) => console.log(`  FAIL ${id} — ${n}`));
process.exit(failed.length ? 1 : 0);
