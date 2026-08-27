# QW-03 — Label kolom Users konsisten Bahasa Indonesia

**Status:** [x] DONE — commit: `(uncommitted, terverifikasi)`
**File:** `app/Http/Controllers/Admin/UserCrudController.php`
**Menutup:** RV1-005 (🟡 header campur EN/ID) · Prioritas P2
**Bukti bug:** `../screenshots/04-users-list.png`

## Masalah
Di `setupListOperation()` baris 114 dipanggil `CRUD::setFromDb()` yang auto-humanize kolom DB → header tampil Inggris: "Name, Email, Locale, Employee, Join date", berdampingan dgn "Departemen/Jabatan/Cabang/Status" (ID) dari `orgListColumns()`.

## Perubahan
Setelah `setFromDb()` (dan setelah `orgListColumns()`), override label kolom bawaan ke Bahasa Indonesia. Tambahkan di `setupListOperation()`:

```php
$this->crud->column('name')->label('Nama');
$this->crud->column('email')->label('Email');           // sudah ID, aman
$this->crud->column('locale')->label('Bahasa');
$this->crud->column('employee_id')->label('Karyawan');  // sesuaikan nama kolom aktual (mis. 'code'/'nip')
$this->crud->column('join_date')->label('Tgl Bergabung');
```
Sesuaikan `name` tiap kolom dgn kolom DB aktual (cek `users` table jika nama beda).

## Verifikasi
1. `/admin/user`: semua header berbahasa Indonesia, tak ada "Name/Locale/Join date".
2. Regresi: `node tests/browser/ui-test.mjs` (TC-USER-01) + `crud-suite.mjs` (Users 3/3) hijau.
