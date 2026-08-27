# F3 — Laravel 11 → 12

**Status:** [ ] TODO · Estimasi: 1–2 hari (low-friction)
**Prasyarat:** F2 selesai (L11 hijau).

## Langkah
- [ ] `composer why-not laravel/framework 12.*` — pastikan tak ada penahan tersisa
- [ ] Bump: `composer require laravel/framework:^12.0 -W`
- [ ] Naikkan sisa dep yang minta L12 (biasanya minor)
- [ ] Ikuti Laravel 12 upgrade guide (rilis 12 relatif ringan dari 11)
- [ ] Clear semua cache, regen autoload
- [ ] Jalankan PHPUnit + perbaiki deprecation

## Catatan
- Laravel 12 mendukung PHP 8.2+; bila environment siap, pertimbangkan PHP 8.3 (keputusan #2 di README)
- Rilis 11→12 sengaja dibuat minim breaking oleh tim Laravel — sebagian besar effort adalah dep bumps

## Kriteria selesai
- `php artisan --version` = 12.x
- `composer outdated --direct` bersih dari mayor yang tertinggal (selain yang sengaja dipin)
- PHPUnit hijau
