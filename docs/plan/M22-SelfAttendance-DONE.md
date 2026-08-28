# M22 — Self-Attendance (Absen Mandiri: Kamera + Geolokasi + Map Layer)

**Status:** ✅ SELESAI (semua sub-modul M22-1..M22-5 terimplementasi & tested)
**Modul:** M22 · **Prasyarat:** M4 (Portal `/my`), M7 (Branch/Geofence), M15 (Settings), M16 (Storage)
**Trigger:** Capt — "absen mandiri pakai kamera, bukti geolokasi + map layer, dan di setting bisa pilih QR Mode atau Camera Location Mode."

## Ringkasan Implementasi (final)

| Sub | Status | Test |
|-----|--------|------|
| M22-1 Fondasi data + Settings toggle | ✅ | PHPUnit 5/5 |
| M22-2 Portal Camera Check-in | ✅ | PHPUnit 6 + Browser 5/5 |
| M22-3 Bukti selfie+peta (riwayat & admin Show) | ✅ | PHPUnit 3 (+stream auth) |
| M22-5 Approval absen luar-radius | ✅ | PHPUnit 6/6 |
| M22-4 Menu mode-aware + tombol portal | ✅ | PHPUnit 2/2 |

**Total: 425/425 PHPUnit hijau + 5/5 Browser Playwright (kamera+GPS fake) + nol regresi.**

File utama:
- Migration `2026_08_28_100000_add_selfie_to_presences.php` (selfie_path, source, accuracy, approval_status, approval_note, approved_by)
- `PortalAttendanceController` (create/store/selfie stream) + `PresenceService::approve/reject`
- `PresenceCrudController` (custom Show + approvals + approve/reject actions)
- Views: `portal/attendance_checkin`, `admin/presence/show`, `admin/presence/approvals`, dashboard/attendance banner
- Setting: `attendance_mode` (qr|camera), `camera_require_selfie`

---


---

## 1. Cerita Bisnis

Sekarang absensi RitmeHR berjalan lewat **satu scanner QR bersama di pintu** (`/scan` publik, tanpa login) — karyawan mengantre menempelkan QR pribadinya ke satu perangkat. Ini menyulitkan untuk:

- Karyawan lapangan / remote / multi-cabang yang tidak melewati satu pintu fisik.
- Bukti kehadiran yang bisa diaudit (siapa, di mana, wajahnya).

**Fitur ini menambah jalur kedua: absen MANDIRI dari HP karyawan sendiri** (di portal `/my`, sudah login), dengan:
1. **Foto selfie** sebagai bukti wajah (disimpan via StorageManager M16).
2. **Geolokasi** (lat/lng dari HP) — sudah divalidasi geofence Haversine yang ADA.
3. **Map layer** menampilkan posisi karyawan + radius kantor/cabang sebagai bukti visual, dan snapshot peta ini jadi bagian record.

Dan super-admin bisa memilih **mode absensi global** di Settings:
- **QR Mode** — perilaku lama (scanner bersama di pintu). Tetap dipertahankan.
- **Camera Location Mode** — absen mandiri kamera + geo dari portal karyawan.

> Ini BUKAN mengganti QR. Ini menambah mode kedua + toggle. QR Mode tetap utuh (jangan regresi).

---

## 2. Requirement Inti → Konsekuensi Desain

| ID | Requirement | Konsekuensi desain |
|----|-------------|--------------------|
| R1 | Karyawan absen sendiri pakai kamera HP | Halaman baru `/my/attendance/check-in` (butuh login, guard portal). Ambil foto via `getUserMedia` → `<canvas>` → JPEG base64. TIDAK pakai upload file manual (bukti harus live). |
| R2 | Bukti geolokasi | Reuse `navigator.geolocation` (sudah dipakai di scan.blade). Kirim lat/lng ke server, validasi dengan `PresenceService::inCoordinate()` yang ADA (branch-aware). |
| R3 | Map layer sebagai bukti | Leaflet (sudah dipakai di project, gratis, tanpa API key) tampilkan marker posisi + lingkaran radius. Simpan `map_lat/map_lng` + status `outside` di record. |
| R4 | Foto tersimpan sebagai bukti | Kolom `presences.selfie_path`. Simpan via StorageManager M16 (`disk()->putFile`) → dukung local/S3/dst. Tampil di admin Show + portal riwayat. |
| R5 | Toggle QR / Camera mode | Setting baru `attendance_mode` (`qr` \| `camera`) di grup `lokasi`. Portal + menu menyesuaikan mode aktif. Default `qr` (back-compat). |
| R6 | Anti-titip absen (opsional) | Camera Mode WAJIB selfie + WAJIB dalam radius (kalau `outside` → tolak / tandai). Setting `camera_require_inside` (bool). |

