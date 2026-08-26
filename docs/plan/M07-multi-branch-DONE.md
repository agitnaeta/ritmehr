# M07 — Multi-Branch ✅ DONE (polish)

> **Status:** ✅ DONE + POLISHED (2026-08-24) · **Prioritas:** 🟡 polish

## Evaluasi Flow Business (verifikasi ke kode)
- **E1 proses bisnis** ✅ — CRUD cabang, assign user (User CRUD), presensi auto-set `branch_id` + geofence per cabang (`PresenceService::branchFor`/`inCoordinate`), dashboard filter + report per cabang. Jalur data lengkap.
- **E3 tampilan** — 🔴 gap (map picker) ditemukan & diperbaiki (Polish #1).
- **E4 config** ✅ — koordinat global (`office_lat/lng/radius`) **sudah** di M15 grup Lokasi.
- **E5 keterkaitan** ✅ — presence/user/dashboard/report semua ter-scope per cabang (eksplisit). Lihat Keputusan.

## Keputusan: BranchScope global TIDAK dibangun (YAGNI + aman)
Plan asli mengusulkan `BranchScope` global auto-filter (invasiveness HIGH). Verifikasi kode: filtering per-cabang **sudah jalan eksplisit & benar** — `DashboardService` (by_branch, attendanceReport(branchId)), `User::scopeInBranch`, `PresenceService::branchFor` (branch tercatat di row menang, history stabil). Global scope malah **berbahaya**: bisa memutus query lintas-cabang (payroll rekap, akuntansi, laporan konsolidasi) dan menyulitkan super_admin. Keputusan: pertahankan filtering eksplisit yang sudah teruji, tidak menambah global scope.

## Polish yang dikerjakan
1. **Map picker koordinat cabang (E3)** — sebelumnya lat/lng diketik manual. Kini form cabang (create/update) punya **peta Leaflet + OpenStreetMap** (gratis, tanpa API key): klik peta / geser marker → isi lat/lng otomatis, **preview lingkaran radius** (ikut field radius_meters), **pencarian alamat** via Nominatim, dan sinkron dua-arah bila lat/lng diketik manual. Diimplementasi sebagai field `view` (`admin.branch.map_picker`) di atas field lat/lng — tanpa menyentuh kolom DB.
2. **Map picker koordinat GLOBAL di Settings (E3/E4)** — geofence global (`office_lat/lng/radius`) di `/admin/settings` tab **Lokasi & Geofence** sebelumnya hanya input angka. Kini punya map picker Leaflet/OSM yang sama (klik peta / geser marker / cari alamat via Nominatim / preview lingkaran radius / sinkron dua-arah). Diimplementasi sebagai partial `admin.settings.geofence_map` yang di-`@include` di pane `lokasi`, membaca/menulis input `#fld-office_lat/lng/radius` yang sudah ada — tanpa mengubah `SettingService`/skema. `map.invalidateSize()` dipanggil saat tab Lokasi ditampilkan (Bootstrap `shown.bs.tab`) agar peta tidak abu-abu saat tab awalnya tersembunyi.

## Automation Test
- **PHPUnit** `MultiBranchTest` — 14/14 (existing 11 geofence/presence + 3 baru: `scopeInBranch` filter, form create render map picker, create cabang berkoordinat via CRUD).
- **Playwright** `m07-branch-map.mjs` — 3/3 (peta Leaflet + kotak cari termuat, klik peta mengisi koordinat nyata, ubah radius tersinkron tanpa error).
- **Playwright** `m15-geofence-map.mjs` — 5/5 (map picker global di tab Lokasi: peta+tile termuat, klik peta isi `office_lat/lng` nyata, ubah radius tersinkron, koordinat persist setelah Simpan+reload, nol JS error).
- **Playwright** `m15-settings.mjs` — 7/7 (nol regresi di halaman Settings setelah penambahan map picker).
- **Regression:** `php artisan test` → **233 passed (496 assertions)**, nol regresi.
- Verifikasi visual (screenshot): peta + marker + lingkaran radius + kotak cari rapi, di form cabang & tab Settings Lokasi.

## Definition of Done — tercapai ✅
- Core multi-branch jalan & ter-scope; map picker ada; koordinat global di M15. BranchScope global sengaja tidak dibangun (keputusan sadar). i18n (E6) menyusul via M13.

---

## Evaluasi Awal (arsip)


## Ringkasan
Cabang (branch) + geofencing per-cabang, `branch_id` di user & presence, filter
dashboard per cabang.

## Evaluasi Bisnis (7 Poin)
- **E1. Proses bisnis** — ✅ CRUD cabang, assign user ke cabang (User CRUD), scan presensi auto-set branch by geofence, dashboard filter cabang. Jalur data lengkap (bukan tabel mati).
- **E2. Integrasi keluar** — ➖ Internal.
- **E3. Tampilan** — ⚠️ Cabang punya lat/lng → **kandidat map picker** (sekarang input angka manual). Best practice: pilih lokasi di peta.
- **E4. Third-party config** — ⚠️ Koordinat kantor global (`LAT/LNG` .env) → pindah M15. Map picker butuh tile provider (config M15 bila berbayar).
- **E5. Keterkaitan** — 🔴 Menyentuh presence (geofence), user, dashboard, report. Plan asli sebут `BranchScope` global — **verifikasi apakah semua query sudah ter-scope per cabang** (invasiveness HIGH).
- **E6. Bahasa** — ⚠️ Hardcode ID → M13.
- **E7. Currency** — ➖ N/A (kecuali gaji beda per cabang → currency M14).

## Polish Task
1. Verifikasi/implement `BranchScope` global auto-filter.
2. Map picker untuk koordinat cabang.
3. Koordinat global → M15. i18n → M13.

## Definition of Done
- Core sudah jalan; polish: BranchScope global, map picker, config koordinat.
