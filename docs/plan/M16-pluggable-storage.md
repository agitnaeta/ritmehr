# M16 — Pluggable Storage Backends (Local / S3 / Google Drive / Nextcloud)

> **Status:** ✅ FASE 1, 2 & 3 DONE (2026-08-24) — S3-compatible + Google Drive + Nextcloud/WebDAV.
>            Fase 4 (migrasi berkas antar-disk) 🎨 opsional.
> **Prioritas:** 🟠 CC-4 (E2 + E4) — cross-cutting, lanjutan M06/M15
> **Konteks Capt:** onboarding pelanggan yang SUDAH punya storage sendiri
> (Google Drive / S3 / Nextcloud). Semua opsi harus punya **halaman konfigurasi**
> untuk dikoneksikan dari UI, bukan `.env`.

## ✅ Fase 3 — Hasil Implementasi (Nextcloud / ownCloud / WebDAV)
- **Paket** `league/flysystem-webdav ^3.0` (v3.31.0) + `sabre/dav` (v4.7.1).
- **Driver kustom `webdav`** di `AppServiceProvider::boot()` via `Storage::extend` → `Sabre\DAV\Client` + `WebDAVAdapter`.
- **StorageManager**: `provider()` terima `webdav`, `webdavConfig()` rakit baseUri/userName/password/prefix dari setting, `label()` = "Nextcloud/WebDAV (baseUri)".
- **Setting M15**: `storage_webdav_base_uri/username/password(encrypted)/prefix`. Provider dropdown tambah "Nextcloud / ownCloud (WebDAV)".
- **UI**: field WebDAV muncul kondisional saat provider=webdav; tombol Tes Koneksi dipakai bersama.
- **Docs** `docs/STORAGE_NEXTCLOUD.md`: cara App Password + Base URI + troubleshooting.

### Automation Test (Fase 3)
- **PHPUnit** `StorageManagerTest` 15/15 (+4 webdav: provider diterima, rakit config, **driver `webdav` resolve & build disk**, password terenkripsi).
- **Playwright** `m16-storage.mjs` 6/6 (+TC-STG-65: field WebDAV kondisional).
- **Regression** `php artisan test` → **230 passed (488 assertions)**, nol regresi.

### Sisa
- **Fase 4** (opsional) command migrasi berkas antar-disk saat pelanggan pindah provider.

## ✅ Fase 2 — Hasil Implementasi (Google Drive)
- **Paket** `masbug/flysystem-google-drive-ext ^2.0` (v2.5.0) + `google/apiclient` (v2.19.4).
- **Driver kustom `google`** didaftarkan di `AppServiceProvider::boot()` via `Storage::extend` → `GoogleDriveAdapter` (`useDisplayPaths=true`).
- **StorageManager**: `provider()` terima `google`, `googleConfig()` rakit clientId/secret/refreshToken/folder dari setting, `label()` = "Google Drive".
- **Setting M15**: `storage_gdrive_client_id/client_secret/refresh_token` (encrypted) + `storage_gdrive_folder`. Provider dropdown tambah "Google Drive".
- **UI**: field Google Drive muncul kondisional (JS group-based) saat provider=google; tombol Tes Koneksi dipakai bersama.
- **Docs** `docs/STORAGE_GOOGLE_DRIVE.md`: panduan lengkap dapat client id/secret (Google Cloud Console) + refresh token (OAuth Playground) + troubleshooting.

### Automation Test (Fase 2)
- **PHPUnit** `StorageManagerTest` 11/11 (+5 google: provider diterima, rakit config, folder default root, **driver `google` resolve & build disk**, secret gdrive terenkripsi).
- **Playwright** `m16-storage.mjs` 5/5 (+TC-STG-64: field Google Drive kondisional).
- **Regression** `php artisan test` → **226 passed (478 assertions)**, nol regresi.

### Sisa (fase berikut)
- **Fase 3** Nextcloud/WebDAV: `league/flysystem-webdav` + keys `storage_webdav_*`; SFTP bonus.
- **Fase 4** (opsional) command migrasi berkas antar-disk.

## ✅ Fase 1 — Hasil Implementasi (S3-compatible + kerangka)
- **Paket** `league/flysystem-aws-s3-v3 ^3.0` (v3.35.3) di-install.
- **`StorageManager`** (`app/Services/StorageManager.php`) — sumber tunggal:
  `provider()`, `diskConfig()` (rakit config S3 dari setting), `disk()` (via `Storage::build()`, runtime, tanpa sentuh `filesystems.php`), `label()`, `testConnection()` (tulis→baca→hapus probe, tangkap error jadi pesan rapi), fallback aman ke `local`.