---

## 3. Model Data (additive — TIDAK bongkar `presences`)

Migration baru `add_selfie_to_presences`:

```php
Schema::table('presences', function (Blueprint $t) {
    $t->string('selfie_path')->nullable()->after('lng');   // path bukti foto (StorageManager)
    $t->string('source')->default('qr')->after('selfie_path'); // 'qr' | 'camera' — asal record
    $t->decimal('accuracy', 8, 2)->nullable()->after('source'); // akurasi GPS (meter), untuk audit
});
```

- `lat/lng/outside/branch_id` — **sudah ada**, dipakai ulang apa adanya.
- `source` membedakan record QR vs Camera (untuk laporan/audit), default `qr` → record lama tetap valid.
- Foto disimpan sebagai FILE (via M16 StorageManager), DB simpan path saja.

Setting baru (M15 `SettingService::definitions()`, grup `lokasi`):

| Key | Type | Default | Label |
|-----|------|---------|-------|
| `attendance_mode` | select `qr`\|`camera` | `qr` | Mode Absensi |
| `camera_require_selfie` | bool | `true` | Wajib Selfie (Camera Mode) |
| `camera_require_inside` | bool | `true` | Wajib Dalam Radius (Camera Mode) |

---

## 4. Arsitektur per Requirement

### R1+R2+R3+R4 — Alur Camera Location Mode (portal karyawan)

```
Karyawan buka /my → tombol "Absen Sekarang" (muncul hanya jika attendance_mode=camera)
  → /my/attendance/check-in (Blade portal, layout /my)
     1. minta izin kamera (getUserMedia) + lokasi (geolocation) — paralel
     2. tampilkan preview kamera + peta Leaflet live (marker posisi + radius kantor/cabang)
     3. status real-time: "✅ Dalam area kantor" / "⚠️ Di luar area (radius X m)"
     4. tombol "Ambil Foto & Absen" → capture <canvas> → JPEG
     5. POST /my/attendance/check-in {selfie(base64), lat, lng, accuracy}
  → PortalAttendanceController@store
     - resolve user = backpack_user() (TIDAK terima user_id dari request — anti-spoof)
     - PresenceService::record($user)        // login/logout otomatis (ADA)
     - simpan selfie via StorageManager M16  // presences/selfie/<uuid>.jpg
     - PresenceService::updateCoordinate()   // hitung outside pakai geofence ADA
     - set source='camera'
     - jika camera_require_inside && outside → tolak (422) atau tandai (keputusan terbuka Q3)
  → tampil hasil: "Absen masuk 08:03 tercatat" + thumbnail selfie + peta lokasi
```

**Kunci keamanan:** user dari sesi login, BUKAN dari QR/request. Camera Mode = self-service, jadi identitas = pemilik sesi.

### R5 — Toggle mode

- Setting `attendance_mode` dibaca di:
  - **Menu/portal:** tombol "Absen Sekarang" (camera) vs instruksi "scan di pintu" (qr).
  - **Route guard:** `/my/attendance/check-in` hanya aktif saat `attendance_mode=camera` (else redirect + pesan).
  - **Halaman `/scan`:** tetap ada di kedua mode (fallback), tapi ditonjolkan saat `qr`.
- Perubahan setting = efek langsung (tabel `settings`, cache flush) — bukan cache prompt.

### R3 — Map layer detail

- **Live (saat absen):** Leaflet peta, tile OpenStreetMap, marker biru = posisi karyawan (dari geolocation), lingkaran = geofence kantor/cabang (`office_lat/lng/radius` atau branch). Warna lingkaran hijau (dalam) / merah (luar).
- **Bukti tersimpan:** simpan `lat/lng` (sudah), render ulang peta statis dari koordinat itu di admin Show + portal riwayat (Leaflet read-only, tak perlu simpan gambar peta — hemat storage; hanya selfie yang disimpan sebagai file).

---

## 5. Evaluasi Arsitektur (checklist 7-poin)

