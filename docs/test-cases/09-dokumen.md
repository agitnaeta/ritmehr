# Modul 09 — Dokumen

Dropdown **Dokumen**: Dokumen Karyawan, Kelengkapan Dokumen, Jenis Dokumen.

Prinsip keamanan modul ini: berkas disimpan di **disk `local` (privat)**, bukan
`public`. Dokumen identitas dan kontrak tidak boleh dijangkau dengan menebak URL.
Unduhan selalu mengalir lewat aplikasi setelah pemeriksaan hak akses.

---

## 9.1 Jenis Dokumen — `/admin/document-type`

**Field:** `name`, `code`, `allowed_extensions`, `max_file_size_mb`,
`has_expiry`, `is_required`

**Validasi:**

| Field | Aturan |
|---|---|
| `name` | `required\|string\|max:100` |
| `code` | `required\|string\|max:20\|unique:document_types,code` |
| `max_file_size_mb` | `nullable\|integer\|min:1\|max:100` |

| ID | Skenario | Data | Expected | Status |
|---|---|---|---|---|
| DTY-C-01 | Buka form | — | Form 6 field | ✅ 200 |
| DTY-C-02 | Buat jenis dokumen | KTP, `pdf,jpg`, 5 MB, wajib | Tersimpan | ⬜ |
| DTY-C-03 | Jenis berkedaluwarsa | `has_expiry=1` | Pengunggahan wajib mengisi tanggal kedaluwarsa | ⬜ |
| DTY-R-01 | List | Buka list | 8 jenis pada data demo | ⬜ |
| DTY-U-01 | Ubah ekstensi diizinkan | `pdf` saja | Unggahan `.jpg` berikutnya ditolak | ⬜ |
| DTY-U-02 | Ubah batas ukuran | Turunkan ke 1 MB | Unggahan lebih besar ditolak | ⬜ |
| DTY-U-03 | Jadikan wajib | `is_required=1` | Muncul di checklist kelengkapan | ⬜ |
| DTY-D-01 | Hapus jenis terpakai | Delete yang punya dokumen | Ditolak atau dokumen tertangani | ⬜ |
| DTY-V-01 | Submit kosong | Semua kosong | ✅ 302 kembali ke form (validasi jalan) | ✅ |
| DTY-V-02 | Kode duplikat | Kode yang sudah ada | Ditolak — `unique` | ⬜ |
| DTY-V-03 | Ukuran > 100 MB | `max_file_size_mb=500` | Ditolak — `max:100` | ⬜ |
| DTY-V-04 | Ukuran 0 | `max_file_size_mb=0` | Ditolak — `min:1` | ⬜ |

---

## 9.2 Dokumen Karyawan — `/admin/employee-document`

| | |
|---|---|
| **Controller** | [EmployeeDocumentController](../../app/Http/Controllers/Admin/EmployeeDocumentController.php) — controller biasa, bukan CRUD Backpack |
| **Operasi** | Create ✔ · Read ✔ · Update ✖ · Delete ✔ · Unduh ✔ |

**Field:** `user_id`, `document_type_id`, `document_number`, `file`,
`issued_date`, `expiry_date`, `notes`

### CREATE

| ID | Skenario | Data | Expected | Status |
|---|---|---|---|---|
| DOC-C-01 | Buka form | — | 200; 7 field | ✅ |
| DOC-C-02 | Unggah dokumen valid | PDF sesuai jenis | Tersimpan di disk **`local`**, bukan `public` | ⬜ |
| DOC-C-03 | Lokasi berkas | Cek `storage/app/` vs `storage/app/public/` | Berada di jalur privat | ⬜ |
| DOC-C-04 | Nama berkas | Cek nama tersimpan | Di-randomkan / tidak bisa ditebak | ⬜ |
| DOC-C-05 | Dengan kedaluwarsa | Jenis `has_expiry=1` + tanggal | Tersimpan; masuk pemantauan kedaluwarsa | ⬜ |

### READ / DOWNLOAD

