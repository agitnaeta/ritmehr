# UM-04 — Import dukung kolom NIK / `employee_id`

**Poin Capt #4** · Tipe: Import · Urgensi: Sedang · Risiko: Rendah
**Status:** [x] DONE — template+import baca `nik`→employee_id (opsional, unik), preview tampil NIK.
Test `tests/Feature/UserImportNikTest.php` (6 PASS) + regresi 44 PHPUnit PASS.

---

## Konteks
Template import tidak punya kolom NIK, dan `UserImport` tak membaca `employee_id`,
sehingga user hasil import selalu tanpa NIK — padahal data seed punya `EMP-004`, dst.
**Keputusan Capt:** kolom NIK boleh diisi ATAU dikosongkan (opsional).

## Akar masalah (terverifikasi)
- `app/Exports/UserTemplateExport.php:16` header tanpa `nik`/`employee_id`.
- `app/Imports/UserImport.php:35-48` `model()` tidak membaca `employee_id`.

## Rencana solusi
File yang disentuh:
1. `app/Exports/UserTemplateExport.php`
   - Tambah kolom header `nik` (posisi setelah `email`), isi baris contoh (mis. `EMP-010`).
2. `app/Imports/UserImport.php`
   - Di `model()`: `'employee_id' => trim($row['nik'] ?? '') ?: null`.
   - Tetap opsional: kosong → null, tidak menggagalkan baris.
   - ⚠️ **`users.employee_id` punya UNIQUE constraint** (migration
     `2026_08_07_100001...:36` `->unique()`). NIK duplikat akan melempar
     `QueryException`. WAJIB validasi via `WithValidation` rule
     `'nik' => ['nullable','unique:users,employee_id']` supaya baris bentrok masuk
     `SkipsOnFailure` (dilaporkan), bukan menghentikan seluruh import.
   - Karena import keyed by email (`updateOrCreate` di `email`), pastikan saat UPDATE
     user yang sama, rule unique mengecualikan dirinya (atau cukup set employee_id hanya
     bila berbeda).
3. `resources/views/admin/import/user.blade.php` — update daftar `columns` (baris 7)
   agar sebut `nik`.
4. ⚠️ **`UserCrudController::importPreview()` (`:451-455`) punya daftar kolom-tampil
   HARDCODE terpisah** — tambah `nik` di array kolom yang ditampilkan:
   ```php
   \App\Support\ImportPreview::build($path,
       ['email','nama'],
       ['nama','email','nik','departemen','cabang']); // +nik
   ```
   Tanpa ini, NIK tak muncul di layar pratinjau meski template & import sudah dukung.

## Rencana test UI
`tests/browser/um-04-import-nik.mjs` + PHPUnit `tests/Feature/UserImportNikTest.php`:
- TC1 (PHPUnit): import baris dengan `nik=EMP-999` → user tersimpan dengan
  `employee_id=EMP-999`.
- TC2 (PHPUnit): import baris tanpa `nik` → user tersimpan, `employee_id` null, tak error.
- TC3 (browser): unduh template → header memuat kolom `nik`.
- TC4 (browser): upload file berisi NIK via form asli → preview & simpan → NIK muncul di list.

## Definition of Done
Template punya kolom NIK; import mengisi `employee_id` (opsional); test PASS
(PHPUnit + browser); update Status + centang README.
