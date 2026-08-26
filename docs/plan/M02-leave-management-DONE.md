# M02 — Leave Management ✅ DONE (polish)

> **Status:** ✅ DONE + POLISHED (2026-08-24) · **Prioritas:** 🟡 polish (E3 kalender)

## Polish yang dikerjakan (E3 tampilan + performa)
- **Legend warna jenis cuti** di kalender: badge tiap `leave_type` dengan warnanya + tanda "(tidak dibayar)". Sebelumnya chip berwarna ada tapi user tak tahu warna = jenis apa.
- **Fix N+1 query:** `LeaveService::calendarEntries()` kini eager-load `dates` (selain `user`, `leaveType`). View grid loop `$entry->dates` tanpa query per-entry → jumlah query konstan.
- Konfirmasi task plan sudah terpenuhi: kalender **sudah** pakai `leave_types.color` per chip + **filter departemen** (verified, dipertahankan).

## Automation Test
- **PHPUnit** `LeaveCalendarTest` — 2/2 (entries approved dalam rentang + relasi ter-eager-load; **guard N+1**: ≤5 query untuk 6 entri cuti).
- **Playwright** `m02-leave-calendar.mjs` — 4/4 (grid 7 kolom, legend 5 badge, filter departemen, chip cuti berwarna tampil).
- **Regression:** `php artisan test` → **187 passed (399 assertions)**, nol regresi.

## Sisa (bertahap, bukan blocker)
- i18n label modul (E6) — ikut batch terjemahan konten M13.

## Definition of Done — tercapai ✅
- Kalender pakai warna per jenis + legend + filter dept; performa aman (no N+1). i18n menyusul.

---

## Evaluasi Awal (arsip)


## Ringkasan
Jenis cuti, saldo, pengajuan, approval, integrasi ke perhitungan gaji (paid/unpaid),
kalender cuti, carry-over, generate saldo tahunan.

## Evaluasi Bisnis (7 Poin)
- **E1. Proses bisnis** — ✅ Ajukan→approve/reject→saldo berkurang→gaji tidak potong hari cuti. `LeaveService` lengkap. Seed jenis cuti ada.
- **E2. Integrasi keluar** — ➖ Internal.
- **E3. Tampilan** — ⚠️ Ada `leave-calendar` (bagus, data tanggal → kalender = best practice ✅). Verifikasi kalender sudah menampilkan warna per jenis cuti & filter dept. Daftar pengajuan pakai tabel (tepat).
- **E4. Third-party config** — ➖ Tidak.
- **E5. Keterkaitan** — 🔴 Kuat: leave → salary (abstain), notif (approve/reject), approval engine, portal (ajukan mandiri). Menu "Cuti & Izin" sudah mengelompokkan semua.
- **E6. Bahasa** — ⚠️ Hardcode ID → M13.
- **E7. Currency** — ➖ N/A.

## Polish Task
1. Pastikan kalender pakai warna `leave_types.color` + filter departemen.
2. i18n (M13).

## Definition of Done
- Sudah terpenuhi; sisa polish kalender + i18n.