| ID | Skenario | Langkah | Expected | Status |
|---|---|---|---|---|
| DOC-R-01 | List | Buka `/admin/employee-document` | 200; daftar dokumen | ✅ |
| DOC-R-02 | Data demo kosong | Buka list pada seed default | Tidak ada dokumen — `/admin/employee-document/1/download` **404** (bukan bug) | ✅ |
| DOC-R-03 | Unduh sebagai HR | Unduh dokumen karyawan lain | Berhasil — HR melihat semua | ⬜ |
| DOC-R-04 | Unduh sebagai pemilik | Unduh dokumen sendiri | Berhasil | ⬜ |
| DOC-R-05 | **Unduh milik orang lain** | Karyawan A unduh dokumen karyawan B | **Ditolak** (403/404) | ⬜ |
| DOC-R-06 | **URL ditebak** | Akses jalur berkas langsung tanpa lewat aplikasi | **Tidak dapat diakses** — disk privat | ⬜ |
| DOC-R-07 | Header unduhan | Amati response | `Content-Disposition: attachment`, tipe konten benar | ⬜ |

### DELETE

| ID | Skenario | Langkah | Expected | Status |
|---|---|---|---|---|
| DOC-D-01 | Hapus dokumen | `POST /admin/employee-document/{id}/delete` | Record terhapus | ⬜ |
| DOC-D-02 | **Berkas fisik ikut terhapus** | Cek disk setelah delete | Berkas hilang dari storage — tidak menyisakan sampah | ⬜ |
| DOC-D-03 | Hapus milik orang lain | Karyawan A hapus dokumen B | **Ditolak** | ⬜ |

### VALIDASI

| ID | Skenario | Data | Expected | Status |
|---|---|---|---|---|
| DOC-V-01 | Tanpa berkas | Submit tanpa `file` | Ditolak | ⬜ |
| DOC-V-02 | **Ekstensi tidak diizinkan** | Unggah `.exe` saat hanya `pdf,jpg` | Ditolak | ⬜ |
| DOC-V-03 | **Melebihi ukuran maks** | Berkas > `max_file_size_mb` | Ditolak dengan pesan, bukan 500 / timeout | ⬜ |
| DOC-V-04 | **Kedaluwarsa wajib** | Jenis `has_expiry=1` tanpa `expiry_date` | Ditolak | ⬜ |
| DOC-V-05 | Kedaluwarsa di masa lalu | `expiry_date` kemarin | Diterima dengan peringatan, atau ditolak — perilaku terdefinisi | ⬜ |
| DOC-V-06 | Terbit setelah kedaluwarsa | `issued_date` > `expiry_date` | Ditolak | ⬜ |
| DOC-V-07 | Berkas berbahaya | Unggah `.pdf` berisi skrip | Tetap disimpan sebagai berkas, tidak pernah dieksekusi | ⬜ |

---

## 9.3 Kelengkapan Dokumen — `/admin/employee-document/completeness`

| ID | Skenario | Langkah | Expected | Status |
|---|---|---|---|---|
| DOC-X-01 | Halaman termuat | Buka halaman | 200 | ✅ |
| DOC-X-02 | Dokumen wajib kurang | Karyawan belum unggah jenis `is_required=1` | Ditandai belum lengkap | ⬜ |
| DOC-X-03 | Setelah dilengkapi | Unggah yang kurang → refresh | Berubah jadi lengkap | ⬜ |
| DOC-X-04 | Jenis tidak wajib | Kosongkan jenis `is_required=0` | **Tidak** membuat status jadi tidak lengkap | ⬜ |
| DOC-X-05 | Karyawan resigned | Set satu karyawan resigned | Tidak ikut dihitung dalam checklist aktif | ⬜ |

## 9.4 Notifikasi kedaluwarsa

| ID | Skenario | Langkah | Expected | Status |
|---|---|---|---|---|
| DOC-X-06 | Command berjalan | `php artisan documents:notify-expiring --days=30` | Berjalan tanpa error | ⬜ |
| DOC-X-07 | Dokumen mendekati kedaluwarsa | Set `expiry_date` 15 hari lagi → jalankan | Notifikasi terkirim ke pemilik | ⬜ |
| DOC-X-08 | Dokumen masih lama | `expiry_date` 90 hari lagi | **Tidak** dinotifikasi | ⬜ |
| DOC-X-09 | Jalankan dua kali | Ulangi command hari yang sama | Tidak mengirim notifikasi ganda | ⬜ |
| DOC-X-10 | Terjadwal | Cek `app/Console/Kernel.php` | Senin 07:30 | ⬜ |

## AKSES

| ID | Role | Expected | Status |
|---|---|---|---|
| DOC-A-01 | SA / HR | Akses penuh, lihat dokumen semua karyawan | ✅ 200 |
| DOC-A-02 | MGR | ⚠️ Terbuka penuh meski tidak punya permission dokumen (DEF-03) | ⚠️ |
| DOC-A-03 | EMP | Dialihkan dari admin; hanya dokumen sendiri | 🌐 |
