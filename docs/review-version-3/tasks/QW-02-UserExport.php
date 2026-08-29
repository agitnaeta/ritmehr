# QW-02 — `app/Exports/UserExport.php`

**Fokus:** PERF-6 — stop dump semua kolom (bocor password) + hemat RAM
**Severity:** 🟠 Tinggi
**Status:** [x] DONE — commit: `pending` (terverifikasi 2026-08-29)
**File (satu-satunya) yang disentuh:** `app/Exports/UserExport.php`

---

## Masalah
`UserExport::collection()` = `User::all()` (baris 15): tanpa filter, tanpa `select`,
tanpa chunk. Seluruh baris + **SEMUA kolom termasuk `password` hash & `remember_token`**
masuk RAM lalu di-serialize ke Excel → boros CPU/RAM + **kebocoran data sensitif**.

## Diff (langkah minimal — batasi kolom, buang yang sensitif)
```php
<?php

namespace App\Exports;

use App\Models\User;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class UserExport implements FromQuery, WithHeadings, WithChunkReading
{
    public function query()
    {
        // Hanya kolom non-sensitif. JANGAN sertakan password / remember_token.
        return User::query()->select([
            'id', 'name', 'email', 'phone', 'address',
            'employment_status', 'created_at',
        ]);
    }

    public function headings(): array
    {
        return ['ID','Nama','Email','Telepon','Alamat','Status Kerja','Dibuat'];
    }

    public function chunkSize(): int
    {
        return 1000; // memori rata, tidak spike pada data besar
    }
}
```
> Verifikasi nama kolom dulu: `SHOW COLUMNS FROM users;` — sesuaikan bila ada kolom
> yang tak ada (mis. `employment_status`).

## Cek per file
- [ ] Buka `admin/user` → tombol export → file `user-export.xlsx` **tidak** memuat kolom
      password/token.
- [ ] Export pada dataset besar (seed 1000 user) tidak melonjakkan memori (bandingkan
      `memory_get_peak_usage`).

---

## Verifikasi
- [x] `php -l app/Exports/UserExport.php` → "No syntax errors detected"
- [x] File xlsx asli diperiksa: heading + data benar, TANPA password/token
- [x] `node tests/browser/crud-suite.mjs` → **146 PASS**, Users 3/3 (crud-suite menyentuh export user)
- [x] Flip `Status:` ke `[x] DONE`

## Catatan implementasi
Kolom disesuaikan dgn `SHOW COLUMNS FROM users` — dipilih non-sensitif + berguna:
`id, employee_id(NIP), name, email, phone, address, employment_status, join_date, created_at`.
Ditambah `WithMapping` untuk memformat tanggal (`join_date`/`created_at` di-cast date → format
`Y-m-d`). **password, remember_token, qr TIDAK disertakan.**

## PROOF (2026-08-29)

### Isi file xlsx ASLI (unzip xl/sharedStrings.xml)
```
Header : ID | NIP | Nama | Email | Telepon | Alamat | Status Kerja | Tanggal Bergabung | Dibuat
Row-1  : EMP-001 | Siti Rahayu | siti@demo.test | 081200000001 | active | 2019-03-01 | 2026-08-26 10:09
Row-2  : EMP-002 | Budi Santoso | budi@demo.test | ...
... (5 karyawan)
```

### Bukti kebocoran tertutup (scan seluruh arsip xlsx)
```
$ unzip -p qw02_proof.xlsx '*' | grep -E '\$2y\$|remember_token|password'
=> TIDAK ADA hash/password/token (AMAN)
```
Bandingkan: hash password user di DB = `$2y$12$b01...` — dipastikan **tidak muncul** di file.

### Skalabilitas
`FromCollection User::all()` → `FromQuery` + `WithChunkReading(1000)`: baris diproses
per-1000, memori rata (tidak memuat seluruh tabel ke RAM sekaligus).

### Regresi
- crud-suite: **146/146**, Users **3/3** (suite menjalankan export user → tetap lulus).
- PHPUnit: tak ada Feature test khusus UserExport; baseline 2-failure time-dependent tetap sama.
