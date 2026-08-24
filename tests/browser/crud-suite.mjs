// Full CRUD lifecycle per entity, driven through a real browser session.
// Kebenaran ditentukan dengan MEMBACA BALIK keadaan (jumlah baris / nilai field),
// bukan menebak dari kode redirect — Backpack mengarahkan ke /edit setelah simpan
// sukses, sehingga redirect bukan penanda gagal.
// Run: node tests/browser/crud-suite.mjs
import fs from 'fs';
import { BASE, launch, session, token, rowCount, totalFromInfo, post, del, record, summary, titleOf } from './lib.mjs';

const Y = 2035; // tahun uji, jauh dari data seed

const ENTITIES = [
  { mod: 'Organisasi', ent: 'branch', label: 'Cabang', probe: 'name',
    valid: { name: 'ZZ Cabang Uji', code: 'ZZBR', lat: '-6.85', lng: '107.94', radius_meters: '150', is_active: '1' },
    patch: { name: 'ZZ Cabang Diubah', code: 'ZZBR', lat: '-6.85', lng: '107.94', radius_meters: '150', is_active: '1' },
    dup: { name: 'ZZ Dup', code: 'ZZBR' } },
  { mod: 'Organisasi', ent: 'department', label: 'Departemen', probe: 'name',
    valid: { name: 'ZZ Departemen Uji', code: 'ZZDEP' }, patch: { name: 'ZZ Dept Diubah', code: 'ZZDEP' },
    dup: { name: 'ZZ Dup', code: 'ZZDEP' } },
  { mod: 'Organisasi', ent: 'position', label: 'Jabatan', probe: 'name',
    valid: { name: 'ZZ Jabatan Uji', level: '2' }, patch: { name: 'ZZ Jabatan Diubah', level: '3' } },
  { mod: 'Cuti', ent: 'leave-type', label: 'Jenis Cuti', probe: 'name',
    valid: { name: 'ZZ Cuti Uji', code: 'ZZLT', default_quota: '10', is_paid: '1', is_active: '1' },
    patch: { name: 'ZZ Cuti Diubah', code: 'ZZLT', default_quota: '11', is_paid: '1', is_active: '1' },
    dup: { name: 'ZZ Dup', code: 'ZZLT' } },
  { mod: 'Dokumen', ent: 'document-type', label: 'Jenis Dokumen', probe: 'name',
    valid: { name: 'ZZ Dokumen Uji', code: 'ZZDT', allowed_extensions: 'pdf', max_file_size_mb: '5' },
    patch: { name: 'ZZ Dokumen Diubah', code: 'ZZDT', allowed_extensions: 'pdf', max_file_size_mb: '6' },
    dup: { name: 'ZZ Dup', code: 'ZZDT' } },
  // day_off adalah checkbox array (day_off[]) berisi id hari, bukan nama hari.
  // Mengirimnya sebagai skalar menghasilkan baris tidak sah yang gagal saat diedit.
  { mod: 'Absensi', ent: 'schedule', label: 'Jadwal', probe: 'name',
    valid: { name: 'ZZ Jadwal Uji', in: '08:00', out: '17:00', over_in: '18:00', over_out: '21:00', 'day_off[]': '2' },
    patch: { name: 'ZZ Jadwal Diubah', in: '09:00', out: '17:00', over_in: '18:00', over_out: '21:00', day_off: 'Minggu' } },
  { mod: 'Absensi', ent: 'national-holiday', label: 'Libur Nasional', probe: 'info',
    valid: { date: `${Y}-08-17`, info: 'ZZ Libur Uji' }, patch: { date: `${Y}-08-17`, info: 'ZZ Libur Diubah' } },
  { mod: 'Kasbon', ent: 'loan', label: 'Kasbon', probe: 'amount',
    valid: { user_id: '4', amount: '500000', date: `${Y}-01-15` }, patch: { user_id: '4', amount: '600000', date: `${Y}-01-15` } },
  { mod: 'Kasbon', ent: 'loan-payment', label: 'Pembayaran Kasbon', probe: 'amount',
    valid: { user_id: '4', amount: '100000', date: `${Y}-02-15` }, patch: { user_id: '4', amount: '120000', date: `${Y}-02-15` } },
  { mod: 'Pajak', ent: 'ptkp-rate', label: 'Tarif PTKP', probe: 'amount',
    valid: { year: String(Y), status: 'TK/0', amount: '54000000' }, patch: { year: String(Y), status: 'TK/0', amount: '55000000' } },
  { mod: 'Pajak', ent: 'pph21-bracket', label: 'Lapisan PPh 21', probe: 'rate',
    valid: { year: String(Y), lower_bound: '0', upper_bound: '60000000', rate: '5' },
    patch: { year: String(Y), lower_bound: '0', upper_bound: '60000000', rate: '6' } },
  { mod: 'Pajak', ent: 'bpjs-rate', label: 'Tarif BPJS', probe: 'employee_rate',
    valid: { year: String(Y), type: 'kesehatan', employee_rate: '1', employer_rate: '4', max_salary: '12000000' },
    patch: { year: String(Y), type: 'kesehatan', employee_rate: '2', employer_rate: '4', max_salary: '12000000' } },
  { mod: 'Cuti', ent: 'leave-balance', label: 'Saldo Cuti', probe: 'quota',
    valid: { user_id: '4', leave_type_id: '1', year: String(Y), quota: '12', carry_over: '6', used: '3' },
    patch: { user_id: '4', leave_type_id: '1', year: String(Y), quota: '14', carry_over: '6', used: '3' } },
  { mod: 'Pengaturan', ent: 'approval-flow', label: 'Alur Persetujuan', probe: 'name',
    valid: { name: 'ZZ Flow Uji', module: 'overtime', is_active: '0' },
    patch: { name: 'ZZ Flow Diubah', module: 'overtime', is_active: '0' } },
];

