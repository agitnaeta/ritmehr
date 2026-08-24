# Modul 01 — Users

| | |
|---|---|
| **URL** | `/admin/user` |
| **Controller** | [UserCrudController](../../app/Http/Controllers/Admin/UserCrudController.php) |
| **Model / tabel** | `App\Models\User` / `users` |
| **Validasi** | [UserRequest](../../app/Http/Requests/UserRequest.php) — aturan **berbeda** untuk create dan update |
| **Operasi** | Create ✔ · Read ✔ · Update ✔ · Delete ✔ · Export ✔ · Cetak ID card ✔ |

## Field form

`name`, `email`, `password`, `phone`, `address`, `image`, `employee_id`,
`join_date`, `employment_status`, `department_id`, `position_id`, `branch_id`,
`manager_id`, `schedule_id`

## Aturan validasi

| Field | Create | Update |
|---|---|---|
| `name` | `required\|string` | `required\|string` |
| `email` | `required\|email\|unique:users,email` | `required\|email\|unique` **ignore id sendiri** |
| `password` | `required\|string` | `string\|nullable` |
| `image` | **`required`**`\|file\|mimes:jpg,png,webp` | `file\|mimes:jpg,png,webp` (opsional) |
| `schedule_id` | `nullable\|integer` | `nullable\|integer` |

> Perbedaan create vs update ini disengaja dan **sudah benar**: saat edit, email
> mengabaikan id sendiri sehingga menyimpan tanpa mengubah email tidak ditolak,
> dan password kosong berarti "jangan ubah".

---

## CREATE

| ID | Skenario | Data | Expected | Status |
|---|---|---|---|---|
| USER-C-01 | Buka form create | — | Form tampil dengan 14 field | ✅ 200 |
| USER-C-02 | Create lengkap | Semua field wajib + foto | Tersimpan, redirect ke list, muncul di tabel | ⬜ |
| USER-C-03 | Create minimum | `name` + `email` + `password` + `image` | Tersimpan; field opsional boleh kosong | ⬜ |
| USER-C-04 | Simpan lalu tambah lagi | `_save_action=save_and_new` | Form kosong kembali, data pertama tersimpan | ⬜ |
| USER-C-05 | Password ter-hash | Create user → cek kolom `password` di DB | Nilai ter-hash bcrypt, bukan teks polos | ⬜ |
| USER-C-06 | QR ter-generate | Create user → buka detail | Kolom QR terisi, bisa dipakai untuk scan | ⬜ |

## READ

| ID | Skenario | Langkah | Expected | Status |
|---|---|---|---|---|
| USER-R-01 | List termuat | Buka `/admin/user` | Tabel AJAX `#crudTable` berisi 5 baris — "Menampilkan 1 hingga 5 dari 5 masukan." | 🌐 |
| USER-R-02 | Detail / show | `/admin/user/1/show` | Seluruh atribut tampil | ⬜ |
| USER-R-03 | Pencarian | Ketik "Ahmad" di kotak cari | Tabel menyempit ke 1 baris | ⬜ |
| USER-R-04 | Paginasi | Set 10 per halaman dengan >10 user | Navigasi halaman berfungsi | ⬜ |
| USER-R-05 | Filter | Gunakan filter bar (`HasSimpleFilters`) | Hasil menyempit, parameter terbaca di URL | ⬜ |
| USER-R-06 | Kolom relasi | Amati kolom departemen / jabatan / cabang | Menampilkan nama, bukan id mentah | ⬜ |

## UPDATE

| ID | Skenario | Data | Expected | Status |
|---|---|---|---|---|
| USER-U-01 | Buka form edit | `/admin/user/1/edit` | Form terisi nilai saat ini | ✅ 200 |
| USER-U-02 | Ubah satu field | Ubah `phone` saja → Save | Tersimpan, field lain tidak berubah | ⬜ |
| USER-U-03 | Simpan tanpa ubah email | Save tanpa menyentuh email | **Berhasil** — unique mengabaikan id sendiri | ⬜ |
| USER-U-04 | Simpan tanpa isi password | Kosongkan password → Save | Password lama tetap berlaku; uji dengan login ulang | ⬜ |
| USER-U-05 | Ganti password | Isi password baru → Save | Login lama gagal, login baru berhasil | ⬜ |
| USER-U-06 | Simpan tanpa unggah foto | Biarkan `image` kosong saat edit | **Berhasil** — foto lama dipertahankan | ⬜ |
| USER-U-07 | Ganti atasan | Set `manager_id` = Budi | Rantai approval & struktur organisasi ikut berubah | ⬜ |
| USER-U-08 | Ubah status kepegawaian | Set `resigned` | Hilang dari headcount & payroll, riwayat tetap utuh | ⬜ |
| USER-U-09 | Pindah cabang | Ganti `branch_id` → hitung ulang presensi lama | Presensi lama **tetap** memakai cabang saat itu | ⬜ |
| USER-U-10 | Perubahan tercatat audit | Edit user → buka `/admin/audit-log` | Entri baru berisi nilai lama vs baru | ⬜ |

