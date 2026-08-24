# Modul 12 — Audit Log

| | |
|---|---|
| **URL** | `/admin/audit-log` |
| **Controller** | [AuditLogCrudController](../../app/Http/Controllers/Admin/AuditLogCrudController.php) |
| **Model / tabel** | `App\Models\AuditLog` / `audit_logs` |
| **Trait** | `App\Traits\Auditable` — dipasang pada model yang ingin dicatat |
| **Operasi** | Create ✖ · Read ✔ · Update ✖ · Delete ✖ (**read-only**) |

**Kolom:** `action`, `auditable_type`, `auditable_id`, `user_id`, `ip_address`,
`user_agent`, nilai lama/baru, `created_at`

Modul ini sengaja **tidak bisa ditulis lewat UI** — entri hanya lahir dari
trait `Auditable`, dan hanya hilang lewat command prune. Itulah yang membuatnya
layak disebut jejak audit.

---

## CREATE — harus tertutup

| ID | Skenario | Langkah | Expected | Status |
|---|---|---|---|---|
| AUD-C-01 | Create ditutup | Buka `/admin/audit-log/create` | **404** — `denyAccess(['create','update','delete'])` | ✅ |
| AUD-C-02 | Tidak ada tombol Add | Amati halaman list | Tombol tambah tidak tampil | ⬜ |
| AUD-C-03 | POST langsung | Kirim `POST /admin/audit-log` | Ditolak | ⬜ |

## READ

| ID | Skenario | Langkah | Expected | Status |
|---|---|---|---|---|
| AUD-R-01 | List termuat | Buka `/admin/audit-log` | "Menampilkan 1 hingga 10 dari **184** masukan." pada data demo | 🌐 |
| AUD-R-02 | Detail entri | `/admin/audit-log/1/show` | Nilai lama vs baru tampil | ⬜ |
| AUD-R-03 | Kolom aktor | Amati kolom user | Nama pelaku, bukan id mentah | ⬜ |
| AUD-R-04 | Kolom IP & user agent | Amati kolom | Terisi untuk aksi lewat web | ⬜ |
| AUD-R-05 | Urutan | Amati urutan default | Terbaru di atas | ⬜ |
| AUD-R-06 | Filter | Gunakan filter bar | Menyempit per aksi / model / tanggal | ⬜ |
| AUD-R-07 | Paginasi | Navigasi halaman | Berfungsi pada 184 entri | ⬜ |

## PENCATATAN — apa yang harus masuk

| ID | Skenario | Aksi | Expected | Status |
|---|---|---|---|---|
| AUD-X-01 | Create tercatat | Buat karyawan baru | Entri `created` dengan nilai baru | ⬜ |
| AUD-X-02 | Update tercatat | Ubah nomor telepon karyawan | Entri `updated` berisi nilai **lama dan baru** | ⬜ |
| AUD-X-03 | Delete tercatat | Hapus sebuah record | Entri `deleted` dengan nilai terakhir | ⬜ |
| AUD-X-04 | Pelaku benar | Login sebagai Rina → ubah data | `user_id` = Rina, bukan yang lain | ⬜ |
| AUD-X-05 | Aksi lewat command | Jalankan `salary:recalculate` | Perilaku terdefinisi — pelaku sistem, bukan null yang membingungkan | ⬜ |
| AUD-X-06 | Aksi approval | Approve sebuah pengajuan | Tercatat | ⬜ |
| AUD-X-07 | Model tanpa trait | Ubah model yang **tidak** pakai `Auditable` | Tidak tercatat — memang begitu rancangannya | ⬜ |
| AUD-X-08 | Field sensitif | Ubah password karyawan | Hash password **tidak** tersimpan mentah di log | ⬜ |

## UPDATE / DELETE — harus tertutup

| ID | Skenario | Langkah | Expected | Status |
|---|---|---|---|---|
| AUD-U-01 | Edit ditutup | Buka `/admin/audit-log/1/edit` | 404 | ⬜ |
| AUD-D-01 | Delete ditutup | Coba hapus entri | 404 / tombol tidak ada | ⬜ |
| AUD-D-02 | Tidak bisa dihapus lewat UI | Kirim `DELETE` langsung | Ditolak | ⬜ |

## PRUNE

| ID | Skenario | Langkah | Expected | Status |
|---|---|---|---|---|
| AUD-X-09 | Command berjalan | `php artisan audit:prune --days=90` | Berjalan tanpa error | ⬜ |
| AUD-X-10 | Entri lama terhapus | Buat entri bertanggal 120 hari lalu → prune | Terhapus | ⬜ |
| AUD-X-11 | Entri baru bertahan | Entri 30 hari lalu → prune 90 hari | **Tetap ada** | ⬜ |
| AUD-X-12 | Parameter hari | `--days=30` | Ambang mengikuti parameter | ⬜ |
| AUD-X-13 | Terjadwal | Cek `app/Console/Kernel.php` | Berjalan bulanan | ⬜ |
| AUD-X-14 | Volume besar | Prune pada puluhan ribu entri | Selesai tanpa kehabisan memori | ⬜ |

## AKSES

| ID | Role | Permission | Expected | Status |
|---|---|---|---|---|
| AUD-A-01 | SA | `audit.view` | Boleh melihat | ✅ 200 |
| AUD-A-02 | HR | `audit.view` | Boleh melihat | ✅ 200 |
| AUD-A-03 | MGR | **Tidak punya** `audit.view` | ⚠️ Tetap dapat 200 — seharusnya ditolak (DEF-03) | ⚠️ |
| AUD-A-04 | EMP | — | Dialihkan ke `/my` | 🌐 |

> AUD-A-03 lebih serius daripada kebocoran akses lain di DEF-03: jejak audit
> memuat riwayat perubahan seluruh karyawan, termasuk data yang manager tidak
> berhak lihat lewat modul aslinya.