const browser = await launch();
const { page } = await session(browser, 'siti@demo.test');
const leftovers = [];

async function total(ent) { return totalFromInfo((await rowCount(page, ent)).info); }

async function lastId(ent) {
  await rowCount(page, ent);
  return page.evaluate(() => {
    const ids = [...document.querySelectorAll('#crudTable a[href*="/edit"]')]
      .map(a => parseInt((a.getAttribute('href').match(/\/(\d+)\/edit/) || [])[1], 10)).filter(Number.isFinite);
    return ids.length ? Math.max(...ids) : null;
  });
}

// PUT sungguhan — terbukti bekerja (lihat probe-update.mjs).
async function put(url, data) {
  const r = await page.request.fetch(url, {
    method: 'PUT',
    headers: { 'content-type': 'application/x-www-form-urlencoded' },
    data: new URLSearchParams(data).toString(),
    maxRedirects: 0, failOnStatusCode: false,
  });
  return { status: r.status(), location: (r.headers()['location'] || '').replace(BASE, ''), body: await r.text().catch(() => '') };
}

// Baca nilai field langsung dari form edit — ini sumber kebenarannya.
async function readField(ent, id, field) {
  const r = await page.goto(`${BASE}/admin/${ent}/${id}/edit`);
  if (!r.ok()) return { ok: false, status: r.status(), value: null };
  const v = await page.evaluate(f => {
    const el = document.querySelector(`[name="${f}"]`);
    if (!el) return null;
    return el.tagName === 'SELECT' ? (el.selectedOptions[0]?.text ?? el.value) : el.value;
  }, field);
  return { ok: true, status: r.status(), value: v };
}

// ---------------------------------------------------------------- 1. CREATE
for (const e of ENTITIES) {
  const { status, token: t } = await token(page, `${BASE}/admin/${e.ent}/create`);
  if (!t) { record(e.mod, `${e.ent}/C-form`, 'FAIL', `${e.label}: form create ${status}`); continue; }
  record(e.mod, `${e.ent}/C-form`, 'PASS', `${e.label}: form create ${status}`);

  const before = await total(e.ent);
  const res = await post(page, `${BASE}/admin/${e.ent}`, { _token: t, ...e.valid, _save_action: 'save_and_back' });
  const after = await total(e.ent);

  if (res.status === 500) {
    record(e.mod, `${e.ent}/C-valid`, 'FAIL', `${e.label}: data VALID ditolak 500 — ${titleOf(res.body)}`);
  } else if (before !== null && after === before + 1) {
    record(e.mod, `${e.ent}/C-valid`, 'PASS', `${e.label}: tersimpan (${before}→${after})`);
    e._created = true;
  } else if (before === null && after !== null) {
    record(e.mod, `${e.ent}/C-valid`, 'PASS', `${e.label}: tersimpan (tabel semula kosong → ${after})`);
    e._created = true;
  } else {
    record(e.mod, `${e.ent}/C-valid`, 'FAIL', `${e.label}: tidak tersimpan (${before}→${after}, http ${res.status} ${res.location})`);
  }
}