- **Setting M15 (group storage)**: `storage_provider` (local/s3) + `storage_s3_key/secret` (encrypted) + `storage_s3_region/bucket/endpoint/path_style`. Field lama `document_disk` diganti.
- **Wiring E5**: `DocumentService::disk()` & `JournalService::disk()` kini instance dari StorageManager → dokumen karyawan + lampiran jurnal ikut provider aktif (store/download/delete). Controller download disesuaikan.
- **UI**: tab Penyimpanan — dropdown provider + **field S3 muncul kondisional** (JS by `data-setting-row`), tombol **"Tes Koneksi Penyimpanan"** (endpoint `settings/test-storage`), panel Status Integrasi jadi provider-aware ("Lokal — OK" / "S3-compatible (endpoint) — …").
- **S3-compatible**: satu form melayani AWS + MinIO + Wasabi + Cloudflare R2 (via `endpoint` + `path_style`).

### Automation Test (Fase 1)
- **PHPUnit** `StorageManagerTest` 6/6 (default local, test-connection local OK, rakit config S3, tanpa-endpoint omit path-style, region fallback, secret terenkripsi at-rest) + `DocumentServiceTest` 14/14 (disesuaikan ke instance disk).
- **Playwright** `m16-storage.mjs` 4/4 (dropdown provider, field S3 kondisional, tombol tes, tes koneksi local sukses).
- **Regression** `php artisan test` → **221 passed (467 assertions)**, nol regresi.

### Sisa (fase berikut)
- **Fase 2** Google Drive: `composer require masbug/flysystem-google-drive-ext` + keys `storage_gdrive_*` + panduan refresh token.
- **Fase 3** Nextcloud/WebDAV: `league/flysystem-webdav` + keys `storage_webdav_*`; SFTP bonus.
- **Fase 4** (opsional) command migrasi berkas antar-disk.

---

## Rencana Awal (arsip)


## Masalah Sekarang (verifikasi kode)
- `config/filesystems.php` hanya define `local`, `public`, `s3` — semua kredensial dari `.env`.
- Setting `document_disk` (M15) hanya menawarkan `local`/`s3`, **tanpa field kredensial** di UI.
- `DocumentService::disk()` (M06) sudah baca setting → tinggal backend-nya diperkaya.
- **Gap:** pelanggan tak bisa connect storage mereka sendiri tanpa akses `.env` server.

## Sasaran (Definition of Done)
1. Admin bisa pilih **provider storage** dari UI: Lokal, Amazon S3 (+ S3-compatible: MinIO/Wasabi/R2), Google Drive, Nextcloud/WebDAV.
2. **Halaman konfigurasi per-provider** dengan field kredensial yang relevan (muncul kondisional sesuai provider terpilih).
3. Tombol **"Tes Koneksi"** yang benar-benar menulis+baca+hapus file uji ke storage (bukan sekadar cek field terisi) — pola sama seperti "Tes WhatsApp" (M03).
4. Kredensial tersimpan **terenkripsi at-rest** (SettingService `encrypted`).
5. Disk dibangun **runtime dari setting** (bukan `.env`), dipakai oleh semua konsumen (dokumen, lampiran jurnal, dst).
6. Semua diuji: PHPUnit (build disk per provider, fallback, test-connection) + Playwright (form kondisional, tes koneksi).

## Evaluasi Bisnis (7 Poin)
- **E1 Proses bisnis** — onboarding: pilih provider → isi kredensial → tes → simpan → file masuk ke storage pelanggan.
- **E2 Integrasi keluar** — INI intinya: konek ke storage eksternal milik pelanggan, self-managed via UI.
- **E3 Tampilan** — form kondisional per provider + indikator status koneksi (hijau/merah) di panel status M15.
- **E4 Third-party config** — semua kredensial storage pindah dari `.env` ke Settings UI (super admin).
- **E5 Keterkaitan** — dipakai M06 (dokumen), M12 (lampiran jurnal `JournalService::DISK`), dan upload lain.
- **E6 Bahasa** — label ikut M13.
- **E7 Currency** — N/A.

## Arsitektur

### 1. Driver & dependency
| Provider | Driver Flysystem | Paket |
|----------|------------------|-------|
| Lokal | `local` | bawaan |
| Amazon S3 / S3-compatible | `s3` | `league/flysystem-aws-s3-v3` (sudah umum) |
| Google Drive | `google` | `masbug/flysystem-google-drive-ext` |
| Nextcloud / ownCloud | `webdav` | `league/flysystem-webdav` |
| (bonus) SFTP | `sftp` | `league/flysystem-sftp-v3` |

