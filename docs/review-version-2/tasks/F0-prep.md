# F0 — Prep & Baseline (jaring pengaman)

**Status:** [x] DONE · Estimasi: 0.5–1 hari
**Tujuan:** kunci kondisi hijau saat ini sebelum menyentuh apa pun.

## Hasil
- [x] Branch `upgrade/laravel-12` dibuat dari master
- [x] Backup DB → `storage/app/backups/pre-upgrade.sql` (2094 baris)
- [x] Baseline PHPUnit **403/403 hijau**
- [x] Baseline browser **146/146 hijau** (crud-suite)
- [x] Snapshot dependency → `docs/review-version-2/_baseline-deps.txt`
- [x] Blocker terverifikasi

## Temuan kunci (MENGUBAH RENCANA)
`composer why-not backpack/crud 7.1` + packagist p2:
- **Backpack 7 = Laravel ^12 saja** — TIDAK ada Backpack 7 untuk L10/L11.
- **Backpack 6.8.16 mendukung `^10|^11|^12`** → cukup naik minor 6.5→6.8 untuk sampai L12.
- **Dampak:** critical-path "Backpack 6→7" HILANG. Upgrade jauh lebih murah:
  - F1 turun dari 3–5 hari → **0.5–1 hari** (minor bump)
  - Backpack 6→7 jadi fase OPSIONAL terpisah (butuh doctrine/dbal 4 + theme-tabler 2 + L12)

## Revisi estimasi total
Upgrade Laravel: **~8–13 hari → ~4–7 hari** (Backpack 7 tak lagi di jalur kritis).

## Kriteria selesai
- [x] Semua test hijau tercatat sebagai baseline
- [x] Daftar blocker terdokumentasi → rencana direvisi (F1, masukan-teknis RV2-002)
