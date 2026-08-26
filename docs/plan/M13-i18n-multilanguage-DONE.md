# M13 — Multi-language (i18n) ✅ DONE (fondasi)

> **Status:** ✅ FONDASI IMPLEMENTED & TESTED (2026-08-24) · **Prioritas:** 🟠 CC-1 (E6).
> **Catatan:** fondasi i18n + switcher + terjemahan menu inti selesai. Terjemahan
> label per-modul (form/kolom CRUD) dilanjutkan bertahap.

## Hasil Implementasi
- **Kolom `users.locale`** (nullable) — preferensi bahasa per user.
- **Lang files** `lang/id/*` + `lang/en/*`: `menu.php` (semua menu sidebar) & `common.php` (Simpan/Batal/Tanggal/Jumlah/dll).
- **Middleware `SetLocale`** (di web group): resolusi locale per request — preferensi user → session → default M15 (`default_locale`) → `config('app.locale')`. Supported: id, en.
- **`LocaleController@switch`** + route `/locale/{locale}`: simpan pilihan ke user (lintas device) + session (efek langsung). Locale tak didukung → 404.
- **Language switcher di topbar** (🇮🇩/🇬🇧) menampilkan locale aktif.
- **Menu sidebar** seluruh judul top-level pakai `__('menu.*')` → ID↔EN.

## Automation Test
- **PHPUnit** `LocaleSwitchTest` — 4/4 (switch persist ke user+session, locale tak didukung ditolak 404, middleware menerapkan locale user, terjemahan menu 2 arah).
- **Playwright** `m13-i18n.mjs` — 5/5 (switcher tampil, default ID, switch→EN menu berubah, persist antar halaman, balik ke ID).
- **Regression:** `php artisan test` → **177 passed (378 assertions)**, nol regresi.

## Sisa (bertahap, bukan blocker)
- **Terjemahan label isi halaman DITUNDA (keputusan Capt, 2026-08-24):** saat ini baru menu sidebar + switcher yang EN; isi halaman (label form/kolom CRUD, tombol, judul di ~30 controller & blade custom) masih hardcode ID. Dilanjutkan per-modul saat modulnya disentuh. Fondasi (`__()`, lang files, middleware, switcher) sudah siap dipakai.
- Format tanggal Carbon `->locale()` & angka mengikuti locale (sebagian ikut M14 currency).

## Definition of Done (fondasi) — tercapai ✅
- User bisa ganti bahasa ID↔EN, menu inti + switcher jalan, preferensi tersimpan per user.

---

## Rencana Awal (arsip)


## Ringkasan
Sistem sekarang `locale='id'` tapi label Bahasa Indonesia **di-hardcode** di controller
& blade, dan folder `lang/` cuma punya `lang/en/` bawaan Laravel. Modul ini menjadikan
seluruh UI translatable dengan language switcher.

## Evaluasi Bisnis (7 Poin)

- **E1. Kelengkapan proses bisnis** — ❌ Tidak bisa ganti bahasa. Tak ada `lang/id/`, tak ada switcher. Label campur hardcode.
- **E2. Integrasi keluar** — ➖ N/A (murni internal).
- **E3. Best-practice tampilan** — Language switcher di topbar (admin) & portal. Simpan preferensi bahasa per user.
- **E4. Third-party config** — Default locale sistem diatur di M15 (Pengaturan > Lokalisasi).
- **E5. Keterkaitan antar fitur** — Menyentuh SEMUA modul (setiap label). Dikerjakan setelah fondasi data (M15/M05/M12) stabil agar tak rework label yang masih berubah.
- **E6. Bahasa** — 🔴 INI fokusnya. Target: minimal ID + EN penuh.
- **E7. Currency** — Format angka/tanggal ikut locale (terkait M14).

## Gap & Temuan
- `config/app.php`: `locale='id'`, `fallback_locale='en'`, tapi hanya `lang/en/{validation,passwords,pagination,auth}.php`.
- Label ID hardcode di banyak controller (mis. `SalaryCrudController`, menu blade, alert).

## Task Breakdown
1. Buat struktur `lang/id/` + `lang/en/` untuk domain: menu, crud, payroll, leave, tax, document, notification.
2. Ganti string hardcode → `__('...')` / `trans('...')` bertahap per modul.
3. Middleware `SetLocale` (baca preferensi user → session → default M15).
4. Kolom `users.locale` + switcher di topbar & portal.
5. Pastikan tanggal (Carbon `->locale()`) & angka mengikuti locale aktif.

## Definition of Done
- User bisa ganti bahasa ID↔EN, seluruh UI inti ikut berubah.
- Tidak ada label inti yang hardcode (minimal modul utama).
- Preferensi bahasa tersimpan per user.