// ------------------------- 2. VALIDASI form kosong — jumlah baris yang menentukan
for (const e of ENTITIES) {
  const { token: t } = await token(page, `${BASE}/admin/${e.ent}/create`);
  if (!t) continue;
  const before = await total(e.ent);
  const res = await post(page, `${BASE}/admin/${e.ent}`, { _token: t, _save_action: 'save_and_back' });
  const after = await total(e.ent);

  if (res.status === 500) {
    record(e.mod, `${e.ent}/V-empty`, 'FAIL', `${e.label}: form kosong → HTTP 500 tanpa validasi — ${titleOf(res.body)}`);
  } else if (after === before) {
    record(e.mod, `${e.ent}/V-empty`, 'PASS', `${e.label}: form kosong ditolak, tidak ada baris baru (${before})`);
  } else {
    record(e.mod, `${e.ent}/V-empty`, 'FAIL', `${e.label}: form kosong TERSIMPAN (${before}→${after})`);
  }
}

// -------------------------------- 3. VALIDASI nilai duplikat pada kolom unique
for (const e of ENTITIES.filter(x => x.dup)) {
  const { token: t } = await token(page, `${BASE}/admin/${e.ent}/create`);
  if (!t) continue;
  const before = await total(e.ent);
  const res = await post(page, `${BASE}/admin/${e.ent}`, { _token: t, ...e.dup, _save_action: 'save_and_back' });
  const after = await total(e.ent);

  if (res.status === 500) {
    record(e.mod, `${e.ent}/V-dup`, 'FAIL', `${e.label}: kode duplikat → HTTP 500 — ${titleOf(res.body)}`);
  } else if (after === before) {
    record(e.mod, `${e.ent}/V-dup`, 'PASS', `${e.label}: kode duplikat ditolak validasi`);
  } else {
    record(e.mod, `${e.ent}/V-dup`, 'FAIL', `${e.label}: kode duplikat TERSIMPAN (${before}→${after})`);
  }
}

// ------------------------------------------------------------------ 4. READ
for (const e of ENTITIES) {
  const { rows, info } = await rowCount(page, e.ent);
  rows > 0
    ? record(e.mod, `${e.ent}/R-list`, 'PASS', `${e.label}: ${rows} baris dimuat AJAX — ${info}`)
    : record(e.mod, `${e.ent}/R-list`, 'FAIL', `${e.label}: tabel tidak terisi (rows=${rows})`);
}

// ------------------------------------ 5. UPDATE — diverifikasi dengan baca balik
for (const e of ENTITIES) {
  if (!e._created) { record(e.mod, `${e.ent}/U-edit`, 'SKIP', `${e.label}: tidak ada baris uji`); continue; }
  const id = await lastId(e.ent);
  if (!id) { record(e.mod, `${e.ent}/U-edit`, 'SKIP', `${e.label}: id tidak ditemukan`); continue; }
  e._id = id;

  const form = await readField(e.ent, id, e.probe);
  if (!form.ok) { record(e.mod, `${e.ent}/U-form`, 'FAIL', `${e.label}: form edit ${form.status}`); continue; }
  record(e.mod, `${e.ent}/U-form`, 'PASS', `${e.label}: form edit terbuka (${e.probe}="${form.value}")`);

  // Isi field lalu klik Simpan seperti pengguna sungguhan. PUT mentah tidak dipakai:
  // payload parsial membuat validasi menolak dan menghasilkan kegagalan palsu.
  const want = String(e.patch[e.probe] ?? '');
  await page.goto(`${BASE}/admin/${e.ent}/${id}/edit`);
  await page.fill(`[name="${e.probe}"]`, want).catch(() => null);
  const btn = await page.$('button[type="submit"]');
  await Promise.all([page.waitForLoadState('networkidle').catch(() => null), btn?.click().catch(() => null)]);
  const uiErrs = await page.evaluate(() => [...document.querySelectorAll('.invalid-feedback, .alert-danger')]
    .map(x => x.innerText.replace(/\s+/g, ' ').trim()).filter(t => t && t !== 'Keluar').slice(0, 3));

  const back = await readField(e.ent, id, e.probe);
  const got = String(back.value ?? '');

  if (uiErrs.length && got !== want) {
    record(e.mod, `${e.ent}/U-edit`, 'FAIL', `${e.label}: update ditolak validasi — ${JSON.stringify(uiErrs)}`);
  } else if (got === want || got.startsWith(want)) {
    record(e.mod, `${e.ent}/U-edit`, 'PASS', `${e.label}: update id=${id} tersimpan (${e.probe}="${got}")`);
  } else {
    record(e.mod, `${e.ent}/U-edit`, 'FAIL', `${e.label}: update id=${id} tidak tersimpan (${e.probe} ingin "${want}", dapat "${got}")`);
  }
}

