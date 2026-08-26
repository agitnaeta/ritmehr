# M03b — WhatsApp In-App (Scan QR + Kirim dari Layout Aplikasi) ✅ DONE

> **Status:** ✅ IMPLEMENTED & TESTED (2026-08-24)
> **Permintaan Capt:** admin tidak perlu buka UI WAHA. Scan QR & kirim WA
> dilakukan langsung dari layout aplikasi kita. Pendekatan: **proxy penuh**.

## Hasil Implementasi
- **`WahaAdminService`** (`app/Services/Notifications/`) — proxy server-side ke WAHA:
  `status()` (map STOPPED/STARTING/SCAN_QR_CODE/WORKING + akun), `start()` (idempoten create+start), `qr()` (stream bytes PNG), `logout()`. `fromSettings()` baca config M15; null bila `waha_url` kosong. Semua error di-catch → status `UNREACHABLE`, tak pernah melempar ke UI.
- **`WahaController`** (super_admin only) — `index` (halaman), `status` (JSON, dipoll), `start`, `qr` (stream image, `Cache-Control: no-store`), `logout`.
- **View** `admin/whatsapp/index.blade.php` — kartu status 🔴🟡🟢, QR `<img>` (muncul saat SCAN_QR_CODE, auto cache-bust), info akun tersambung, tombol Sambungkan/Putuskan/Segarkan, polling JS tiap 3 dtk (berhenti saat WORKING). UI 100% tema kita.
- **Routes** `/admin/whatsapp/{,status,start,qr,logout}` + **menu** "Koneksi WhatsApp" (grup Pengaturan, super_admin).
- **Keamanan:** semua route super_admin; base URL + API key WAHA tetap server-side, tak pernah ke browser; QR di-proxy.

## Automation Test
- **PHPUnit** `WahaConnectionTest` — 10/10 (status mapping WORKING/SCAN_QR/404→STOPPED, start kirim X-Api-Key, logout, fromSettings null, endpoint guard super_admin, NOT_CONFIGURED JSON, index load, start proxy). Catatan: path `UNREACHABLE` di-cover di kode (try/catch) tapi tak di-unit-test karena env PHP/xdebug segfault saat exception dilempar dalam `Http::fake` closure.
- **Playwright** `m03b-waha-connect.mjs` — 5/5 (halaman+tombol, polling+graceful saat WAHA down, QR hidden, menu tampil, manager 403).
- **Regression:** `php artisan test` → **207 passed (432 assertions)**, nol regresi.

## Alur pengguna (tanpa buka WAHA) — tercapai ✅
Pengaturan → Koneksi WhatsApp → Sambungkan → scan QR di halaman kita → status auto jadi 🟢 Tersambung → kirim tes dari Pengaturan.

## Definition of Done — tercapai ✅
- Sambungkan nomor WA (scan QR) dari layout aplikasi, tanpa buka WAHA. ✅
- Status real-time (polling) + akun tersambung + logout. ✅
- Kirim WA tetap jalan (gateway existing). ✅
- Kredensial WAHA aman (proxy server-side). ✅

---

## Rencana Awal (arsip)


## Kunci: WAHA itu API-first
Semua fungsi dashboard WAHA tersedia sebagai REST API. App kita jadi **proxy** —
browser ngomong ke route kita, route kita ngomong ke WAHA (server-to-server).
Keuntungan: **URL & API key WAHA tidak pernah bocor ke browser**, dan UI 100% pakai
tema kita.

### Endpoint WAHA yang dipakai
| Aksi | WAHA API |
|------|----------|
| Status session | `GET /api/sessions/{session}` → `status`: STOPPED/STARTING/SCAN_QR_CODE/WORKING/FAILED |
| Mulai session | `POST /api/sessions/{session}/start` (atau create `POST /api/sessions`) |
| Ambil QR | `GET /api/{session}/auth/qr?format=image` (muncul saat status SCAN_QR_CODE) |
| Akun tersambung | `GET /api/sessions/{session}/me` → nomor & nama |
| Logout | `POST /api/sessions/{session}/logout` |
| Kirim pesan | `POST /api/sendText` (sudah ada di `WahaWhatsAppGateway`) |

