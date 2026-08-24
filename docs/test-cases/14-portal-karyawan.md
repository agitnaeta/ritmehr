# Modul 14 — Portal Karyawan (`/my`)

| | |
|---|---|
| **URL** | `/my/*` |
| **Controller** | [PortalController](../../app/Http/Controllers/Portal/PortalController.php) |
| **View** | `resources/views/portal/` — Blade + Bootstrap 5 via CDN, tanpa build step |
| **Auth** | Berbagi login dan guard Backpack — **tidak ada sistem auth kedua** |

Dua aturan yang menjadi tulang punggung keamanan modul ini:

1. Setiap query di-scope ke user terautentikasi.
2. **Tidak ada satu route pun yang menerima id user dari request.**

Uji keduanya di setiap halaman, bukan hanya sekali.

---

## 14.1 Akses & autentikasi

| ID | Skenario | Langkah | Expected | Status |
|---|---|---|---|---|
| MY-A-01 | Login employee | `ahmad@demo.test` | Mendarat di `/my`, bukan panel admin | 🌐 |
| MY-A-02 | Employee paksa buka admin | Ketik `/admin/user` | Browser mendarat di `/my` — panel admin tidak pernah tampil | 🌐 |
| MY-A-03 | Admin buka portal | Login `siti@` → `/my` | 200 — admin juga punya portal pribadi | ✅ |
| MY-A-04 | Tanpa login | Buka `/my` di incognito | Dialihkan ke halaman login | ⬜ |
| MY-A-05 | Sesi berakhir | Tunggu sesi kedaluwarsa → refresh | Dialihkan ke login, bukan 500 | ⬜ |
| MY-A-06 | Logout | Klik logout | Sesi berakhir; tombol Back tidak memulihkan | ⬜ |

## 14.2 Dashboard — `/my`

| ID | Skenario | Langkah | Expected | Status |
|---|---|---|---|---|
| MY-R-01 | Halaman termuat | Buka `/my` | 200; ringkasan pribadi | ✅ |
| MY-R-02 | Data milik sendiri | Bandingkan dengan data Ahmad di admin | Angka cocok dengan miliknya, bukan agregat perusahaan | ⬜ |
| MY-R-03 | Navigasi | Amati menu portal | 7 tautan: dashboard, kehadiran, slip gaji, cuti, kasbon, profil, notifikasi | ✅ |

## 14.3 Riwayat Kehadiran — `/my/attendance`

| ID | Skenario | Langkah | Expected | Status |
|---|---|---|---|---|
| MY-R-04 | Halaman termuat | Buka `/my/attendance` | 200 | ✅ |
| MY-R-05 | **Hanya presensi sendiri** | Bandingkan jumlah baris vs 110 total | Hanya milik user login | ⬜ |
| MY-R-06 | Kolom telat & lembur | Amati tabel | Menit telat dan lembur tampil | ⬜ |
| MY-R-07 | Status geofence | Amati kolom | Di dalam / di luar radius tampil | ⬜ |
| MY-R-08 | Filter periode | Ganti bulan | Data menyesuaikan | ⬜ |

## 14.4 Slip Gaji — `/my/salary`

| ID | Skenario | Langkah | Expected | Status |
|---|---|---|---|---|
| MY-R-09 | Daftar slip | Buka `/my/salary` | 200; hanya rekap sendiri | ✅ |
| MY-R-10 | Detail slip sendiri | `/my/salary/4` (Ahmad = user 4) | **200** | 🌐 |
| MY-R-11 | **IDOR — slip orang lain** | `/my/salary/1`, `/2`, `/3`, `/5` | Semua **404**. Diuji di sesi browser sungguhan: `1:404 2:404 3:404 4:200 5:404` | 🌐 |
| MY-R-12 | Id tidak ada | `/my/salary/9999` | 404, bukan 500 | ⬜ |
| MY-R-13 | Rincian slip | Buka detail | Gaji pokok, lembur, potongan, kasbon, pajak, diterima | ⬜ |
| MY-R-14 | Konsistensi | Bandingkan dengan `/admin/salary-recap/4/show` | Angka identik | ⬜ |

## 14.5 Cuti — `/my/leave`

**Field form:** `leave_type_id`, `start_date`, `end_date`, `reason`, `attachment`