## DELETE

| ID | Skenario | Langkah | Expected | Status |
|---|---|---|---|---|
| USER-D-01 | Konfirmasi hapus | Klik Delete | Muncul dialog konfirmasi lebih dulu | ⬜ |
| USER-D-02 | Hapus user tanpa relasi | Hapus user yang baru dibuat | Terhapus, hilang dari list | ⬜ |
| USER-D-03 | Hapus user berelasi | Hapus user yang punya presensi & rekap gaji | Ditolak, **atau** relasi tertangani — tidak boleh menyisakan baris yatim |  ⬜ |
| USER-D-04 | Batal hapus | Klik Delete → Cancel | Data tetap ada | ⬜ |

## VALIDASI

| ID | Skenario | Data | Expected | Status |
|---|---|---|---|---|
| USER-V-01 | Submit kosong | Semua field kosong | ✅ 302 kembali ke form, tidak tersimpan | ✅ |
| USER-V-02 | Create tanpa foto | `name`+`email`+`password`, `image` kosong | ✅ **Ditolak** — foto wajib saat create; user tidak dibuat | ✅ |
| USER-V-03 | Email tidak valid | `email=bukanemail` | Ditolak, pesan format email | ⬜ |
| USER-V-04 | Email duplikat (create) | `email=ahmad@demo.test` | Ditolak, pesan sudah terpakai | ⬜ |
| USER-V-05 | Email duplikat (update) | Edit Dewi, set email = milik Ahmad | Ditolak | ⬜ |
| USER-V-06 | Format foto salah | Unggah `.pdf` atau `.gif` | Ditolak — hanya `jpg`, `png`, `webp` | ⬜ |
| USER-V-07 | `schedule_id` bukan angka | `schedule_id=abc` | Ditolak | ⬜ |
| USER-V-08 | Pesan error tampil | Kirim form tidak valid | Pesan muncul **di bawah field terkait**, bukan 500 | ⬜ |

## AKSES per role

| ID | Skenario | Role | Expected | Status |
|---|---|---|---|---|
| USER-A-01 | super_admin | SA | Akses penuh CRUD | ✅ 200 |
| USER-A-02 | hr_admin | HR | Akses penuh CRUD | ✅ 200 |
| USER-A-03 | manager — baca | MGR | Boleh melihat | ✅ 200 |
| USER-A-04 | manager — tulis | MGR | ⚠️ **Seharusnya ditolak** (tidak punya `user.create`/`user.edit`), tetapi form create terbuka dan simpan berhasil — DEF-03 | ⚠️ |
| USER-A-05 | manager — scope tim | MGR | ⚠️ **Gagal** — melihat 5 dari 5 user, bukan hanya timnya — DEF-04 | 🌐 ⚠️ |
| USER-A-06 | employee | EMP | Dialihkan ke `/my`, tidak pernah melihat halaman | 🌐 |

## OPERASI KHUSUS

| ID | Skenario | Langkah | Expected | Status |
|---|---|---|---|---|
| USER-X-01 | Export Excel | `/admin/user/export` | ✅ 200, `.xlsx` ±6,9 KB berisi 5 baris | ✅ |
| USER-X-02 | Isi file export | Buka berkas hasil unduh | Header kolom benar, tidak ada kolom sensitif (hash password) | ⬜ |
| USER-X-03 | Cetak ID card satuan | `/admin/user/1/print` | ✅ Bila `id_card` di Profil Perusahaan kosong → dialihkan ke `/admin/company-profile` (guard benar). Bila terisi → PDF | ✅ |
| USER-X-04 | Cetak semua ID card | `/admin/user/print-all` | ✅ Perilaku sama, berlaku massal | ✅ |
| USER-X-05 | Isi ID card | Unggah background → cetak | Nama, foto, dan QR karyawan tercetak di posisi benar | ⬜ |
