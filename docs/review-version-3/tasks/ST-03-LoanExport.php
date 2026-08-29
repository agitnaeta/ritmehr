# ST-03 — `app/Exports/LoanExport.php`

**Fokus:** PERF-7 — FromQuery + chunk
**Severity:** 🟡 Sedang
**Status:** [x] DONE — commit: `pending` (terverifikasi 2026-08-29)
**File (satu-satunya) yang disentuh:** `app/Exports/LoanExport.php`

---

## Masalah
`LoanExport implements FromCollection` → sama seperti ST-02, materialisasi penuh.

## Arah perubahan
Ubah ke `FromQuery` + `WithChunkReading` (1000). Pertahankan `WithHeadings`.

## Cek per file
- [ ] Export loan besar → memori rata, isi & heading benar.

---

## Verifikasi
- [ ] `php -l app/Exports/LoanExport.php` bersih (kalau file PHP)
- [ ] `php -d memory_limit=2G -d xdebug.mode=off vendor/bin/phpunit --no-coverage` → tetap hijau (baseline)
- [ ] `node tests/browser/crud-suite.mjs` → tetap hijau (baseline 146)
- [ ] Verifikasi manual di browser sesuai bagian "Cek" di atas
- [ ] Flip `Status:` ke `[x] DONE` + isi commit SHA setelah semua centang
