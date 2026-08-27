# F1 — Backpack CRUD 6.5 → 6.8 (di Laravel 10)

**Status:** [x] DONE · commit: `(uncommitted)` · Estimasi: 0.5–1 hari → **aktual ~1 jam**
**Temuan F0 yang mengubah rencana:** Backpack 7 = Laravel 12-only (tak ada BP7 utk L10/11).
Tapi **Backpack 6.8.16 mendukung `^10|^11|^12`** → cukup naik minor 6.5→6.8, TIDAK perlu BP7
untuk sampai Laravel 12. Critical-path Backpack 7 hilang; upgrade jadi jauh lebih murah.

## Langkah
- [ ] Bump minor: `composer require backpack/crud:^6.8 backpack/theme-tabler:^1.2 backpack/basset:^1.2 -W`
- [ ] `php artisan config:clear && view:clear && route:clear`
- [ ] Verifikasi 37 CRUD controller render (list/create/edit/show) — API fluent BP6 stabil antar minor, risiko rendah
- [ ] Verifikasi 20 custom view `vendor/backpack/**` masih cocok (BP6 minor jarang ubah DOM)
- [ ] PHPUnit hijau + crud-suite browser hijau

## Kenapa bukan Backpack 7 sekarang
BP7 butuh `doctrine/dbal ^4` + theme-tabler 2 (DOM baru) + Laravel 12. Menaikkan BP7
**bersamaan** dengan Laravel 12 di F3 lebih efisien daripada 2 kali refactor view.
Backpack 6→7 dijadikan **fase opsional terpisah** setelah Laravel 12 stabil (bila diinginkan).

## Kriteria selesai
- `composer show backpack/crud` = 6.8.x
- Semua modul render tanpa error di Laravel 10
- 403 PHPUnit + 146 browser tetap hijau
