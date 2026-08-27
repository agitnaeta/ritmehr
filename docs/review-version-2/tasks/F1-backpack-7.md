# F1 — Backpack CRUD 6 → 7 (di Laravel 10) · CRITICAL PATH

**Status:** [ ] TODO · Estimasi: 3–5 hari
**Kenapa duluan:** Backpack 6 tak support Laravel 12. Naikkan ke 7 selagi masih di L10 supaya breaking change Backpack terisolasi & test lama masih jadi jaring pengaman.

## Langkah
- [ ] Baca upgrade guide resmi Backpack 6→7 (view namespace, operation, field/column changes)
- [ ] Bump paket:
      ```
      composer require backpack/crud:^7.0 backpack/theme-tabler:^2.0 backpack/basset:^2.0 -W
      ```
- [ ] Jalankan `php artisan backpack:upgrade` bila tersedia
- [ ] Publish ulang config/asset Backpack yang berubah; bandingkan dengan yang lama
- [ ] **20 custom view** `resources/views/vendor/backpack/**` — cek tiap file terhadap struktur baru theme-tabler 2 (tombol `user_import`, `salary_import`, `user_export`, `user-print`, dll)
- [ ] **37 CRUD controller** — verifikasi API fluent masih valid: `addColumn/addField/->type()/addButtonFromView/setupListOperation`. Perbaiki per modul.
- [ ] Fokus khusus modul yang baru & kompleks: SalaryCrud (kolom number+priority QW-02), UserCrud (import op IMP-03), SetupWizard, Import views
- [ ] Render manual tiap list admin — pastikan `#crudTable` AJAX tetap jalan

## Pitfalls
- Filter berbayar tetap dihindari — pertahankan trait `HasSimpleFilters`
- Guard `backpack` vs `web` (Spatie) — jangan berubah saat upgrade
- Jangan campur langkah ini dengan upgrade Laravel; selesaikan Backpack dulu sampai hijau

## Kriteria selesai
- `composer show backpack/crud` = 7.x
- Semua list/create/edit/show 37 modul render tanpa error di L10
- PHPUnit tetap hijau (browser suite mungkin merah → ditangani di T2)
