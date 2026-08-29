# Audit Plan — `docs/review-version-4` (Step 8: Plan-vs-Code Realization)

Hasil audit **rencana** review-version-4 terhadap **kode aktual** (skill
`laravel-codebase-audit`, prinsip *labels lie, code is truth*). Tiap klaim
akar-masalah & solusi di 11 file task diverifikasi ulang; ditambah temuan baru
yang plan belum tangkap.

Tanggal audit: dari sesi analisa modul User. Login uji: `siti@demo.test` (super_admin).

---

## A. Tabel verifikasi klaim plan

| Task | Klaim akar-masalah | Verifikasi kode | Verdict |
|------|--------------------|-----------------|---------|
| UM-01 | `setFromDb()` + kolom kebanyakan → collapse | `UserCrudController:112-148` ✔ | ✅ Valid |
| UM-02 | `--field-bg`=n-100 + border transparent di atas bar `--bg-sunken` | `admin.css:287-292`, `tokens.css:57,60,102` ✔ | ✅ Valid |
| UM-03 | Tombol didaftar terpisah `addButtonFromView` tanpa wadah | `UserCrudController:141-144,388` ✔ | ✅ Valid |
| UM-04 | Template & import tak baca NIK | `UserTemplateExport:16`, `UserImport:35-48` ✔ | ✅ Valid + **gap solusi** (lihat B1) |
| UM-05 | `locale` nullable tanpa default | migration `:16` ✔; klaim "butuh dbal" **salah** → dikoreksi | ✅ Valid (koreksi) |
| UM-06 | Show dump mentah; pelatihan tak tampil | `setupShowOperation:64`; **`User::trainingEnrollments` = 0 match** ✔ | ✅ Valid |
| UM-07 | Label Show Inggris | UI Show `Name/Locale/Employee/...` ✔ | ✅ Valid |
| UM-08 | `locale` bukan dropdown | tak ada field `locale` di `orgFields/fieldModification` ✔ | ✅ Valid |
| UM-09 | Import sinkron | `importStore:461-491` ✔; **infra queue `sync`, tabel `jobs` belum ada** → dikoreksi | ✅ Valid (koreksi) |
| UM-10 | Export sinkron | `export:422-424` ✔ | ✅ Valid + **temuan keamanan** (lihat B2) |
| UM-11 | printAll `User::all()` tanpa batas | `printAll:397-400`, `_print:401-420` ✔ | ✅ Valid + **temuan keamanan** (lihat B2) |

**Kesimpulan:** semua 11 klaim akar-masalah **terbukti benar di kode**. 3 koreksi
akurasi solusi sudah diterapkan (UM-04 unique, UM-05 dbal, UM-09 infra queue).

---

## B. Temuan BARU yang plan belum tangkap

### B1 — 🟡 UM-04: preview import punya daftar kolom hardcoded terpisah
`UserCrudController::importPreview()` (`:451-455`) memanggil:
```php
\App\Support\ImportPreview::build($path,
    ['email','nama'],                          // kolom wajib
    ['nama','email','departemen','cabang']);   // kolom DITAMPILKAN di preview
```
Menambah `nik` hanya di header template + `UserImport` **tidak akan** membuat NIK
muncul di layar pratinjau — array kolom-tampil di sini juga harus di-update jadi
`['nama','email','nik','departemen','cabang']`, dan string `$columns` di
`resources/views/admin/import/user.blade.php:7`. **Action:** ditambahkan ke UM-04.

### B2 — 🔴 KEAMANAN: route print/print-all/export TANPA guard permission + bypass scope
**Status:** [x] DONE — `UserCrudController::{export,print,printAll}` + `UserExport($viewer)`.
Test `tests/Feature/UserExportPrintSecurityTest.php` **5 PASS**; regresi User/Report/
Ranking/Recruitment **68 PASS**; super_admin export live **200 + XLSX valid**.
`routes/backpack/custom.php:59-68` — group `user` (print, print-all, export, import)
**tidak memasang middleware `permission:`**. Selain itu:
- `print($id)` (`:393`), `printAll()` (`:397`), `export()` (`:422`) **tak memanggil
  `hasAccessOrFail(...)`** di dalam method (berbeda dengan `importForm/Store` yang cek `create`).
- `setup()` — yang memuat guard `abort(403)` `user.view` + `addClause('visibleTo',$me)` —
  **hanya jalan untuk operasi CRUD** (Route::crud), BUKAN untuk method custom ini.
- Akibatnya:
  - `GET /admin/user/export` → `UserExport::query()` = `User::query()` (SEMUA user,
    tanpa `visibleTo`) → **manager bisa mengunduh seluruh data karyawan** (harusnya
    hanya bawahannya), kebal `user.view`/`user.export`.
  - `GET /admin/user/print-all` → `User::all()` → sama, bocor + kebal permission.
  - `GET /admin/user/{id}/print` → cetak ID siapa pun tanpa cek visibilitas.

**Dampak:** kebocoran data personel + kebal-otorisasi. Ini bug nyata, bukan sekadar
performa. **Action:**
- Tambah guard di UM-10 (export) & UM-11 (print/print-all): `hasAccessOrFail('user.view')`
  di awal tiap method **DAN** terapkan scope `visibleTo(backpack_user())` pada query
  (`UserExport`, `printAll`, `print`) supaya manager hanya dapat data bawahannya.
- Idealnya juga pasang `->middleware('permission:user.view')` pada route group agar
  ditolak di lapis route (defense in depth). `export` sebaiknya cek `user.view`
  (atau permission `user.export` bila ada di seeder — verifikasi dulu keberadaannya).

### B3 — 🟢 Catatan: `_print()` mengubah `$entry->qr`/`image` in-memory
`_print()` (`:401-408`) me-`map()` dan menimpa atribut model (`$user->image`,
`$user->qr`) untuk keperluan render. Karena tak dipanggil `save()`, tak korupsi DB,
tapi saat refactor UM-11 (batch/queue) hati-hati: jangan sampai perubahan in-memory
ini bocor ke path yang menyimpan. Bukan bug sekarang; catatan untuk eksekusi.

---

## C. Rekomendasi urutan (revisi kecil)

Prioritaskan **B2 (keamanan)** — angkat ke depan meski aslinya bagian UM-10/UM-11:
1. **Keamanan dulu** (B2): guard permission + scope `visibleTo` untuk export & print.
   Cepat, low-risk, high-value. Bisa dikerjakan sebagai bagian awal UM-10/UM-11 tanpa
   menunggu keputusan infra queue.
2. Lanjut CSS quick-win (UM-02 → UM-03 → UM-01).
3. Backend ringan (UM-05 → UM-08 → UM-04).
4. Struktural + mockup (UM-07 → UM-06 → UM-11 selection/filter).
5. Background/queue (UM-09, UM-10, UM-11 batch) — setelah keputusan infra.

---

## D. Verdict

Plan **akurat dan layak eksekusi**. Semua akar-masalah terbukti di kode. 3 koreksi
akurasi sudah diterapkan; 1 gap solusi (B1) + 1 temuan keamanan (B2) ditambahkan ke
file task terkait. Tidak ada klaim di plan yang terbukti salah/halusinasi.
