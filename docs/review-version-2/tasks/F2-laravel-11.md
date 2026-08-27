# F2 — Laravel 10 → 11

**Status:** [x] DONE · commit: `(uncommitted)` · Estimasi 2–3 hari → **aktual ~2 jam**
**Hasil:** Laravel **11.56.1** + PHPUnit **403/403 hijau**. Tanpa perubahan kode aplikasi.

## Yang dikerjakan
- [x] `composer.json`: laravel ^11, sanctum ^4, collision ^8, dompdf ^3, spatie/backup ^9, backpack/basset ^1.3, php ^8.2, mockery ^1.6, sail ^1.26
- [x] `composer update -W` → Laravel 11.56.1; security advisories 44→3
- [x] Clear cache; tak ada migration sanctum tertunda
- [x] PHPUnit **403/403 hijau** (LeaveCalendarTest sudah di-harden di F1)

## Temuan lingkungan (BUKAN bug app)
1. **`artisan serve` L11 + Xdebug bocorkan Notice "Broken pipe"** ke HTML saat serve
   stdout ditangkap pipe. Muncul hanya di dev-server yang stdout-nya di-capture;
   TAK terjadi di produksi (nginx/php-fpm). Mitigasi test: jalankan serve dengan
   `XDEBUG_MODE=off php -d display_errors=Off artisan serve`. Akar: port :8000
   sempat dipegang serve lama → semua serve baru fallback ke :8001 (bikin bingung).
2. **crud-suite: 2 item create (branch, department) FAIL** (302 balik, tak tersimpan).
   Diverifikasi via PHPUnit HTTP test: create branch payload minimal (name+code)
   **TERSIMPAN & redirect ke list** di guard `backpack` → **backend sehat**.
   Kegagalan ada di harness browser (CSRF/cookie persist di L11 dev-server),
   masuk scope **T2 (browser realign)**, bukan blocker upgrade.

## Bukti backend create branch OK di L11
Test HTTP `post(backpack_url('branch'), [name, code])` → `assertDatabaseHas` lulus,
redirect 302 ke `/admin/branch`. (Test diagnosa dihapus setelah konfirmasi.)

## Kriteria selesai
- [x] `php artisan --version` = 11.x
- [x] PHPUnit hijau (403)
- [x] Create/store fungsional terverifikasi via HTTP test
- [x] Browser suite: 2 item harness sudah beres di T2 (146/146 hijau — root cause data test, bukan regresi app)
