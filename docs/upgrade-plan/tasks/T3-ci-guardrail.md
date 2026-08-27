# T3 — CI Guardrail (GitHub Actions)

**Status:** [ ] TODO · Estimasi: 1–2 hari
**Tujuan:** kunci hasil upgrade supaya tak diam-diam regres; jalankan test tiap PR.

## Langkah
- [ ] Buat `.github/workflows/ci.yml`:
      - Service MySQL 8 (port 3307 / atau default + env)
      - Matrix PHP: `8.2`, `8.3`
      - `composer install`
      - `php artisan migrate --force` ke `absensi_testing`
      - Jalankan `phpunit` dgn flag memory (bukan `artisan test` — OOM)
      - (Opsional job kedua) `php artisan serve` + Playwright `crud-suite.mjs`/`ui-test.mjs`
- [ ] Cache composer & node_modules untuk kecepatan
- [ ] Badge status CI di README (ganti/temani badge "tests 403 passing" statis)
- [ ] Branch protection `master`: wajib CI hijau sebelum merge

## Kriteria selesai
- PR memicu CI otomatis, phpunit hijau di PHP 8.2 & 8.3
- Badge CI tampil di README
- Merge ke master butuh CI lulus
