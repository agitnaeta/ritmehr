# QW-02 — `app/Exports/UserExport.php`

**Fokus:** PERF-6 — stop dump semua kolom (bocor password) + hemat RAM
**Severity:** 🟠 Tinggi
**Status:** [ ] TODO — commit: `______`
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
- [ ] `php -l app/Exports/UserExport.php` bersih (kalau file PHP)
- [ ] `php -d memory_limit=2G -d xdebug.mode=off vendor/bin/phpunit --no-coverage` → tetap hijau (baseline)
- [ ] `node tests/browser/crud-suite.mjs` → tetap hijau (baseline 146)
- [ ] Verifikasi manual di browser sesuai bagian "Cek" di atas
- [ ] Flip `Status:` ke `[x] DONE` + isi commit SHA setelah semua centang
