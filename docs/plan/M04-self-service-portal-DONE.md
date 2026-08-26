# M04 — Employee Self-Service Portal ✅ DONE (polish)

> **Status:** ✅ DONE + POLISHED (2026-08-24) · **Prioritas:** 🟡 polish

## Evaluasi Flow Business (verifikasi ke kode)
- **E1 proses bisnis** ✅ — semua self-service jalan & query scoped ke user (aman IDOR).
- **E3 tampilan** — 2 gap ditemukan & diperbaiki (lihat Polish).
- **E5 keterkaitan** ✅ — slip sinkron payroll+pajak (M05), notif (M03), cuti (M02).
- **E7 currency** ✅ — slip pakai `@rupiah`/`money()` (M14).
- **E6 bahasa** ⚠️ ditunda ke batch M13.

## Polish yang dikerjakan
1. **Kalender kehadiran** (E3 item #2): `/my/attendance` kini punya **grid kalender bulanan** (default) dengan badge per hari (Tepat/Telat/Lembur/Luar Radius) + jam masuk→pulang, plus **toggle Kalender/Tabel** (preferensi tersimpan di localStorage). `PortalController@attendance` kirim `byDate` (indexed, no N+1).
2. **Unduh/Cetak slip gaji** (gap flow-business — plan janji "unduh slip" tapi belum ada): route baru `/my/salary/{id}/print` + `salaryPrint()` (ownership-scoped, 404 utk slip orang lain), view **A4 print-to-PDF** bersih (header CompanyProfile, pendapatan, potongan lengkap PPh21/BPJS, net) + tombol "Unduh / Cetak Slip" di detail slip. Currency via `money()`.

## Automation Test
- **PHPUnit** `PortalTest` — 20/20 (termasuk 4 baru: print slip milik sendiri, tolak print slip orang lain (404), detail slip tautkan print, kalender kehadiran render).
- **Playwright** `m04-portal-polish.mjs` — 4/4 (kalender default + grid, toggle 2 arah, tombol cetak, halaman print SLIP GAJI+net).
- **Regression:** `php artisan test` → **213 passed (447 assertions)**, nol regresi.
- Verifikasi visual (screenshot): kalender + slip A4 rapi.

## Definition of Done — tercapai ✅
- Portal usable penuh; kalender kehadiran + unduh/cetak slip PDF ada. i18n (E6) menyusul via M13.

---

## Evaluasi Awal (arsip)


## Ringkasan
Portal `/my/*`: dashboard, kehadiran, slip gaji, cuti (ajukan/batal), kasbon, profil,
ganti password, notifikasi. Guard sama (backpack), middleware `EnsurePortalAccess`.

## Evaluasi Bisnis (7 Poin)
- **E1. Proses bisnis** — ✅ Karyawan bisa self-service penuh: lihat gaji, ajukan cuti, lihat kasbon, edit profil, ganti password.
- **E2. Integrasi keluar** — ➖ Internal.
- **E3. Tampilan** — ⚠️ Blade+Bootstrap, mobile-friendly. Slip gaji perlu tampilkan breakdown pajak (setelah M05). Kehadiran per tanggal → kandidat kalender (sekarang tabel).
- **E4. Third-party config** — ➖ Tidak langsung.
- **E5. Keterkaitan** — 🔴 Konsumen M02 (cuti), M05 (slip pajak), M03 (notif). Slip gaji harus sinkron dengan hasil payroll+pajak.
- **E6. Bahasa** — ⚠️ Hardcode ID + butuh switcher (M13).
- **E7. Currency** — ⚠️ "Rp" di slip → M14.

## Polish Task
1. Breakdown pajak/BPJS di slip (depends M05).
2. Kehadiran → opsi kalender view.
3. i18n + switcher (M13), currency (M14).

## Definition of Done
- Sudah usable; polish slip pajak (M05), kalender kehadiran, i18n/currency.
