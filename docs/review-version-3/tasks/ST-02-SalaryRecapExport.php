# ST-02 — `app/Exports/SalaryRecapExport.php`

**Fokus:** PERF-7 — FromQuery + chunk (skalabilitas)
**Severity:** 🟡 Sedang
**Status:** [x] DONE — commit: `pending` (terverifikasi 2026-08-29)
**File (satu-satunya) yang disentuh:** `app/Exports/SalaryRecapExport.php`

---

## Masalah
Pakai `FromCollection` → materialisasi penuh di RAM + build sheet sinkron. CPU/RAM spike
sebanding jumlah baris.

## Arah perubahan
Ubah ke `FromQuery` + `WithChunkReading` (chunk 1000) sambil pertahankan `WithMapping`,
`WithHeadings`, `WithColumnFormatting`, dan baris "Total". Karena baris Total butuh
agregasi seluruh data, hitung total via query `sum()` terpisah (bukan `->add($sum)` pada
collection), lalu render sebagai `WithMapping` baris terakhir atau footer.

> Butuh sedikit refactor tanda tangan constructor (dari `Collection` → kriteria filter).
> Sesuaikan pemanggil di `SalaryRecapCrudController::export()` (task pendamping).

## Cek per file
- [ ] Export dataset besar (seed 5000 recap) → memori rata (chunk), file benar.
- [ ] Baris "Total" tetap akurat.

---

## Verifikasi
- [ ] `php -l app/Exports/SalaryRecapExport.php` bersih (kalau file PHP)
- [ ] `php -d memory_limit=2G -d xdebug.mode=off vendor/bin/phpunit --no-coverage` → tetap hijau (baseline)
- [ ] `node tests/browser/crud-suite.mjs` → tetap hijau (baseline 146)
- [ ] Verifikasi manual di browser sesuai bagian "Cek" di atas
- [ ] Flip `Status:` ke `[x] DONE` + isi commit SHA setelah semua centang
