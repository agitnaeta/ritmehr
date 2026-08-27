# T3 — CI Guardrail (GitHub Actions)

**Status:** [x] DONE · commit: `(uncommitted)` · Estimasi: 1–2 hari → **aktual ~20 menit**
**Tujuan:** kunci hasil upgrade supaya tak diam-diam regres; jalankan test tiap PR.

## Yang dikerjakan
- [x] `.github/workflows/ci.yml`:
      - Trigger: push ke `master`/`upgrade/**`, PR ke `master`
      - Service **MySQL 8.0** (db `absensi_testing`, root/secret, health-check)
      - Matrix **PHP 8.2 & 8.3** (setup-php + ekstensi mbstring/pdo_mysql/bcmath/gd/zip/intl/exif)
      - Cache Composer (vendor by composer.lock hash)
      - `composer install` → `key:generate` → set DB creds ke `.env` (phpunit.xml hanya
        override DB_DATABASE; host/port/user/pass diambil dari `.env`)
      - `php artisan migrate --force`
      - **`php -d memory_limit=2G -d xdebug.mode=off vendor/bin/phpunit --no-coverage`**
        (bukan `artisan test` — OOM di app ini)

## Validasi lokal (bukti CI akan hijau)
- [x] `CREATE DATABASE absensi_testing` + `migrate:fresh --force` → semua migrasi DONE
      (termasuk training/ter/salary-breakdown terbaru)
- [x] PHPUnit penuh **403/403 hijau**

## Hasil CI nyata (run 33047691017)
- [x] Push branch → **CI HIJAU di PHP 8.2 & 8.3** (conclusion=success)
- [x] 2 bug lingkungan CI diperbaiki: Vite build + pymupdf (lihat F4)
- [x] Badge CI di README → sudah ditambah (F4)
- [ ] (Opsional, manual) Branch protection `master` → set di GitHub Settings

## Kriteria selesai
- [x] Workflow ditulis + tervalidasi lokal (migrate + phpunit hijau)
- [x] Run Actions nyata HIJAU di 8.2 & 8.3 (juga hijau di master pasca-merge)
- [x] Badge CI done; branch protection = opsional/manual
