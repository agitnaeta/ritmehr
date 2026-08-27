# F0 — Prep & Baseline (jaring pengaman)

**Status:** [ ] TODO · Estimasi: 0.5–1 hari
**Tujuan:** kunci kondisi hijau saat ini sebelum menyentuh apa pun.

## Langkah
- [ ] Buat branch `upgrade/laravel-12` dari `master`
- [ ] Backup DB: `docker exec absensi-mysql mysqldump -uroot -psecret --single-transaction absensi > storage/app/backups/pre-upgrade.sql`
- [ ] Baseline test PHPUnit hijau & catat angka:
      `php -d memory_limit=2G -d xdebug.mode=off vendor/bin/phpunit --no-coverage` (target: 403 lulus)
- [ ] Baseline browser hijau: `php artisan serve` lalu `node tests/browser/crud-suite.mjs` (146) + `node tests/browser/ui-test.mjs`
- [ ] Snapshot dependency: `composer show > docs/upgrade-plan/_baseline-deps.txt`
- [ ] Cek blocker L12 lebih dulu: `composer why-not laravel/framework 12.*` — catat paket yang menahan
- [ ] Cek blocker Backpack 7: `composer why-not backpack/crud 7.*`

## Kriteria selesai
- Semua test hijau tercatat sebagai baseline
- Daftar paket penahan L12 & Backpack 7 terdokumentasi (jadi input F1–F3)