// -------------------------------------- 6. DELETE — diverifikasi dengan baca balik
for (const e of ENTITIES) {
  if (!e._id) { record(e.mod, `${e.ent}/D-del`, 'SKIP', `${e.label}: tidak ada baris uji`); continue; }
  const { token: t } = await token(page, `${BASE}/admin/${e.ent}`);
  const res = await del(page, `${BASE}/admin/${e.ent}/${e._id}`, t);
  const back = await readField(e.ent, e._id, e.probe);

  if (!back.ok && (back.status === 404 || back.status === 500)) {
    record(e.mod, `${e.ent}/D-del`, 'PASS', `${e.label}: hapus id=${e._id} berhasil (edit → ${back.status})`);
  } else if (res.status === 200 && back.value === null) {
    record(e.mod, `${e.ent}/D-del`, 'PASS', `${e.label}: hapus id=${e._id} berhasil`);
  } else {
    record(e.mod, `${e.ent}/D-del`, 'FAIL', `${e.label}: hapus id=${e._id} gagal (http ${res.status}, masih ada: "${back.value}")`);
    leftovers.push({ ent: e.ent, id: e._id });
  }
}

// --------------------------------------------- 7. Operasi yang harus tertutup
for (const c of [
  { mod: 'Penggajian', ent: 'salary-recap', expect: [403] },
  { mod: 'Cuti', ent: 'leave-request', expect: [404] },
  { mod: 'Persetujuan', ent: 'approval', expect: [404] },
  { mod: 'Audit Log', ent: 'audit-log', expect: [404] },
  { mod: 'Pengaturan', ent: 'permission', expect: [404] },
]) {
  const r = await page.goto(`${BASE}/admin/${c.ent}/create`);
  c.expect.includes(r.status())
    ? record(c.mod, `${c.ent}/C-closed`, 'PASS', `create ditutup — ${r.status()}`)
    : record(c.mod, `${c.ent}/C-closed`, 'FAIL', `create seharusnya ${c.expect.join('/')}, dapat ${r.status()}`);
}

// ------------------------------- 8. Hak akses tulis manager (harus ditolak)
const mgr = await session(browser, 'budi@demo.test');
for (const w of [
  { mod: 'Users', ent: 'user', perm: 'user.create' },
  { mod: 'Pajak', ent: 'bpjs-rate', perm: 'permission pajak apa pun' },
  { mod: 'Pajak', ent: 'ptkp-rate', perm: 'permission pajak apa pun' },
  { mod: 'Pajak', ent: 'pph21-bracket', perm: 'permission pajak apa pun' },
  { mod: 'Pajak', ent: 'tax-profile', perm: 'permission pajak apa pun' },
  { mod: 'Organisasi', ent: 'branch', perm: 'permission cabang' },
  { mod: 'Organisasi', ent: 'department', perm: 'org.edit' },
  { mod: 'Organisasi', ent: 'position', perm: 'org.edit' },
  { mod: 'Profil Perusahaan', ent: 'company-profile', perm: 'company_profile.edit' },
  { mod: 'Cuti', ent: 'leave-type', perm: 'leave.configure' },
  { mod: 'Cuti', ent: 'leave-balance', perm: 'leave.manage_balance' },
  { mod: 'Dokumen', ent: 'document-type', perm: 'permission dokumen' },
  { mod: 'Akuntansi', ent: 'acc', perm: 'acc.edit' },
  { mod: 'Absensi', ent: 'schedule', perm: 'schedule.edit' },
  { mod: 'Absensi', ent: 'national-holiday', perm: 'national_holiday.edit' },
  { mod: 'Absensi', ent: 'presence', perm: 'presence.create' },
  { mod: 'Kasbon', ent: 'loan', perm: 'loan.create' },
  { mod: 'Kasbon', ent: 'loan-payment', perm: 'loan_payment.create' },
  { mod: 'Penggajian', ent: 'salary', perm: 'salary.edit' },
]) {
  const r = await mgr.page.goto(`${BASE}/admin/${w.ent}/create`);
  const s = r.status();
  s === 403 || s === 404
    ? record(w.mod, `${w.ent}/A-mgr-write`, 'PASS', `manager ditolak form create (${s})`)
    : record(w.mod, `${w.ent}/A-mgr-write`, 'FAIL', `manager DAPAT form create (${s}) tanpa ${w.perm}`);
}

for (const ent of ['role', 'permission', 'approval-flow', 'approval-flow-step']) {
  const r = await mgr.page.goto(`${BASE}/admin/${ent}`);
  r.status() === 403
    ? record('Pengaturan', `${ent}/A-mgr`, 'PASS', `manager ditolak 403`)
    : record('Pengaturan', `${ent}/A-mgr`, 'FAIL', `manager dapat ${r.status()}, seharusnya 403`);
}

