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

## Sisa (manual, butuh akses GitHub)
- [ ] Push branch → cek run Actions hijau di PHP 8.2 & 8.3
- [ ] Tambah badge CI ke README (ganti/temani badge "tests 403 passing" statis)
- [ ] Branch protection `master`: wajib CI hijau sebelum merge

## Kriteria selesai
- [x] Workflow ditulis + tervalidasi lokal (migrate + phpunit hijau)
- [~] Run Actions nyata + badge + branch protection → saat push ke GitHub (F4)
