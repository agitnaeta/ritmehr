# M08 — Reporting & Dashboard ✅ DONE (polish)

> **Status:** ✅ DONE + POLISHED (2026-08-24) · **Prioritas:** 🟡 polish

## Evaluasi ulang (verifikasi ke KODE, bukan label)
- **E1 proses bisnis** ✅ — Dashboard cards (hari ini + bulan ini) + tren + latecomers + cuti minggu ini + headcount. Report: attendance/salary/loan/headcount + leave-report + tax-report annual/bpjs. `DashboardService` agregasi data nyata (teruji di `DashboardServiceTest`).
- **E3 tampilan** ✅ — tren pakai **Chart.js** (line, dual-axis kehadiran% + keterlambatan), bukan tabel. Leave calendar di M02. Sudah best-practice.
- **E5 keterkaitan** — 🔴 **gap ditemukan & diperbaiki (Polish #1 & #2).** Report tersebar di 3 lokasi (`/report/*`, `leave-report`, `tax-report/*`) tanpa menu terpadu, DAN grup route `report/*` **tanpa middleware permission** (siapa pun yang login bisa akses).
- **E6 bahasa** ✅ (bertahap) — judul menu `Laporan`/`Reports` via `__('menu.reports')` (id+en). Sub-item masih label ID (ikut pola M13 bertahap).
- **E7 currency** — 🔴 **gap ditemukan & diperbaiki (Polish #3).** Dashboard hardcode `'Rp ' . number_format()` padahal M14 punya `money()`/`@money` yang baca `setting('default_currency')`. Report views sendiri sudah pakai `@rupiah` (aliased ke `money()`).

## Polish yang dikerjakan
1. **Guard route report (E5 + keamanan)** — grup `report/*` (attendance/salary/loan/headcount) kini dibungkus `middleware => 'permission:report.view'`. Sebelumnya tanpa gate → employee tanpa `report.view` bisa buka. Permission `report.view`/`report.export` memang sudah ada di seeder & ter-assign ke super_admin/hr_admin/manager — tinggal dipakai.
2. **Menu "Laporan" terpadu (E5)** — dropdown sidebar baru `__('menu.reports')` (ikon `la la-chart-bar`), mengumpulkan SEMUA laporan (Kehadiran, Gaji, Kasbon, Headcount, Rekap Cuti, Pajak Tahunan, BPJS Bulanan) di satu tempat, sub-item A-Z, tiap link tetap ter-gate izinnya masing-masing (`report.view` / `tax.view` / `leave.view_all`). Link report yang lama dihapus dari menu Cuti & Pajak (tak ada duplikat). Sebelumnya report attendance/salary/loan/headcount **tak ada di sidebar sama sekali** — cuma bisa lewat tombol di dashboard.
3. **Dashboard ikut currency setting (E7)** — 4 kartu uang (Total Gaji/Lembur/Potongan/Sisa Kasbon) ganti dari `'Rp ' . number_format()` ke `money()`. Ganti mata uang di Settings → dashboard otomatis ikut (IDR→Rp, USD→$), tak lagi hardcode.

## Automation Test
- **PHPUnit** `ReportingDashboardTest` — 10/10 baru: report/* butuh `report.view` (403 tanpa izin × 4 route), terbuka 200 dengan izin (× 4), guest di-redirect, dashboard money ikut currency setting (IDR "Rp" → USD "$").
- **PHPUnit** `DashboardServiceTest` — existing tetap hijau (agregasi today/month/trend/report).
- **Playwright** `m08-reports-menu.mjs` — 5/5 (satu dropdown Reports memuat semua link, lengkap 7 laporan by-href, tak ada duplikat di menu Cuti/Pajak, semua laporan buka 200, dashboard pakai simbol mata uang). Catatan: user demo locale EN → judul render "Reports" (id: "Laporan"); test match by struktur/href, bukan label.
- **Regression:** `php artisan test` → **243 passed (509 assertions)** (naik dari 233; +10 test baru), nol regresi.

## Definition of Done — tercapai ✅
- Report terpusat di satu menu + ter-gate izin; dashboard chart + currency-aware; i18n judul menu. Sub-item i18n & E6 penuh menyusul via M13 bertahap. Semua modul plan (M01–M08) kini ✅ DONE.

---

## Evaluasi Awal (arsip)

## Ringkasan
Dashboard (override Backpack) + report attendance/salary/loan/headcount, tax-report
annual/bpjs. `DashboardService` agregasi data nyata.

## Evaluasi Bisnis (7 Poin)
- **E1. Proses bisnis** — ✅ Dashboard cards + report utama ada, filter bulan/dept/cabang. Export sebagian.
- **E2. Integrasi keluar** — ➖ Internal.
- **E3. Tampilan** — ⚠️ Cek chart (Chart.js/ApexCharts) sudah dipakai untuk tren, bukan cuma tabel. Leave calendar sudah ada di M02. Data waktu → chart/kalender = best practice.
- **E4. Third-party config** — ➖ Tidak.
- **E5. Keterkaitan** — 🔴 Konsumen SEMUA modul (M01-M07). Konsistensi: report leave ada di `leave-report`, tax di `tax-report` (bukan di grup `/admin/report/*`). **Rapikan lokasi/menu report** biar seamless.
- **E6. Bahasa** — ⚠️ Hardcode ID → M13.
- **E7. Currency** — ⚠️ Angka gaji "Rp" hardcode → M14.

## Polish Task
1. Satukan lokasi/menu semua report (attendance/salary/loan/leave/tax/bpjs/headcount).
2. Pastikan tren pakai chart. i18n (M13), currency (M14).

## Definition of Done
- Sudah jalan; polish: konsolidasi report + chart + i18n/currency.
