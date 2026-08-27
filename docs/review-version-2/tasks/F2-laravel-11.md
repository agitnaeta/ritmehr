# F2 — Laravel 10 → 11

**Status:** [ ] TODO · Estimasi: 2–3 hari
**Prasyarat:** F1 selesai (Backpack 7 hijau di L10).

## Langkah
- [ ] Bump framework + paket first-party:
      ```
      composer require laravel/framework:^11.0 laravel/sanctum:^4.0 laravel/tinker:^3.0 nunomaduro/collision:^8.0 -W
      composer require --dev phpunit/phpunit:^11.0 -W   # lihat T1
      ```
- [ ] Naikkan dep lain yang menahan: guzzle ^8, dompdf ^3, spatie/laravel-backup ^9, spatie/laravel-ignition ^2.12
- [ ] Ikuti Laravel 11 upgrade guide. Poin yang relevan utk repo ini:
      - Sanctum 3→4: publish config baru, jalankan migration token bila perlu
      - Password rehash & default config changes
      - `config/*` yang di-override — bandingkan dengan default L11
- [ ] **Skeleton:** BIARKAN struktur lama (Kernel.php, Handler.php, bootstrap/app.php lama). L11 tetap jalan dgn ini. Migrasi ke skeleton slim = iterasi terpisah (lihat keputusan #1 di README).
- [ ] `php artisan config:clear && php artisan route:clear && php artisan view:clear`
- [ ] Jalankan PHPUnit — perbaiki deprecations yang muncul

## Pitfalls
- `dompdf 2→3`: cek output slip gaji & kartu ID (barryvdh wrapper)
- Guzzle 8: cek gateway WAHA/Fonnte masih jalan
- 11 console command — schedule masih di `app/Console/Kernel.php` (tak wajib pindah ke routes/console.php)

## Kriteria selesai
- `php artisan --version` = 11.x
- PHPUnit hijau
- Smoke manual: login admin, list, cetak 1 slip PDF, 1 alur portal