| ID | Skenario | Langkah | Expected | Status |
|---|---|---|---|---|
| MY-R-15 | Daftar cuti | `/my/leave` | 200; hanya pengajuan sendiri | ✅ |
| MY-R-16 | Saldo tampil | Amati halaman | Kuota, terpakai, sisa tampil | ⬜ |
| MY-C-01 | Form pengajuan | `/my/leave/create` | 200; 5 field | ✅ |
| MY-C-02 | **Tanpa field user_id** | Query DOM `[name="user_id"]` | **Nol hasil** — id user tidak pernah diterima dari request | 🌐 |
| MY-C-03 | Ajukan cuti | Isi form → Submit | Tersimpan pending; approval terbentuk; saldo dicek | ⬜ |
| MY-C-04 | Injeksi user_id | Kirim `POST /my/leave` dengan `user_id` milik orang lain | Diabaikan — cuti tetap atas nama user login | ⬜ |
| MY-C-05 | Melebihi kuota | Ajukan lebih dari sisa | Ditolak dengan pesan | ⬜ |
| MY-C-06 | Tumpang tindih | Rentang beririsan | Ditolak | ⬜ |
| MY-C-07 | Lampiran wajib | Jenis `requires_attachment=1` tanpa berkas | Ditolak | ⬜ |
| MY-U-01 | Batalkan cuti sendiri | `POST /my/leave/{id}/cancel` | Berhasil; kuota kembali | ⬜ |
| MY-U-02 | **Batalkan cuti orang lain** | Kirim id milik karyawan lain | **Ditolak** | ⬜ |

## 14.6 Kasbon — `/my/loan`

| ID | Skenario | Langkah | Expected | Status |
|---|---|---|---|---|
| MY-R-17 | Halaman termuat | `/my/loan` | 200; hanya kasbon sendiri | ✅ |
| MY-R-18 | Sisa kasbon | Amati angka | Cocok dengan rekap admin untuk user itu | ⬜ |
| MY-R-19 | Riwayat cicilan | Amati daftar | Pembayaran tampil berurutan | ⬜ |
| MY-R-20 | Kasbon orang lain | Coba akses id kasbon milik orang lain | Ditolak | ⬜ |

## 14.7 Profil — `/my/profile`

**Field:** `phone`, `address`, `image`, `email`, `current_password`,
`password`, `password_confirmation`

| ID | Skenario | Langkah | Expected | Status |
|---|---|---|---|---|
| MY-R-21 | Halaman termuat | `/my/profile` | 200 | ✅ |
| MY-U-03 | Ubah kontak | Ubah `phone` / `address` → Simpan | Tersimpan | ⬜ |
| MY-U-04 | Unggah foto | Isi `image` | Foto berubah | ⬜ |
| MY-U-05 | **Field HR tidak ada** | Query DOM `department_id`, `employment_status`, `position_id`, `salary`, `manager_id` | **Nol hasil** — hanya HR yang boleh mengubah | 🌐 |
| MY-U-06 | Injeksi field HR | Kirim `POST /my/profile` dengan `department_id` | Diabaikan, departemen tidak berubah | ⬜ |
| MY-U-07 | Ganti password | Isi password lama + baru | Berhasil; login baru berlaku | ⬜ |
| MY-U-08 | Password lama salah | `current_password` keliru | **Ditolak** | ⬜ |
| MY-U-09 | Konfirmasi tidak cocok | `password` ≠ `password_confirmation` | Ditolak | ⬜ |
| MY-U-10 | Ubah email | Ganti `email` | Perilaku terdefinisi; bila boleh, unique tetap ditegakkan | ⬜ |

## 14.8 Notifikasi — `/my/notifications`

| ID | Skenario | Langkah | Expected | Status |
|---|---|---|---|---|
| MY-R-22 | Halaman termuat | `/my/notifications` | 200 | ✅ |
| MY-R-23 | Hanya milik sendiri | Bandingkan dengan notifikasi user lain | Hanya milik user login | ⬜ |
| MY-U-11 | Tandai satu dibaca | `POST /my/notifications/{id}/read` | Berubah jadi terbaca | ⬜ |
| MY-U-12 | Tandai semua dibaca | `POST /my/notifications/mark-all-read` | Semua terbaca | ⬜ |
| MY-U-13 | Tandai notifikasi orang lain | Kirim id milik orang lain | **Ditolak** | ⬜ |

## 14.9 Uji IDOR menyeluruh

Jalankan pola ini untuk **setiap** route portal yang menerima `{id}`, memakai
dua akun employee (`ahmad@` = user 4, `dewi@` = user 5).

| ID | Route | Sebagai Ahmad | Expected | Status |
|---|---|---|---|---|
| MY-S-01 | `/my/salary/{id}` | id milik Dewi | 404 | 🌐 |
| MY-S-02 | `/my/leave/{id}/cancel` | id milik Dewi | Ditolak | ⬜ |
| MY-S-03 | `/my/notifications/{id}/read` | id milik Dewi | Ditolak | ⬜ |
| MY-S-04 | Unduh dokumen | id dokumen Dewi | Ditolak | ⬜ |
| MY-S-05 | Semua POST portal | Sisipkan `user_id` milik Dewi di body | Diabaikan seluruhnya | ⬜ |
