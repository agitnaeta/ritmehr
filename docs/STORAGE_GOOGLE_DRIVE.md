# Storage — Google Drive (M16 Fase 2)

> Menyimpan berkas dokumen & lampiran ke **Google Drive** milik pelanggan,
> dikonfigurasi dari **Pengaturan → Penyimpanan** (tanpa `.env`).

## Prasyarat paket
Sudah terpasang:
```
composer require masbug/flysystem-google-drive-ext   # + google/apiclient
```

## Langkah dapat kredensial (client id, secret, refresh token)

### 1. Google Cloud Console — buat OAuth Client
1. Buka https://console.cloud.google.com → buat / pilih Project.
2. **APIs & Services → Library** → aktifkan **Google Drive API**.
3. **APIs & Services → OAuth consent screen** → set (External/Internal), isi app name, tambahkan scope `.../auth/drive`, tambahkan email kamu sebagai **Test user** (kalau consent screen masih Testing).
4. **APIs & Services → Credentials → Create Credentials → OAuth client ID**:
   - Application type: **Web application**
   - Authorized redirect URIs: tambah `https://developers.google.com/oauthplayground`
   - Simpan → catat **Client ID** & **Client Secret**.

### 2. OAuth Playground — dapatkan Refresh Token
1. Buka https://developers.google.com/oauthplayground
2. Klik ⚙️ (kanan atas) → centang **Use your own OAuth credentials** → isi Client ID & Secret di atas.
3. Panel kiri, scope: masukkan `https://www.googleapis.com/auth/drive` → **Authorize APIs** → login & izinkan.
4. **Exchange authorization code for tokens** → salin **Refresh token**.

### 3. Isi di aplikasi
**Pengaturan → Penyimpanan**:
| Field | Isi |
|-------|-----|
| Provider Penyimpanan | **Google Drive** |
| Google Drive Client ID | dari langkah 1 |
| Google Drive Client Secret | dari langkah 1 |
| Google Drive Refresh Token | dari langkah 2 |
| Folder Tujuan (opsional) | nama folder, mis. `HRIS-Dokumen` (kosong = root) |

Simpan → klik **Tes Koneksi Penyimpanan**. Kalau hijau, semua upload dokumen &
lampiran jurnal mulai masuk ke Drive tersebut.

## Cara kerja di kode
- Driver kustom `google` didaftarkan di `AppServiceProvider::boot()` via
  `Storage::extend('google', …)` → `Masbug\Flysystem\GoogleDriveAdapter`
  (`useDisplayPaths = true`, jadi path berbentuk nama folder/berkas biasa).
- `StorageManager::googleConfig()` merakit `clientId/clientSecret/refreshToken/folder`
  dari setting (semua secret terenkripsi at-rest).
- `StorageManager::disk()` membangun disk runtime via `Storage::build()` — dipakai
  `DocumentService` & `JournalService`.

## Troubleshooting
- **`invalid_grant` saat Tes Koneksi:** refresh token kedaluwarsa/di-revoke, atau
  consent screen masih "Testing" & user bukan test user. Buat ulang refresh token.
- **`insufficient permission`:** scope kurang — pastikan `auth/drive` (bukan `drive.file`).
- **File tak muncul di folder:** cek nama/ID **Folder Tujuan**; kalau folder di
  Shared Drive butuh konfigurasi tambahan (di luar scope fase ini).
- **Lambat pada file besar:** Drive API lebih lambat dari S3; wajar.
