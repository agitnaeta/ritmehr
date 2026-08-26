# M03 — Notification System ✅ DONE (polish + WAHA pivot)

> **Status:** ✅ DONE + POLISHED (2026-08-24) · **Prioritas:** 🟡 polish

## Polish yang dikerjakan
- **PIVOT ke WAHA (self-hosted)** menggantikan Fonnte sebagai provider default WhatsApp — sejalan arahan E2 "manage sendiri, jangan gantung pihak ketiga". WAHA jalan sebagai container sendiri (https://waha.devlike.pro).
  - `WahaWhatsAppGateway` baru: `POST {base}/api/sendText` body `{session, chatId, text}`, header `X-Api-Key` opsional, chatId auto-normalize ke `62xxxx@c.us`.
  - Settings M15 tambah: `whatsapp_provider` (waha/fonnte), `waha_url`, `waha_session`, `waha_api_key` (encrypted). **Fonnte jadi opsi legacy**.
  - `AppServiceProvider` pilih gateway by provider → fallback `LogWhatsAppGateway` bila belum dikonfigurasi/nonaktif. Gateway singleton di-`forgetInstance` saat tes agar config baru langsung dipakai.
- **Tombol "Kirim Tes" WhatsApp** di Settings — kirim pesan uji sungguhan (bukan cuma cek token terisi), balikin sukses/gagal/log nyata. Panel status jadi provider-aware ("Aktif — WAHA: <url>").
- **Verifikasi task plan lama:** token gateway sudah di Settings UI (M15) ✅, template currency sudah `money()` (M14) ✅ — tidak ada "Rp" hardcode di `NotificationTemplates`.
- **Docs** `docs/WHATSAPP_WAHA.md`: cara jalankan container WAHA (docker/compose), pairing QR, wiring ke Settings, troubleshooting.

## Automation Test
- **PHPUnit** `WahaWhatsAppTest` — 8/8 (sendText + chatId normalisasi, header X-Api-Key on/off, error handling, passthrough chatId, container resolve waha/fonnte/log per provider & saat disabled).
- **PHPUnit** `TestWhatsAppEndpointTest` — 3/3 (super admin kirim tes → hit WAHA; phone wajib; non-super-admin 403).
- **Playwright** `m03-waha.mjs` — 4/4 (provider selector, field WAHA, form Kirim Tes, submit mode-log tanpa crash).
- **Regression:** `php artisan test` → **198 passed (413 assertions)**, nol regresi.

## Sisa (bertahap, bukan blocker)
- i18n template notifikasi (E6) — ikut batch terjemahan konten M13.

## Definition of Done — tercapai ✅
- Gateway WA dikonfigurasi via UI (WAHA self-hosted default), bisa dites dari UI, currency template rapi. i18n menyusul.

## Update (2026-08-24) — Fonnte DIHAPUS TOTAL
Keputusan Capt: buang Fonnte, WAHA jadi satu-satunya provider.
- Hapus `FonnteWhatsAppGateway.php`, setting `whatsapp_provider` + `fonnte_token`, blok `config/services.php` fonnte.
- `AppServiceProvider` binding jadi WAHA-only (enabled + waha_url → WAHA, else Log).
- `SettingController` status/test WA tanpa cabang provider. Secret field yang diuji di M15 pindah ke `waha_api_key`.
- Tes disesuaikan: `WahaWhatsAppTest` (8), `TestWhatsAppEndpointTest` (3), `m03-waha.mjs` (4, incl. verifikasi selector & field Fonnte hilang), `m15-settings.mjs` (7).
- Regression: **197 passed (412 assertions)**, nol regresi.

## Menyusul (permintaan lanjutan Capt) — lihat `docs/plan/M03b-waha-inapp.md`
Koneksi WhatsApp (scan QR + kirim) langsung dari layout aplikasi, tanpa buka UI WAHA.

---



## Ringkasan
Notifikasi database + WhatsApp (Fonnte/Log gateway), preferensi per user, bell icon,
scheduler alert absensi & digest approval.

## Evaluasi Bisnis (7 Poin)
- **E1. Proses bisnis** — ✅ Ter-trigger event nyata: leave approve/reject, approval step, dokumen expiring, alert absensi terjadwal, digest mingguan.
- **E2. Integrasi keluar** — ⚠️ WhatsApp via **Fonnte (eksternal)**. Sudah ada fallback `LogWhatsAppGateway` kalau token kosong → aman. Kredensial harus pindah ke config UI (M15/E4).
- **E3. Tampilan** — ✅ Bell + unread count + dropdown; portal punya halaman notifikasi.
- **E4. Third-party config** — ❌ Token Fonnte masih `services.fonnte.token` (.env) → **pindah ke M15**.
- **E5. Keterkaitan** — ✅ Konsumen dari hampir semua modul (leave, approval, document, attendance). Preferensi channel per user.
- **E6. Bahasa** — ⚠️ Template `NotificationTemplates` hardcode ID → M13.
- **E7. Currency** — ⚠️ Template kasbon pakai "Rp" hardcode → M14.

## Polish Task
1. Pindahkan token Fonnte ke Pengaturan (M15).
2. i18n template (M13), format currency template (M14).

## Definition of Done
- Sudah jalan; sisa: config gateway via UI + i18n/currency template.