- **Kelengkapan proses bisnis** — Camera Mode mencakup siklus utuh: check-in → check-out (reuse `writeRecord` login/logout), bukti selfie + geo + peta, tampil di riwayat portal & admin. ✅
- **Dependensi eksternal** — Leaflet + OSM tile = gratis tanpa API key (sudah dipakai). getUserMedia/geolocation = API browser native. **Nol dependensi berbayar.** ✅
- **Best practice UI/UX** — Data absensi berbasis tanggal → sudah kalender di `/my/attendance` (M4). Check-in = full-screen mobile-first (preview kamera besar + peta + 1 tombol). Bukti = thumbnail + peta, bukan tabel mentah. ✅
- **Third-party config** — Storage foto pakai StorageManager M16 (super-admin atur disk). Mode & geofence di Settings super-admin. ✅
- **Keterkaitan antar-fitur** — Nyambung ke M4 (portal), M7 (branch geofence per-cabang), M16 (storage), M15 (settings). Record masuk ke tabel `presences` yang sama → laporan/payroll existing otomatis lihat. ✅
- **Lokalisasi** — Label ikut level i18n proyek (menu `lang/*/menu.php`; form/portal hardcode ID seperti modul lain). ✅
- **Currency** — N/A. ✅

---

## 6. Rencana Delivery Bertahap (1 sub-modul per eksekusi)

| Sub | Nama | Isi | Test |
|-----|------|-----|------|
| **M22-1** | Fondasi data + Settings toggle | Migration `selfie_path/source/accuracy`; setting `attendance_mode`+2 flag; UI Settings dropdown mode. | PHPUnit: setting tersimpan/terbaca; migration; browser: dropdown mode di Settings. |
| **M22-2** | Portal Camera Check-in | Route `/my/attendance/check-in` (GET+POST); `PortalAttendanceController`; Blade kamera+Leaflet; capture selfie→canvas; validasi geofence; simpan via M16. | PHPUnit: store bikin Presence source=camera + selfie tersimpan + outside benar + tolak di luar radius jika flag; browser: render kamera+peta, absen, hasil. |
| **M22-3** | Bukti di Riwayat & Admin | Portal `/my/attendance` tampilkan thumbnail selfie + peta per hari; admin Presence Show tampilkan selfie + peta + badge source. | PHPUnit: portal hanya lihat milik sendiri; browser: thumbnail+peta muncul. |
| **M22-4** | Menu & mode-aware | Tombol "Absen Sekarang" di `/my` (mode camera); guard route; `/scan` tetap utuh (mode qr). | Browser: mode=camera→tombol muncul; mode=qr→scanner; nol regresi QR. |

Urutan eksekusi: M22-1 → M22-2 → M22-3 → M22-4. Tiap sub wajib PHPUnit + Playwright hijau + full regression sebelum `-DONE`.

---

## 7. Rencana Test User via Browser (Playwright, UI asli)

Simpan skrip di `tests/browser/m22-self-attendance.mjs` (reuse `lib.mjs`: `login()`, `session()`). **Automasi bukan hacking** — semua lewat handler UI asli, bukan API bypass.

**Catatan teknis kamera/geo di headless Chromium:**
- Jalankan Playwright dengan permission granted:
  `context.grantPermissions(['camera','geolocation'], {origin})` + `context.setGeolocation({latitude, longitude})`.
- Fake kamera: launch chromium args `--use-fake-device-for-media-stream --use-fake-ui-for-media-stream --use-fake-mnclient` (stream video sintetis, `getUserMedia` tak minta izin manual).
- Geofence: set `setGeolocation` ke koordinat **dalam** radius kantor demo untuk happy path, dan **jauh di luar** untuk edge case.

**Skenario (TC-ID):**

