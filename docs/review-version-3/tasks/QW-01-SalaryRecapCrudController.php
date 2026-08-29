# QW-01 — `app/Http/Controllers/Admin/SalaryRecapCrudController.php`

**Fokus:** PERF-5 — hilangkan N+1 di export gaji
**Severity:** 🟠 Tinggi
**Status:** [x] DONE — commit: `pending` (terverifikasi 2026-08-29)
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
- [x] `php -l ...SalaryRecapCrudController.php` → "No syntax errors detected"
- [x] Query count turun & konstan (proof di bawah)
- [x] Output Excel tetap benar (`fine_type` terisi)
- [x] `node tests/browser/crud-suite.mjs` → **146 PASS**, Penggajian 4/4
- [x] Flip `Status:` ke `[x] DONE`

## PROOF (2026-08-29)

### 1. Query count — N+1 hilang (DB::getQueryLog, map() semua baris)
```
jumlah recap: 5
LAMA  with(['user'])        -> 7 query saat map()   (2 base + 5 lazy-load salary = N+1)
BARU  with(['user.salary']) -> 3 query saat map()   (recap + user + salary, KONSTAN)
```
→ Dengan 5 baris saja sudah hemat 4 query. Skala linear: 100 karyawan lama = ~102 query,
  baru tetap 3. Ini murni pengurangan beban CPU/DB di request export.

### 2. Output Excel tetap benar
```
nama: Siti Rahayu
tipe potongan (fine_type): [minute]   ← relasi user.salary ter-eager-load, terisi benar
gaji: 25000000, diterima: 25057500
map() OK, 15 kolom
```

### 3. Regresi
- crud-suite: **146/146**, Penggajian **4/4** lulus.
- Tak ada perubahan logika, hanya kedalaman eager-load → PHPUnit baseline tak terpengaruh.