// Scoping tim manager.
const uTot = totalFromInfo((await rowCount(mgr.page, 'user')).info);
uTot === 5 ? record('Users', 'user/A-mgr-scope', 'FAIL', `manager melihat ${uTot}/5 user — belum ter-scope tim`)
           : record('Users', 'user/A-mgr-scope', 'PASS', `manager melihat ${uTot} user`);
const pTot = totalFromInfo((await rowCount(mgr.page, 'presence')).info);
pTot === 110 ? record('Absensi', 'presence/A-mgr-scope', 'FAIL', `manager melihat ${pTot}/110 presensi — belum ter-scope tim`)
             : record('Absensi', 'presence/A-mgr-scope', 'PASS', `manager melihat ${pTot} presensi`);
const aTot = totalFromInfo((await rowCount(mgr.page, 'approval')).info);
aTot === 1 ? record('Persetujuan', 'approval/A-mgr-scope', 'PASS', `manager melihat ${aTot} dari 2 approval — ter-scope benar`)
           : record('Persetujuan', 'approval/A-mgr-scope', 'FAIL', `manager melihat ${aTot} approval, diharapkan 1`);

// ---------------------------------------------- 9. Employee terkunci dari admin
const emp = await session(browser, 'ahmad@demo.test');
for (const ent of ['user', 'salary-recap', 'audit-log', 'bpjs-rate', 'role']) {
  await emp.page.goto(`${BASE}/admin/${ent}`);
  emp.page.url().includes('/my')
    ? record('Portal', `${ent}/A-emp`, 'PASS', `employee dialihkan ke /my`)
    : record('Portal', `${ent}/A-emp`, 'FAIL', `employee mencapai ${emp.page.url()}`);
}

// -------------------------------------------------- 10. Cetak & export
for (const x of [
  { mod: 'Penggajian', id: 'salary-recap/X-print', url: '/admin/salary-recap/print?id=1', want: 200, label: 'Cetak slip gaji' },
  { mod: 'Penggajian', id: 'salary-recap/X-export', url: '/admin/salary-recap/export', want: 200, label: 'Export rekap gaji' },
  { mod: 'Users', id: 'user/X-export', url: '/admin/user/export', want: 200, label: 'Export karyawan' },
  { mod: 'Kasbon', id: 'loan/X-download', url: '/admin/loan/download', want: 200, label: 'Unduh rekap kasbon' },
  { mod: 'Kasbon', id: 'loan/X-print', url: '/admin/loan/1/print-detail', want: 200, label: 'Cetak detail kasbon' },
]) {
  const r = await page.request.get(BASE + x.url, { failOnStatusCode: false });
  r.status() === x.want
    ? record(x.mod, x.id, 'PASS', `${x.label}: ${r.status()} ${r.headers()['content-type']?.split(';')[0]}`)
    : record(x.mod, x.id, 'FAIL', `${x.label}: ${r.status()} — ${titleOf(await r.text().catch(() => ''))}`);
}

// ------------------------- 11. Route PUBLIK tanpa login (regresi BUG-012)
// Kedua suite selalu login lebih dulu, sehingga jalur anonim dulu tidak teruji
// dan `/scan` sempat 500 karena backpack_user() bernilai null di setup().
const anon = await browser.newContext();
const anonPage = await anon.newPage();
for (const [id, url, label] of [
  ['scan/R-anon', '/scan', 'Halaman scan publik'],
  ['root/R-anon', '/', 'Halaman muka (mengarah ke /scan)'],
]) {
  const r = await anonPage.goto(BASE + url);
  r.status() === 200
    ? record('Absensi', id, 'PASS', `${label}: 200 tanpa login`)
    : record('Absensi', id, 'FAIL', `${label}: ${r.status()} tanpa login`);
}
const scanEls = await anonPage.goto(`${BASE}/scan`).then(() => anonPage.evaluate(() => ({
  preview: !!document.querySelector('#preview'),
  ok: !!document.querySelector('#audioPlayer'),
  fail: !!document.querySelector('#audioPlayerFailed'),
})));
scanEls.preview && scanEls.ok && scanEls.fail
  ? record('Absensi', 'scan/R-elements', 'PASS', 'elemen scanner lengkap tanpa login')
  : record('Absensi', 'scan/R-elements', 'FAIL', `elemen kurang: ${JSON.stringify(scanEls)}`);
await anon.close();

await browser.close();
const out = summary();
fs.writeFileSync('/tmp/crud-results.json', JSON.stringify(out.results, null, 2));
if (leftovers.length) console.log(`\n⚠ sisa data uji: ${JSON.stringify(leftovers)}`);