> Google Drive & WebDAV butuh composer require paket di atas. Dicatat di DoD
> sebagai prasyarat; kalau paket belum ada, provider itu ditandai "perlu instalasi".

### 2. `StorageManager` service (baru)
Sumber tunggal yang membangun disk dari setting:
- `provider(): string` — baca `setting('storage_provider','local')`.
- `diskConfig(): array` — rakit array config sesuai provider dari setting.
- `disk(): Filesystem` — `Storage::build($this->diskConfig())` (Laravel runtime disk, tanpa nulis `filesystems.php`).
- `testConnection(): array{ok:bool, message:string}` — tulis `health/probe_<uuid>.txt`, baca balik, hapus; tangkap exception → pesan rapi.
- Fallback aman ke `local` bila config invalid.

`DocumentService::disk()` & `JournalService` direfactor memanggil `StorageManager` (string name atau instance).

### 3. Setting keys baru (M15 group "storage")
```
storage_provider         select: local | s3 | google | webdav (| sftp)
# S3 / S3-compatible
storage_s3_key           password(encrypted)
storage_s3_secret        password(encrypted)
storage_s3_region        string
storage_s3_bucket        string
storage_s3_endpoint      string   (utk MinIO/Wasabi/R2; kosong=AWS)
storage_s3_path_style    bool     (MinIO/R2 = true)
# Google Drive
storage_gdrive_client_id      password(encrypted)
storage_gdrive_client_secret  password(encrypted)
storage_gdrive_refresh_token  password(encrypted)
storage_gdrive_folder_id      string  (folder tujuan, opsional)
# Nextcloud / WebDAV
storage_webdav_base_uri  string   (mis. https://cloud.contoh.com/remote.php/dav/files/USER/)
storage_webdav_username  string
storage_webdav_password  password(encrypted)
```
Field lama `document_disk` di-*deprecate* / dipetakan ke `storage_provider` (migrasi nilai).

### 4. Halaman konfigurasi (UI)
- Tab **Penyimpanan** di Settings M15 diperluas: dropdown Provider + **field kredensial yang muncul kondisional** (JS show/hide berdasar provider terpilih).
- Tombol **"Tes Koneksi"** (mirip Tes WhatsApp) → panggil `StorageManager::testConnection()` → flash sukses/gagal nyata.
- Panel **Status Integrasi** M15: baris "Penyimpanan" jadi provider-aware ("Aktif — Google Drive", dst).
- Petunjuk singkat per provider (cara ambil refresh token GDrive, path WebDAV Nextcloud).

### 5. Keamanan & operasional
- Semua secret `encrypted` (write-only, ter-mask `********` di UI — pola M15).
- Test-connection wajib sebelum "aktifkan", supaya salah kredensial ketahuan saat onboarding.
- **Migrasi berkas antar-disk**: di luar scope fase 1 (dokumen lama tetap di disk lama). Fase 2 opsional: command `documents:migrate-disk` untuk mindahin file existing.
- Timeout & error gateway tak boleh memutus aksi bisnis (tangkap, log, pesan rapi).

## Rencana Eksekusi (fase)
1. **Fase 1 (inti):** `StorageManager` + setting keys + S3-compatible penuh (AWS/MinIO/Wasabi/R2) + halaman config + tes koneksi + wire `DocumentService`/`JournalService`. (Paket S3 kemungkinan sudah ada.)
2. **Fase 2:** Google Drive (`composer require masbug/flysystem-google-drive-ext`) + panduan refresh token.
3. **Fase 3:** Nextcloud/WebDAV (`composer require league/flysystem-webdav`) + SFTP (bonus).
4. **Fase 4 (opsional):** command migrasi berkas antar-disk.

## Test Plan
- **PHPUnit:** `StorageManager` build config benar per provider; `testConnection` sukses (disk fake) & gagal (kredensial ngaco); fallback ke local; secret ter-enkripsi; `document_disk`→`storage_provider` mapping.
- **Playwright:** tab Penyimpanan tampil field kondisional per provider; tombol Tes Koneksi jalan (mode lokal → sukses); secret ter-mask; guard super_admin.
- **Regression** full hijau.

## Risiko / Catatan
- Google Drive OAuth: butuh client id/secret + refresh token — panduan wajib jelas saat onboarding (bagian tersulit buat user).
- S3-compatible (MinIO/R2/Wasabi) cukup pakai driver `s3` + `endpoint` + `path_style` → satu form melayani banyak vendor.
- Nextcloud = WebDAV standar; base URI harus tepat (`/remote.php/dav/files/<user>/`).
- Ukuran/paket: hanya require paket saat provider dipakai, supaya footprint tetap kecil.
