# Modul 05 — Profil Perusahaan

| | |
|---|---|
| **URL** | `/admin/company-profile` |
| **Controller** | [CompanyProfileCrudController](../../app/Http/Controllers/Admin/CompanyProfileCrudController.php) |
| **Model / tabel** | `App\Models\CompanyProfile` / `company_profiles` |
| **Validasi** | [CompanyProfileRequest](../../app/Http/Requests/CompanyProfileRequest.php) |
| **Operasi** | Create ✔ · Read ✔ · Update ✔ · Delete ✔ |

**Field:** `name`, `address`, `phone`, `image` (logo), `id_card` (background ID card)

**Validasi:**

| Field | Create | Update |
|---|---|---|
| `image` | `required\|file\|mimes:jpg,png,webp` | `file\|mimes:jpg,png,webp` |
| `id_card` | `file\|mimes:jpg,png,webp` | `file\|mimes:jpg,png,webp` |

Modul ini kecil tetapi berpengaruh luas: **logo dipakai slip gaji PDF**, dan
**background ID card dipakai cetak kartu karyawan**. Dua defect cetak di
aplikasi berakar di sini.

---

## CREATE

| ID | Skenario | Data | Expected | Status |
|---|---|---|---|---|
| COMP-C-01 | Buka form | — | Form 5 field | ✅ 200 |
| COMP-C-02 | Buat profil lengkap | Nama, alamat, telepon, logo, background | Tersimpan | ⬜ |
| COMP-C-03 | Tanpa background ID card | `id_card` kosong | Tersimpan — `id_card` opsional | ⬜ |
| COMP-C-04 | Profil kedua | Buat profil saat sudah ada satu | Perilaku terdefinisi — aplikasi memakai `CompanyProfile::first()` | ⬜ |

## READ

| ID | Skenario | Langkah | Expected | Status |
|---|---|---|---|---|
| COMP-R-01 | List | Buka `/admin/company-profile` | 200; profil tampil | ✅ |
| COMP-R-02 | Detail | `/admin/company-profile/1/show` | Semua field tampil, gambar ter-preview | ⬜ |
| COMP-R-03 | Kondisi data demo | Cek nilai `image` & `id_card` | Keduanya **NULL** pada data demo — inilah pemicu DEF-01 | ✅ |

## UPDATE

| ID | Skenario | Data | Expected | Status |
|---|---|---|---|---|
| COMP-U-01 | Ubah identitas | Nama / alamat / telepon | Tersimpan; muncul di dokumen cetak | ⬜ |
| COMP-U-02 | Unggah logo | Isi `image` | Logo muncul di slip gaji PDF | ⬜ |
| COMP-U-03 | Unggah background ID card | Isi `id_card` | Cetak ID card di modul Users mulai berfungsi | ⬜ |
| COMP-U-04 | Ganti logo | Timpa logo lama | Logo baru dipakai; berkas lama tertangani | ⬜ |
| COMP-U-05 | Simpan tanpa unggah ulang | Edit nama saja | **Berhasil** — gambar tidak wajib saat update | ⬜ |
| COMP-U-06 | Hapus logo | Kosongkan `image` yang sudah terisi | ⚠️ Setelah ini cetak slip gaji akan 500 — lihat DEF-01 | ⬜ |

## DELETE

| ID | Skenario | Langkah | Expected | Status |
|---|---|---|---|---|
| COMP-D-01 | Hapus profil | Delete | Terhapus; fitur cetak yang bergantung padanya menangani ketiadaan profil dengan pesan jelas, bukan 500 | ⬜ |

## VALIDASI

| ID | Skenario | Data | Expected | Status |
|---|---|---|---|---|
| COMP-V-01 | Submit kosong | Semua kosong | ✅ 302 kembali ke form | ✅ |
| COMP-V-02 | Create tanpa logo | Isi nama saja | Ditolak — `image` wajib saat create | ⬜ |
| COMP-V-03 | Format logo salah | Unggah `.pdf` / `.gif` / `.svg` | Ditolak — hanya `jpg`, `png`, `webp` | ⬜ |
| COMP-V-04 | Format ID card salah | Unggah `.bmp` | Ditolak | ⬜ |
| COMP-V-05 | Berkas rusak | Unggah `.jpg` yang isinya bukan gambar | Ditolak dengan pesan, bukan 500 saat dipakai cetak | ⬜ |

## AKSES

| ID | Role | Expected | Status |
|---|---|---|---|
| COMP-A-01 | SA / HR | Punya `company_profile.view` + `.edit` — akses penuh | ✅ 200 |
| COMP-A-02 | MGR | ⚠️ **Tidak punya** permission apa pun untuk modul ini, tetapi form create terbuka (DEF-03) | ⚠️ |
| COMP-A-03 | EMP | Dialihkan ke `/my` | 🌐 |

## DAMPAK LINTAS MODUL

| ID | Skenario | Langkah | Expected | Status |
|---|---|---|---|---|
| COMP-X-01 | **Cetak slip tanpa logo** | Kosongkan `image` → cetak Rekap Gaji | ⚠️ **GAGAL — 500**. Seharusnya slip tetap terbit tanpa logo. Lihat DEF-01 | ✅ ⚠️ |
| COMP-X-02 | Cetak slip dengan logo | Unggah logo → cetak | PDF terbit, logo tampil di kop | ⬜ |
| COMP-X-03 | Cetak ID card tanpa background | Kosongkan `id_card` → cetak dari Users | ✅ Dialihkan ke `/admin/company-profile` — **guard benar**, bukan bug | ✅ |
| COMP-X-04 | Cetak ID card dengan background | Unggah background → cetak | PDF ID card terbit dengan foto & QR karyawan | ⬜ |

> **Catatan DEF-01.** Guard di
> [SalaryRecapCrudController.php:279-280](../../app/Http/Controllers/Admin/SalaryRecapCrudController.php#L279-L280)
> mengevaluasi `strlen($company->image)` **sesudah** path diberi prefix
> `Storage::path("public/…")`. Ketika `image` NULL hasilnya adalah path
> direktori `storage/app/public` yang panjangnya bukan nol, sehingga guard lolos
> dan dompdf memanggil `getimagesize()` pada sebuah direktori. Bandingkan dengan
> cetak ID card yang guard-nya benar (COMP-X-03) — pola yang sama seharusnya
> dipakai di sini.