## Arsitektur

```
Browser (layout kita)  ──►  WahaController (proxy, server-side)  ──►  WAHA container
   - polling status            - inject X-Api-Key + base URL
   - render QR <img>           - map ke WahaAdminService
   - tombol connect/logout     - guard super_admin
```

### Komponen baru
1. **`WahaAdminService`** (`app/Services/Notifications/`)
   - `status()`: GET session → normalize ke {state, connected, me?}
   - `start()`: pastikan session ada + start
   - `qr()`: ambil bytes QR (image/png) — untuk di-stream controller
   - `me()`: info akun tersambung
   - `logout()`: putus sesi
   - Reuse base URL / session / api key dari `setting()` (M15).
2. **`WahaController`** (admin) — semua guard `super_admin`:
   - `GET  /admin/whatsapp` — halaman "Koneksi WhatsApp" (layout kita)
   - `GET  /admin/whatsapp/status` — JSON status (dipoll tiap 3 dtk via JS)
   - `POST /admin/whatsapp/start` — mulai/aktifkan sesi
   - `GET  /admin/whatsapp/qr` — stream QR image (proxied)
   - `POST /admin/whatsapp/logout` — putus
   - (kirim tes sudah ada di SettingController)
3. **View** `admin/whatsapp/index.blade.php`:
   - Kartu status besar: 🔴 Terputus / 🟡 Menunggu Scan / 🟢 Tersambung (nomor).
   - Kalau SCAN_QR_CODE → tampilkan QR (`<img src="/admin/whatsapp/qr">`), auto-refresh.
   - Polling JS: hit `/status` tiap 3 dtk; saat WORKING → tampilkan nomor + tombol Logout;
     saat butuh QR → tampilkan QR; berhenti polling saat WORKING.
   - Tombol "Sambungkan" (start) & "Putuskan" (logout).
4. **Menu**: item "Koneksi WhatsApp" di grup Pengaturan (atau Notifikasi).

## Alur pengguna (tanpa buka WAHA)
1. Admin buka **Pengaturan → Koneksi WhatsApp**.
2. Klik **Sambungkan** → app start session di WAHA.
3. Status jadi "Menunggu Scan" → **QR tampil di halaman kita**.
4. Admin scan pakai WhatsApp HP.
5. Polling deteksi status WORKING → tampil "🟢 Tersambung: +62…" + tombol Putuskan.
6. Kirim tes langsung dari sini (atau dari Settings).

## Keamanan
- Semua route `super_admin` only.
- QR & status di-proxy — kredensial WAHA tak pernah ke browser.
- QR endpoint set `Cache-Control: no-store` (QR sekali pakai / cepat basi).

## Pertimbangan / Fallback
- WAHA belum dikonfigur (waha_url kosong) → halaman kasih pesan "atur dulu di Pengaturan".
- WAHA down / timeout → status "Tidak dapat terhubung ke server WAHA", tombol coba lagi.
- Multi-session: cukup satu session (`waha_session`, default `default`).
- WAHA Core (gratis) mendukung 1 session — cukup untuk 1 nomor perusahaan.

## Test
- **PHPUnit**: `WahaAdminService` via `Http::fake` — status mapping (SCAN_QR_CODE/WORKING),
  start, logout, me; controller guard super_admin; graceful bila WAHA error/timeout.
- **Playwright**: halaman termuat, tombol Sambungkan → status berubah (fake/log),
  QR `<img>` muncul saat mode scan, guard non-super-admin 403.

## Definition of Done
- Admin bisa sambungkan nomor WA (scan QR) **dari layout aplikasi**, tanpa buka WAHA.
- Status koneksi real-time (polling) + nomor tersambung + logout.
- Kirim WA tetap jalan (gateway existing).
- Kredensial WAHA aman (server-side proxy).
