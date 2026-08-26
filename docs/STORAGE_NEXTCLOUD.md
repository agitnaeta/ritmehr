# Storage — Nextcloud / ownCloud (WebDAV) (M16 Fase 3)

> Menyimpan berkas dokumen & lampiran ke **Nextcloud / ownCloud** (atau server
> WebDAV apa pun) milik pelanggan, dikonfigurasi dari **Pengaturan → Penyimpanan**.

## Prasyarat paket
Sudah terpasang:
```
composer require league/flysystem-webdav   # + sabre/dav
```

## Langkah koneksi

### 1. Siapkan kredensial di Nextcloud
1. Login ke Nextcloud pelanggan.
2. **Settings → Security → Devices & sessions → Create new app password**
   (disarankan daripada password akun utama). Catat nama user + app password.
3. Cari **Base URI WebDAV** — formatnya:
   ```
   https://<domain-nextcloud>/remote.php/dav/files/<USERNAME>/
   ```
   (ownCloud sama polanya. Trailing slash disarankan.)

### 2. Isi di aplikasi
**Pengaturan → Penyimpanan**:
| Field | Isi |
|-------|-----|
| Provider Penyimpanan | **Nextcloud / ownCloud (WebDAV)** |
| WebDAV Base URI | `https://cloud.contoh.com/remote.php/dav/files/hris/` |
| WebDAV Username | user Nextcloud |
| WebDAV Password / App Password | app password dari langkah 1 |
| Subfolder (opsional) | mis. `HRIS` (kosong = root user) |

Simpan → **Tes Koneksi Penyimpanan**. Kalau hijau, upload dokumen & lampiran
jurnal mulai masuk ke Nextcloud tersebut.

## Cara kerja di kode
- Driver kustom `webdav` didaftarkan di `AppServiceProvider::boot()` via
  `Storage::extend('webdav', …)` → `Sabre\DAV\Client` + `League\Flysystem\WebDAV\WebDAVAdapter`.
- `StorageManager::webdavConfig()` merakit `baseUri/userName/password/prefix` dari
  setting (password terenkripsi at-rest).
- `StorageManager::disk()` membangun disk runtime via `Storage::build()` — dipakai
  `DocumentService` & `JournalService`.

## Troubleshooting
- **401 Unauthorized saat Tes Koneksi:** username/app-password salah, atau akun
  pakai 2FA (wajib App Password, bukan password login).
- **404 / path salah:** Base URI harus mengandung `/remote.php/dav/files/<user>/`.
  Salah satu penyebab umum: lupa username di akhir URI.
- **SSL error:** sertifikat server tidak valid; pakai domain ber-HTTPS yang benar.
- **Lambat pada banyak file:** WebDAV listing lebih lambat dari S3; wajar.
