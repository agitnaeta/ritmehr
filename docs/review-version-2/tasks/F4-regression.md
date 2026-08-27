# F4 — Regresi Penuh & Rilis

**Status:** [x] DONE (regresi + CI hijau) · PR menunggu merge
**Prasyarat:** F3 + T1/T2/T3 selesai.

## Regresi final (semua hijau di Laravel 12.68.0)
- [x] PHPUnit **403/403 hijau** (lokal)
- [x] Browser crud-suite **146/146 hijau** — termasuk cetak slip gaji `200 application/pdf` (dompdf 3)
- [x] **CI GitHub Actions HIJAU** di PHP 8.2 & 8.3 (run 33047691017, conclusion=success)

## CI: bug ditemukan & diperbaiki saat push nyata
Push pertama gagal — 2 akar masalah lingkungan CI (tak muncul lokal karena artifact sudah ada):
1. **Vite manifest not found** → ~26 test render-view gagal 500. Fix: step `npm ci && npm run build` (public/build di-gitignore, sengaja di-build di CI).
2. **CvExtractionTest** (4 gagal) → `cv_text=null` karena `scripts/extract_cv.py` butuh `import fitz`. Fix: step `pip install pymupdf`.
Setelah 2 fix → **CI hijau penuh** di kedua versi PHP.

## Rilis
- [x] README: badge Laravel 10→**12**, PHP 8.2+, tambah **badge CI**, teks "Dibangun dengan Laravel 12"
- [x] Branch `upgrade/laravel-12` di-push, CI hijau
- [x] **PR #32 → merged ke master** (commit `88b2f27`); branch upgrade dihapus
- [ ] (Opsional, manual) Branch protection master: wajib CI hijau sebelum merge
- [x] Data demo dibersihkan dari sisa test (ZZ%)

## Kriteria selesai
- [x] Semua lapis test hijau di Laravel 12 (lokal + CI 8.2/8.3)
- [x] README tercermin kondisi baru
- [~] Merge ke master → via PR
