# M06 — Employee Documents ✅ DONE (polish)

> **Status:** ✅ DONE + POLISHED (2026-08-24) · **Prioritas:** 🟡 polish

## Evaluasi Flow Business (verifikasi ke kode)
- **E1 proses bisnis** ✅ — upload→download(streamed private)→hapus(+file), filter, completeness report, expiry alert terjadwal. Lengkap.
- **E3 tampilan** ✅ — badge near-expiry **sudah ada** (ternyata lebih lengkap dari klaim plan); ditingkatkan jadi bertingkat.
- **E4 third-party config** — 🔴 **gap nyata ditemukan & diperbaiki** (lihat Polish #1).
- **E5 keterkaitan** ✅ — user (M01), notif expiring (M03), portal.
- **E6 bahasa** ⚠️ ditunda ke batch M13.

## Polish yang dikerjakan
1. **Storage disk mengikuti setting M15 (E4)** — BUG: setting `document_disk` sudah ada di UI M15 + panel status memprobe-nya, TAPI `DocumentService` & controller pakai const `DISK='local'` hardcoded → setting diabaikan (file tetap ke local). FIX: tambah `DocumentService::disk()` yang baca `setting('document_disk','local')`; semua store/download/delete kini lewat `disk()`. Ganti ke S3 dari Settings kini benar-benar berlaku, bukan sekadar label. Const `DISK` dipertahankan sebagai default (back-compat).
2. **Badge near-expiry bertingkat (E3)** — sebelumnya 1 tier (≤30 kuning). Kini: **Kedaluwarsa** (merah), **≤7 hari "N hari lagi"** (merah), **≤30 hari** (kuning) — HR lebih awas ke yang mendesak.

## Automation Test
- **PHPUnit** `DocumentServiceTest` — 14/14 (2 baru: `disk()` mengikuti setting + fallback; store ke disk terkonfigurasi).
- **Playwright** `m06-documents.mjs` — 4/4 (list termuat, badge Kedaluwarsa, badge "N hari lagi" ≤7, laporan kelengkapan).
- **Regression:** `php artisan test` → **215 passed (451 assertions)**, nol regresi.

## Definition of Done — tercapai ✅
- Dokumen usable end-to-end; storage disk benar-benar dari config UI (siap S3); badge expiry bertingkat. i18n (E6) menyusul via M13.

---

## Evaluasi Awal (arsip)


## Ringkasan
Jenis dokumen, upload dokumen karyawan (private disk, streamed download), alert
kedaluwarsa, laporan kelengkapan. Seed jenis dokumen ada.

## Evaluasi Bisnis (7 Poin)
- **E1. Proses bisnis** — ✅ Upload→simpan→download→hapus, filter, completeness report, expiry alert terjadwal. Lengkap end-to-end.
- **E2. Integrasi keluar** — ➖ Internal (storage lokal).
- **E3. Tampilan** — ✅ Index + filter, completeness (tabel checklist tepat). Data expiry → bisa ditambah badge/warna near-expiry.
- **E4. Third-party config** — ⚠️ Storage disk sekarang default lokal via `DocumentService::DISK`. Kalau nanti pindah S3 → **wajib config di M15** (driver + kredensial S3 diatur super admin).
- **E5. Keterkaitan** — ✅ Terkait user (M01), notif expiring (M03), portal (karyawan lihat dokumen sendiri).
- **E6. Bahasa** — ⚠️ Hardcode ID → M13.
- **E7. Currency** — ➖ N/A.

## Polish Task
1. Storage driver/kredensial ke config UI (M15) bila butuh S3.
2. Badge near-expiry di list. i18n (M13).

## Definition of Done
- Sudah terpenuhi; sisa: storage config UI (jika S3) + i18n.