| TC | Skenario | Assert |
|----|----------|--------|
| TC-01 | Settings: super-admin set `attendance_mode=camera` via dropdown (native `page.selectOption`) → Simpan | Flash sukses; nilai persist setelah reload |
| TC-02 | Karyawan (`ahmad@demo.test`) login → `/my` → tombol "Absen Sekarang" tampil (mode camera) | Tombol ada; klik → halaman check-in render |
| TC-03 | Check-in: izin kamera+lokasi granted (dalam radius) → preview kamera muncul + peta Leaflet + status "Dalam area" | `#preview` playing; `.leaflet-container` ada; status hijau |
| TC-04 | Klik "Ambil Foto & Absen" → hasil "Absen masuk tercatat" | Presence baru di DB (`source=camera`, `selfie_path` terisi, `outside=0`) |
| TC-05 | Absen kedua hari sama → "Absen keluar tercatat" (logout) | `presences.out` terisi |
| TC-06 | Edge: geolocation di LUAR radius + `camera_require_inside=true` → ditolak | Pesan "di luar area"; TIDAK ada Presence baru (atau ditandai — sesuai Q3) |
| TC-07 | Riwayat `/my/attendance` → hari absen tampil thumbnail selfie + peta lokasi | `img` selfie + `.leaflet-container` di sel tanggal |
| TC-08 | Admin (`siti@demo.test`) → Presence Show record camera → selfie + peta + badge "Camera" | Foto+peta render; badge source |
| TC-09 | Regresi QR: set `attendance_mode=qr` → `/scan` tetap jalan (scanner QR + geo) | Halaman scan render; record QR tetap sukses |
| TC-10 | Anti-spoof: POST `/my/attendance/check-in` dengan `user_id` orang lain di body → diabaikan, record tetap atas nama sesi login | Presence.user_id == pemilik sesi, bukan yang di-body |

Plus **PHPUnit** (`tests/Feature/SelfAttendanceTest.php`): store bikin Presence dari `backpack_user()`, geofence outside benar, selfie tersimpan (fake disk), tolak-di-luar-radius saat flag aktif, ownership riwayat.

---

## 8. Keputusan (Q3 & Q4 TERKUNCI · sisanya default)

### 🔒 Terkunci (Capt, 28 Agu 2026)

- **Q3 — Absen di LUAR radius (Camera Mode) → IZINKAN tapi butuh APPROVAL MANAJER.**
  Absen tetap tercatat, TAPI statusnya `pending_approval` (belum sah) sampai manajer meng-approve/reject. Konsekuensi desain:
  - Kolom baru `presences.approval_status` (`approved` | `pending` | `rejected`), default `approved`.
  - Record dalam radius → langsung `approved`. Di luar radius → `pending` + notif ke manajer.
  - **Sub-modul BARU M22-5 — Approval:** layar manajer (list presensi `pending` dengan selfie + peta) → tombol Approve/Reject + alasan. Reuse pola `visibleTo($me)` untuk scoping tim. Notif ke karyawan saat diputuskan.
  - Laporan/payroll hanya hitung yang `approved` (yang `pending`/`rejected` tidak dihitung sebagai hadir sah).
  - Setting `camera_require_inside` DIHAPUS dari rencana (diganti alur approval, bukan hard-block).

- **Q4 — Mode STRICTLY salah satu (toggle global).** `attendance_mode` = `qr` XOR `camera`. `/scan` tetap ada sebagai fallback teknis di kedua mode, tapi hanya ditonjolkan saat `qr`. Tidak ada mode per-cabang (itu fase 2 kalau diminta).

### Default aman (boleh diubah nanti)

- **Q1 — Selfie WAJIB di Camera Mode** (inti bukti). Setting `camera_require_selfie` default true, bisa dimatikan.
- **Q2 — Peta TIDAK disimpan sebagai file** — cukup simpan `lat/lng`, render peta on-demand. Hanya selfie yang jadi file (via M16).
- **Q5 — Retensi foto selfie** = fase 2 (purge terjadwal > N bulan, configurable) — data pribadi/UU PDP.

### Dampak ke model data (revisi §3)

Tambahan kolom migration:
```php
$t->string('approval_status')->default('approved')->after('source'); // approved|pending|rejected
$t->text('approval_note')->nullable()->after('approval_status');      // alasan reject / catatan manajer
$t->foreignId('approved_by')->nullable()->after('approval_note');     // manajer yg memutuskan
```

### Dampak ke rencana delivery (revisi §6)

Tambah **M22-5 — Approval absen luar-radius**: list `pending` untuk manajer (selfie+peta+jarak) → approve/reject+alasan → notif karyawan; laporan hanya hitung `approved`. Urutan: M22-1 → M22-2 → M22-3 → M22-5 → M22-4.

---

## 9. Definition of Done (per sub-modul)

- [ ] Kode nyambung ke `presences` + service existing (nol duplikasi geofence)
- [ ] QR Mode TIDAK regresi (scanner pintu tetap jalan)
- [ ] PHPUnit hijau + full regression hijau
- [ ] Playwright m22 hijau (kamera+geo di-fake, bukan API bypass)
- [ ] Bukti visual: screenshot check-in (kamera+peta), riwayat, admin Show — verifikasi `vision_analyze`
- [ ] Mockup HTML disepakati sebelum koding tiap layar baru
