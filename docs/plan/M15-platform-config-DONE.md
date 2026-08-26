# M15 — Platform Configuration & Setup ✅ DONE

> **Status:** ✅ IMPLEMENTED & TESTED (2026-08-24) · **Prioritas:** 🔴 Fondasi (urutan #1)
> **Alasan duluan:** Semua third-party (E4) & onboarding (CC-5) bermuara di sini.

## Hasil Implementasi
- Tabel `settings` (key/value, group, type, is_encrypted) + `Setting` model.
- `SettingService` (cache, enkripsi secret at-rest, fallback ke config/.env, definisi 12 setting dalam 6 grup).
- Helper global `setting('key', default)` (autoloaded via composer files).
- UI `admin/settings` (super-admin only, guard ganda: route intent + controller `hasRole`), 6 tab, panel status integrasi live (ACC/WA/storage), secret write-only (masked `********`, tak pernah dikirim ke browser, tak terhapus saat re-save kosong).
- Menu "Pengaturan > Pengaturan Sistem" (super_admin).
- Konsumen `.env` dipindah ke `setting()`: `TransactionService` (acc_active), `Acc` (host/key), `PresenceService` (geofence lat/lng/radius), `AppServiceProvider` (Fonnte token + toggle WA).
- Onboarding (CC-5): `DatabaseSeeder` sekarang panggil `HrisSeeder` + command idempotent `hris:install`.

## Automation Test (Playwright)
`tests/browser/m15-settings.mjs` — **7/7 PASS**:
- TC-SET-05 menu tampil untuk super_admin
- TC-SET-10 halaman + 6 tab + panel status
- TC-SET-11 simpan office_lat persist & tampil ulang
- TC-SET-12 token rahasia tidak bocor ke browser (masked)
- TC-SET-13 re-save masked tidak menghapus secret lama
- TC-SET-01 manager diblokir (HTTP 403)
- TC-SET-01b menu tidak bocor ke manager

Regression: `NotificationServiceTest`, `DashboardServiceTest`, `MultiBranchTest` tetap hijau.

---

## Rencana Awal (arsip)

## Ringkasan
Halaman **Pengaturan Sistem** yang bisa diatur super admin untuk mengelola semua
konfigurasi yang sekarang tersebar di `.env`: gateway WhatsApp, storage, koordinat
kantor, toggle akuntansi, currency & locale default. Plus auto-seed onboarding.

## Evaluasi Bisnis (7 Poin)

- **E1. Kelengkapan proses bisnis** — ❌ Belum ada. Sekarang config = edit `.env` + `php artisan config:cache` (bukan proses bisnis, tugas developer). Super admin tak bisa atur apa pun dari UI.
- **E2. Integrasi keluar** — ⚠️ Halaman ini justru yang MENGONTROL integrasi luar (toggle & kredensial). Nilai kredensial disimpan di DB (encrypted), bukan `.env`.
- **E3. Best-practice tampilan** — Form pengaturan berkelompok (tab: Umum, Lokasi, Notifikasi, Storage, Akuntansi, Lokalisasi). Bukan satu form panjang.
- **E4. Third-party config** — ✅ INI DIA solusinya. Semua kunci pihak ketiga (Fonnte token, ACC host/key, storage driver, S3 creds) pindah ke tabel `settings` + UI.
- **E5. Keterkaitan antar fitur** — Tinggi. M03 (notif WA), M06 (storage dokumen), M07 (koordinat), M12 (akuntansi), M13/M14 (locale/currency) semua baca dari sini.
- **E6. Bahasa** — Label halaman ini harus ikut i18n (setelah M13).
- **E7. Currency** — Menyimpan default currency & locale sistem (dipakai M14).

## Gap & Temuan
- `DatabaseSeeder::run()` KOSONG → `HrisSeeder` tak jalan otomatis (CC-5).
- Kredensial third-party plaintext di `.env`: `ACC_HOST/KEY/ACTIVE`, `services.fonnte.token`, `LAT/LNG`.

## Task Breakdown
1. Tabel `settings` (key, value terenkripsi, group, type) + `SettingService` dengan cache.
2. Helper `setting('key', default)` yang fallback ke `config()`/`.env`.
3. CRUD/Form UI `admin/settings` (guard `super_admin`), tab per grup.
4. Migrasikan pembaca `.env` → `setting()`: `TransactionService`, `FonnteWhatsAppGateway`, `PresenceService` (LAT/LNG), storage disk.
5. Panggil `HrisSeeder` dari `DatabaseSeeder` + command `hris:install` idempotent.
6. Indikator status koneksi (ACC aktif?, WA token terisi?, storage writable?) di halaman.

## Definition of Done
- Super admin ubah token WA / koordinat / toggle ACC dari UI, langsung efektif tanpa deploy.
- Fresh install → `migrate --seed` otomatis punya role + data referensi.
- Tidak ada kredensial third-party yang wajib diisi di `.env` untuk fitur inti.
