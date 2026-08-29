# QW-01 — `app/Http/Controllers/Admin/SalaryRecapCrudController.php`

**Fokus:** PERF-5 — hilangkan N+1 di export gaji
**Severity:** 🟠 Tinggi
**Status:** [ ] TODO — commit: `______`
**File (satu-satunya) yang disentuh:** `app/Http/Controllers/Admin/SalaryRecapCrudController.php`

---

## Masalah
`export()` (baris **258**) hanya eager-load `with(['user'])`, padahal
`SalaryRecapExport::map()` (baris 80) mengakses `$row->user->salary->fine_type`.
Relasi `salary` jadi **lazy-load 1 query per baris** → N+1 saat file dibangun (beban CPU
di request). Method `print()` di file yang sama (baris 273-275) SUDAH benar melakukan
eager-load `user.salary` — cukup samakan.

## Diff
```php
// SalaryRecapCrudController.php — method export(), ~baris 258
-        $recaps = SalaryRecap::with(['user'])
+        $recaps = SalaryRecap::with(['user.salary'])
             ->where(function ($q) use ($sr){
                 if($sr != null){
                     $q->where('recap_month', '=', $sr);
                 }
                 return $q;
             })->get();
```

## Cek per file
- [ ] Buka export dengan `DB::enableQueryLog()` (tinker/telescope) → jumlah query TETAP
      konstan meski jumlah recap bertambah (bukan naik linear).
- [ ] File Excel `recap-*.xlsx` kolom "Tipe Potongan Absen" tetap terisi benar.

---

## Verifikasi
- [ ] `php -l app/Http/Controllers/Admin/SalaryRecapCrudController.php` bersih (kalau file PHP)
- [ ] `php -d memory_limit=2G -d xdebug.mode=off vendor/bin/phpunit --no-coverage` → tetap hijau (baseline)
- [ ] `node tests/browser/crud-suite.mjs` → tetap hijau (baseline 146)
- [ ] Verifikasi manual di browser sesuai bagian "Cek" di atas
- [ ] Flip `Status:` ke `[x] DONE` + isi commit SHA setelah semua centang
