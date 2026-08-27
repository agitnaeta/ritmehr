# F3 — Laravel 11 → 12

**Status:** [x] DONE · commit: `(uncommitted)` · Estimasi 1–2 hari → **aktual ~30 menit**
**Hasil:** Laravel **12.68.0** + PHPUnit **403/403 hijau**. Backpack tetap 6.8 (tak perlu BP7).

## Yang dikerjakan
- [x] `composer why-not laravel/framework 12.0` → hanya collision yg menahan
- [x] `composer.json`: laravel ^12, collision ^8.8 (v8.9.5 support L12), phpunit ^11 (bareng T1)
- [x] `composer update -W` → Laravel 12.68.0; **security advisories 3→0**
- [x] PHPUnit 403/403 hijau

## Catatan
- Backpack 6.8.16 jalan mulus di Laravel 12 — konfirmasi temuan F0 (tak perlu Backpack 7).
- collision v8.9.5 ternyata sudah support L12 (constraint `>=12` di-relax di 8.9.x).
- PHPUnit dinaikkan ke 11 di fase ini karena collision 8.9 menariknya → T1 menyatu di sini.

## Kriteria selesai
- [x] `php artisan --version` = 12.68.0
- [x] `composer outdated --direct` bersih dari mayor tertinggal (selain yg sengaja dipin)
- [x] PHPUnit hijau
