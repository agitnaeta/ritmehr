# UI Test Cases — Aplikasi Absensi / HRIS

Test case manual dari sisi UI, disusun **per menu** mengikuti urutan sidebar di
[menu_items.blade.php](../resources/views/vendor/backpack/ui/inc/menu_items.blade.php).

> Untuk test case **CRUD operasional per modul** (Create/Read/Update/Delete,
> validasi per field, hak akses, operasi khusus) — 725 test case dalam 15
> berkas — lihat [test-cases/](test-cases/README.md).

Semua nama field di dokumen ini diambil dari form yang **benar-benar dirender**
aplikasi (bukan dari pembacaan source), dan matriks role diambil dari hasil
pengujian HTTP terhadap aplikasi yang berjalan.

---

## 1. Persiapan

```bash
docker compose up -d                            # MySQL host port 3307
composer install
php artisan migrate
php artisan db:seed --class=HrisSeeder          # data referensi, idempoten
php artisan db:seed --class=DemoDataSeeder      # 5 karyawan + 1 bulan penuh
php artisan serve                               # http://127.0.0.1:8000
```

### Akun uji

| Nama | Email (login) | Password | Role |
|---|---|---|---|
| Siti Rahayu | `siti@demo.test` | `password` | super_admin |
| Rina Kartika | `rina@demo.test` | `password` | hr_admin |
| Budi Santoso | `budi@demo.test` | `password` | manager |
| Ahmad Fauzi | `ahmad@demo.test` | `password` | employee |
| Dewi Lestari | `dewi@demo.test` | `password` | employee |

Login admin di `/admin/login`. Employee otomatis dialihkan ke `/my`.

### Legenda

| Kode | Arti |
|---|---|
| **SA** | super_admin |
| **HR** | hr_admin |
| **MGR** | manager |
| **EMP** | employee |
| ✅ | Sudah diverifikasi terhadap aplikasi berjalan |
| 🌐 | Diverifikasi di **browser sungguhan** (Chromium/Playwright) |
| ⚠️ | Ada defect diketahui — lihat [bagian 20](#20-defect-diketahui) |
| 🖱️ | Perlu interaksi manual, belum terotomasi |

> **Catatan penting untuk tester:** baris tabel Backpack dimuat lewat AJAX
> (`POST /admin/<entity>/search`) ke dalam `#crudTable`. Membuka URL list saja
> hanya menghasilkan kerangka tabel kosong. Verifikasi isi data harus lewat
> browser, bukan `curl`.

### Harness browser otomatis

Sebagian test case sudah diotomasi di
[tests/browser/ui-test.mjs](../tests/browser/ui-test.mjs):

```bash
npm i -D playwright && npx playwright install chromium
php artisan serve                       # aplikasi harus berjalan
node tests/browser/ui-test.mjs
```

Hasil terakhir: **19 PASS / 2 FAIL** — kedua kegagalan adalah **DEF-01** dan
**DEF-04** yang memang belum diperbaiki.

---

## 2. Matriks akses per role (hasil uji)

Diuji atas 30 route untuk keempat role. ✅ = terverifikasi.

| Area | SA | HR | MGR | EMP |
|---|---|---|---|---|
| `/admin/*` umum (user, presence, gaji, cuti, dokumen, pajak, laporan) | 200 | 200 | ⚠️ 200 | 302→`/my` |
| `/admin/role`, `/admin/permission`, `/admin/approval-flow`, `/admin/approval-flow-step` | 200 | 403 | 403 | 302→`/my` |
| `/my/*` portal karyawan | 200 | 200 | 200 | 200 |

Hanya 4 controller yang memasang guard permission
([RoleCrudController](../app/Http/Controllers/Admin/RoleCrudController.php#L23),
[PermissionCrudController](../app/Http/Controllers/Admin/PermissionCrudController.php#L20),
[ApprovalFlowCrudController](../app/Http/Controllers/Admin/ApprovalFlowCrudController.php#L28),
[ApprovalFlowStepCrudController](../app/Http/Controllers/Admin/ApprovalFlowStepCrudController.php#L29)).
Sisanya terbuka untuk MGR — lihat **DEF-03**.

---

## 3. Login & Autentikasi

| ID | Skenario | Langkah | Expected |
|---|---|---|---|
| TC-AUTH-01 | Login super_admin | `/admin/login` → `siti@demo.test` / `password` | ✅ Masuk `/admin/dashboard`, sidebar tampil penuh termasuk dropdown **Pengaturan** |
| TC-AUTH-02 | Login hr_admin | `rina@demo.test` | ✅ Masuk dashboard, dropdown **Pengaturan** **tidak** muncul |
| TC-AUTH-03 | Login manager | `budi@demo.test` | ✅ Masuk dashboard, **Pengaturan** tidak muncul |
| TC-AUTH-04 | Login employee | `ahmad@demo.test` | 🌐 Dialihkan ke `/my`, bukan panel admin |
| TC-AUTH-05 | Password salah | Email benar, password `salah` | Kembali ke form dengan pesan error, tidak ada sesi |
| TC-AUTH-06 | Akses admin tanpa login | Buka `/admin/user` di incognito | Dialihkan ke `/admin/login` |
| TC-AUTH-07 | Akses portal tanpa login | Buka `/my` di incognito | Dialihkan ke halaman login |
| TC-AUTH-08 | Employee paksa buka admin | Login `ahmad@`, ketik `/admin/user` di address bar | 🌐 Browser mendarat di `/my` — panel admin tidak pernah tampil |
| TC-AUTH-09 | Logout | Klik menu user → Logout | Sesi berakhir, kembali ke login; tombol Back tidak memulihkan sesi |
| TC-AUTH-10 | Ganti password admin | `/admin/change-password` | Password lama diminta, password baru berlaku pada login berikutnya |

---

## 4. Dashboard

`/admin/dashboard` — override dashboard bawaan Backpack.

| ID | Skenario | Langkah | Expected |
|---|---|---|---|
| TC-DASH-01 | Halaman termuat | Login SA → dashboard | 🌐 200, **nol JS error** |
| TC-DASH-02 | Kehadiran hari ini | Amati kartu kehadiran | 🌐 4 kartu terpisah: Hadir 0/5 · Belum Absen 5 · Terlambat 0 · Di Luar Radius 0 |
| TC-DASH-03 | Cuti bukan absen | Bandingkan jumlah cuti vs tidak hadir | Karyawan cuti **tidak** ikut terhitung sebagai absen |
| TC-DASH-04 | Total payroll bulan ini | Amati kartu payroll | 🌐 "Total Gaji Rp 0, 0 rekap" untuk bulan berjalan — benar, data demo ada di bulan **sebelumnya** |
| TC-DASH-05 | Grafik tren 12 bulan | Amati Chart.js | 🌐 Canvas 593×178 ter-render; dua seri (Tingkat Kehadiran % + kali telat), sumbu Sep 25–Agt 26 berlabel |
| TC-DASH-06 | Top keterlambatan | Amati daftar | 🌐 "Tidak ada keterlambatan bulan ini." — empty state tampil rapi |
| TC-DASH-07 | Headcount | Amati kartu | 🌐 Total Aktif 5 · Teknologi 2 · HRD 2 · Operasional 1 · Head Office 0 — hanya `active`+`probation` |
| TC-DASH-08 | Cache 5 menit | Ubah data presensi → refresh | Angka hari ini boleh tertinggal ≤5 menit (perilaku benar, bukan bug) |
| TC-DASH-09 | Dashboard sebagai MGR | Login `budi@` | 🌐 200 — cek apakah angka sudah ter-scope tim (lihat DEF-03) |


---

## 5. Users

`/admin/user` — CRUD karyawan.

Field form: `name`, `email`, `password`, `phone`, `address`, `image`,
`employee_id`, `join_date`, `employment_status`, `department_id`,
`position_id`, `branch_id`, `manager_id`, `schedule_id`.

| ID | Skenario | Langkah | Expected |
|---|---|---|---|
| TC-USER-01 | List termuat | Buka `/admin/user` | 🌐 Tabel AJAX terisi **5 baris** — "Menampilkan 1 hingga 5 dari 5 masukan." |
| TC-USER-02 | Tambah karyawan | Create → isi seluruh field wajib → Save | Redirect ke list, karyawan baru muncul |
| TC-USER-03 | Email duplikat | Create dengan `ahmad@demo.test` | Validasi menolak, form tidak tersimpan |
| TC-USER-04 | Edit karyawan | Edit user 4 → ubah `phone` → Save | Perubahan tersimpan dan tampil di list |
| TC-USER-05 | Password opsional saat edit | Edit tanpa mengisi password | Password lama tetap berlaku (uji dengan login ulang) |
| TC-USER-06 | Assign atasan | Set `manager_id` = Budi | Muncul di struktur organisasi & rantai approval |
| TC-USER-07 | Manager = diri sendiri | Set `manager_id` = user itu sendiri | Harus ditolak (loop rantai approval) |
| TC-USER-08 | Status kepegawaian | Set `resigned` | Hilang dari headcount & payroll, riwayat tetap ada |
| TC-USER-09 | Upload foto | Upload `image` | Preview tampil, file tersimpan |
| TC-USER-10 | Cetak ID card | Tombol Print pada baris | ✅ Bila `id_card` di Profile Perusahaan kosong → dialihkan ke `/admin/company-profile` (guard benar). Bila terisi → PDF ID card |
| TC-USER-11 | Cetak semua ID card | `/admin/user/print-all` | ✅ Sama seperti TC-USER-10, berlaku massal |
| TC-USER-12 | Export Excel | `/admin/user/export` | ✅ 200, unduh `.xlsx` (~6.9 KB, 5 baris) |
| TC-USER-13 | Hapus karyawan | Delete pada baris | Konfirmasi muncul; data bergantung (presensi/gaji) tidak yatim |
| TC-USER-14 | Filter | 🖱️ Gunakan filter bar (`HasSimpleFilters`) | Hasil menyempit, parameter GET terbaca di URL |
| TC-USER-15 | Scoping MGR | Login `budi@` → buka list | ⚠️ **GAGAL** — 🌐 manager melihat **5 dari 5** user. Ekspektasi: hanya tim — lihat DEF-04 |

---

## 6. Absen

### 6.1 Scan — `/admin/presence/scan` dan `/scan` (publik)

| ID | Skenario | Langkah | Expected |
|---|---|---|---|
| TC-SCAN-01 | Halaman scan admin | Buka `/admin/presence/scan` | ✅ 200 |
| TC-SCAN-02 | Halaman scan publik | Buka `/scan` tanpa login | ✅ 200 — memang publik |
| TC-SCAN-03 | Root redirect | Buka `/` | ✅ 302 → `/scan` |
| TC-SCAN-04 | Elemen scanner | Buka `/scan` di browser | 🌐 `#preview`, `#audioPlayer`, `#audioPlayerFailed` ketiganya ada; kamera ditolak (headless) **tidak membuat halaman crash** |
| TC-SCAN-05 | Scan QR valid | 🖱️ Scan QR karyawan | Absen tercatat, audio sukses (`#audioPlayer`) berbunyi, jam tampil di `#time` |
| TC-SCAN-06 | Scan QR tidak dikenal | 🖱️ Scan QR asal | Ditolak, audio gagal (`#audioPlayerFailed`) berbunyi |
| TC-SCAN-07 | Scan kedua = check-out | 🖱️ Scan ulang karyawan yang sama | Terisi kolom `out`, bukan baris baru |
| TC-SCAN-08 | Geofence di dalam radius | 🖱️ Mock GPS di dalam radius cabang | `outside = 0`, tampil "Di Dalam Radius" |
| TC-SCAN-09 | Geofence di luar radius | 🖱️ Mock GPS jauh dari cabang | `outside = 1`, ditandai "Di Luar Radius" |
| TC-SCAN-10 | Tanpa titik referensi | Hapus koordinat cabang & config global | Scan dianggap **on-site**, bukan menandai semua orang di luar |
| TC-SCAN-11 | Radius per cabang | Set radius Cabang A 50 m, Cabang B 500 m | Tiap karyawan dievaluasi dengan radius cabangnya sendiri |

### 6.2 Jadwal — `/admin/schedule`

Field: `name`, `in`, `out`, `over_in`, `over_out`, `day_off`.

| ID | Skenario | Langkah | Expected |
|---|---|---|---|
| TC-SCH-01 | List jadwal | Buka `/admin/schedule` | ✅ 200 |
| TC-SCH-02 | Tambah jadwal | Create "Shift Malam" `in=22:00` `out=06:00` | Tersimpan |
| TC-SCH-03 | Jam keluar < jam masuk | `in=17:00` `out=08:00` | Perilaku shift lintas hari terdefinisi (tidak menghasilkan durasi negatif) |
| TC-SCH-04 | Hari libur | Set `day_off` | Hari tsb tidak dihitung sebagai absen di rekap gaji |
| TC-SCH-05 | Edit & hapus | Edit lalu hapus jadwal | Karyawan yang memakai jadwal tsb tidak error |

### 6.3 Setting Jadwal — `/admin/schedule/view-update`

| ID | Skenario | Langkah | Expected |
|---|---|---|---|
| TC-SCHM-01 | Halaman termuat | Buka halaman | ✅ 200 |
| TC-SCHM-02 | Mass update | 🖱️ Pilih beberapa karyawan → set jadwal → Simpan | `POST /admin/schedule/mass-update`, semua terpilih berubah |
| TC-SCHM-03 | Simpan tanpa pilihan | Submit tanpa memilih siapa pun | Tidak error, tidak ada perubahan |

### 6.4 Kehadiran — `/admin/presence`

Field: `user_id`, `in`, `out`, `is_late`, `late_minute`, `is_overtime`,
`extra_time`, `outside`, `branch_id`.

| ID | Skenario | Langkah | Expected |
|---|---|---|---|
| TC-PRES-01 | List termuat | Buka `/admin/presence` | 🌐 "Menampilkan 1 hingga 10 dari **110** masukan." — paginasi 10/halaman |
| TC-PRES-02 | Status geofence | Amati kolom radius | 🌐 **Nol** teks "Di Luar Radius" di halaman; seluruh 110 baris `outside=0`. Regresi lama (semua di luar radius) tidak muncul lagi |
| TC-PRES-03 | Input manual | Create presensi baru | Tersimpan; observer menghitung geofence saat **create**, bukan hanya update |
| TC-PRES-04 | Edit jam masuk | Ubah `in` ke jam terlambat | `is_late` dan `late_minute` terhitung ulang otomatis |
| TC-PRES-05 | Hitung lembur | Set `out` melewati `over_in` | `is_overtime` / `extra_time` terisi |
| TC-PRES-06 | Karyawan tanpa jadwal | Buat presensi untuk user tanpa `schedule_id` | **Tidak boleh error** — `calculateExtraTime()` pernah crash di sini |
| TC-PRES-07 | Branch melekat | Pindahkan karyawan ke cabang lain → hitung ulang riwayat | Presensi lama tetap memakai cabang saat itu |
| TC-PRES-08 | Filter tanggal/karyawan | 🖱️ Gunakan filter bar | Hasil menyempit sesuai parameter GET |

### 6.5 Libur Nasional — `/admin/national-holiday`

Field: `date`, `info`.

| ID | Skenario | Langkah | Expected |
|---|---|---|---|
| TC-HOL-01 | List & tambah | Tambah tanggal libur | ✅ Form 200; data tersimpan |
| TC-HOL-02 | Tidak dihitung absen | Jalankan rekap gaji pada bulan berisi libur | Hari libur tidak jadi potongan |
| TC-HOL-03 | Tidak dihitung cuti | Ajukan cuti melintasi tanggal libur | Hari libur tidak mengurangi kuota |
| TC-HOL-04 | Tanggal duplikat | Tambah tanggal yang sama dua kali | Ditolak atau tidak menggandakan efek |

---

## 7. Kasbon

### 7.1 Rekap — `/admin/loan/recap`

| ID | Skenario | Langkah | Expected |
|---|---|---|---|
| TC-LOAN-01 | Halaman rekap | Buka `/admin/loan/recap` | ✅ 200 |
| TC-LOAN-02 | Unduh rekap | `/admin/loan/download` | ✅ 200, `.xlsx` (~6.4 KB) |
| TC-LOAN-03 | Saldo per karyawan | Bandingkan sisa vs kasbon − pembayaran | Angka konsisten |

### 7.2 Kasbon — `/admin/loan`

Field: `user_id`, `amount`, `date`.

| ID | Skenario | Langkah | Expected |
|---|---|---|---|
| TC-LOAN-04 | Tambah kasbon | Create untuk Ahmad, `amount=1000000` | Tersimpan, muncul di rekap |
| TC-LOAN-05 | Nominal negatif / nol | `amount=-500` | Ditolak validasi |
| TC-LOAN-06 | Detail kasbon | `/admin/loan/1/detail` | ✅ 200, menampilkan riwayat cicilan |
| TC-LOAN-07 | Cetak detail PDF | `/admin/loan/1/print-detail` | ✅ 200, `application/pdf` |
| TC-LOAN-08 | Unduh detail Excel | `/admin/loan/1/download-detail` | ✅ 200, `.xlsx` |
| TC-LOAN-09 | Potong dari gaji | Jalankan rekap gaji | Cicilan terpotong di slip |

### 7.3 Pembayaran Kasbon — `/admin/loan-payment`

Field: `user_id`, `amount`, `date`.

| ID | Skenario | Langkah | Expected |
|---|---|---|---|
| TC-LOAN-10 | Catat pembayaran | Create pembayaran | Sisa kasbon berkurang |
| TC-LOAN-11 | Bayar melebihi sisa | `amount` > sisa | Ditolak atau sisa tidak jadi negatif |
| TC-LOAN-12 | Kode konfirmasi | Amati kolom `confirm_code` | Terisi dan unik |
| TC-LOAN-13 | Hapus pembayaran | Delete | Sisa kasbon kembali seperti semula |

---

## 8. Gajian

### 8.1 Gaji — `/admin/salary`

Field: `user_id`, `amount`, `overtime_amount`, `overtime_type`, `extra_time`,
`extra_time_rule`, `fine`, `fine_type`, `fine_per_minute`,
`unpaid_leave_deduction`.

| ID | Skenario | Langkah | Expected |
|---|---|---|---|
| TC-SAL-01 | List & create | Buka list, tambah komponen gaji | ✅ Form 200; tersimpan |
| TC-SAL-02 | Denda per menit | Set `fine_type` per menit, `fine_per_minute=1000` | Potongan = menit terlambat × 1000 |
| TC-SAL-03 | Denda flat | Set `fine_type` flat | Potongan tetap, tidak tergantung menit |
| TC-SAL-04 | Aturan lembur | Set `overtime_type` & `extra_time_rule` | Perhitungan lembur mengikuti aturan |
| TC-SAL-05 | Gaji ganda | Buat dua baris gaji untuk user sama | Ditolak, atau perilaku terdefinisi jelas |

### 8.2 Rekap Gaji — `/admin/salary-recap`

| ID | Skenario | Langkah | Expected |
|---|---|---|---|
| TC-REC-01 | List termuat | Buka `/admin/salary-recap` | 🌐 Tabel AJAX terisi **5 dari 5** baris |
| TC-REC-02 | Create dinonaktifkan | Coba `/admin/salary-recap/create` | ✅ **403** — rekap hanya dibuat oleh command, bukan manual |
| TC-REC-03 | Lihat detail | `/admin/salary-recap/1/show` | ✅ 200, rincian komponen lengkap |
| TC-REC-04 | Bayar tunai | Klik tombol **Bayar Cash** | ✅ `?method=cash` → 302 ke list, `paid=1`, `method=cash`, alert sukses |
| TC-REC-05 | Bayar transfer | Klik tombol **Bayar Transfer** | `?method=transfer` → sama, `method=transfer` |
| TC-REC-06 | Set-payment tanpa method | Ketik `/admin/salary-recap/1/set-payment` langsung di address bar | ⚠️ **500** saat ini — lihat **DEF-02** |
| TC-REC-07 | Hitung ulang gaji | Tombol **Recalculate** | ✅ 302 kembali ke list, angka diperbarui |
| TC-REC-08 | Export Excel | `/admin/salary-recap/export` | ✅ 200, `.xlsx` (~6.8 KB) |
| TC-REC-09 | Cetak slip PDF | Klik tombol **Print** pada baris | ⚠️ **GAGAL** — 🌐 HTTP **500** di browser, `getimagesize()` gagal. Lihat **DEF-01**. |
| TC-REC-10 | Cuti berbayar tidak dipotong | Lihat rekap Ahmad (3 hari cuti berbayar disetujui) | Tidak dihitung absen, **tidak** dipotong |
| TC-REC-11 | Absen tanpa keterangan dipotong | Lihat rekap Dewi (2 hari absen tanpa pengajuan) | Dihitung absen **dan** dipotong |
| TC-REC-12 | Cuti tidak berbayar | Rekap dengan cuti unpaid disetujui | Tidak dihitung absen, tetapi **dipotong** |
| TC-REC-13 | Cuti masih pending | Rekap dengan cuti belum disetujui | Tetap dihitung absen — pending tidak memaafkan apa pun |

> Skenario TC-REC-10 s/d TC-REC-13 adalah inti perbaikan payroll M2. Data demo
> sengaja menempatkan Ahmad dan Dewi berdampingan: kekurangan kehadiran sama,
> hasil payroll berlawanan.

---

## 9. Profile Perusahaan — `/admin/company-profile`

Field: `name`, `address`, `phone`, `image`, `id_card`.

| ID | Skenario | Langkah | Expected |
|---|---|---|---|
| TC-COMP-01 | Halaman termuat | Buka menu | ✅ 200 |
| TC-COMP-02 | Ubah identitas | Ubah `name` / `address` / `phone` | Tersimpan, muncul di dokumen cetak |
| TC-COMP-03 | Upload logo | Isi `image` | Logo muncul di slip gaji PDF |
| TC-COMP-04 | Cetak tanpa logo | Kosongkan `image` → cetak rekap gaji | ⚠️ Seharusnya slip tetap terbit tanpa logo; saat ini **500** — **DEF-01** |
| TC-COMP-05 | Upload background ID card | Isi `id_card` | Cetak ID card di menu Users berfungsi (TC-USER-10) |
| TC-COMP-06 | Cetak ID card tanpa background | Kosongkan `id_card` → cetak | ✅ Dialihkan ke Profile Perusahaan — guard benar |

---

## 10. Konfigurasi Akuntansi — `/admin/acc`

Field: `code`, `source_id`, `destination_id`.

| ID | Skenario | Langkah | Expected |
|---|---|---|---|
| TC-ACC-01 | List & create | Tambah konfigurasi kode `GAJIAN` | ✅ Form 200; tersimpan |
| TC-ACC-02 | Integrasi nonaktif | `ACC_ACTIVE` tidak diset di `.env` | Pembayaran gaji **tidak** mengirim transaksi eksternal (kondisi default saat ini) |
| TC-ACC-03 | Integrasi aktif | Set `ACC_ACTIVE=true` → bayar rekap gaji | Transaksi WITHDRAWAL tercatat ke ACC |
| TC-ACC-04 | Kode tidak ditemukan | Bayar saat kode `GAJIAN` belum ada | Gagal dengan pesan jelas, pembayaran tidak setengah jalan |

---

## 11. Organisasi

### 11.1 Cabang — `/admin/branch`

Field: `name`, `code`, `address`, `phone`, `lat`, `lng`, `radius_meters`,
`is_active`, `company_profile_id`.

| ID | Skenario | Langkah | Expected |
|---|---|---|---|
| TC-BR-01 | List & create | Tambah cabang | ✅ Form 200; tersimpan |
| TC-BR-02 | Geofence per cabang | Set `lat`/`lng`/`radius_meters` berbeda per cabang | Scan dievaluasi per cabang, bukan radius global 100 m |
| TC-BR-03 | Koordinat kosong | Kosongkan `lat`/`lng` | Jatuh ke cabang user → config global; tidak menandai semua di luar |
| TC-BR-04 | Nonaktifkan cabang | `is_active=0` | Tidak bisa dipilih untuk karyawan baru |
| TC-BR-05 | Kode duplikat | Buat dua cabang kode sama | Ditolak |

### 11.2 Departemen — `/admin/department`

Field: `name`, `code`, `parent_id`, `head_user_id`.

| ID | Skenario | Langkah | Expected |
|---|---|---|---|
| TC-DEP-01 | List & create | Tambah departemen | ✅ Form 200; tersimpan |
| TC-DEP-02 | Sub-departemen | Set `parent_id` | Muncul bersarang di struktur organisasi |
| TC-DEP-03 | Parent = diri sendiri | Set `parent_id` = departemen itu sendiri | **Ditolak** — cycle guard |
| TC-DEP-04 | Parent = keturunan sendiri | Set parent ke anak/cucunya | **Ditolak** — cycle guard |
| TC-DEP-05 | Kepala departemen | Set `head_user_id` | Tampil di struktur organisasi |

### 11.3 Jabatan — `/admin/position`

Field: `name`, `level`, `department_id`.

| ID | Skenario | Langkah | Expected |
|---|---|---|---|
| TC-POS-01 | List & create | Tambah jabatan level 3 | ✅ Form 200; tersimpan |
| TC-POS-02 | Urutan level | Buat beberapa level | Terurut benar di struktur organisasi |

### 11.4 Struktur Organisasi — `/admin/org-chart`

| ID | Skenario | Langkah | Expected |
|---|---|---|---|
| TC-ORG-01 | Halaman termuat | Buka `/admin/org-chart` | ✅ 200 |
| TC-ORG-02 | Hierarki benar | 🖱️ Amati bagan | Head Office → Teknologi / HRD / Operasional |
| TC-ORG-03 | Data bersiklus | Injeksi loop parent di DB → buka bagan | **Tidak hang** — `descendants()` tetap berhenti |
| TC-ORG-04 | Karyawan resigned | Set satu karyawan `resigned` | Tidak muncul di bagan aktif |

---

## 12. Cuti & Izin

### 12.1 Pengajuan Cuti — `/admin/leave-request`

| ID | Skenario | Langkah | Expected |
|---|---|---|---|
| TC-LV-01 | List termuat | Buka `/admin/leave-request` | 🌐 Tabel AJAX terisi **2 dari 2** pengajuan |
| TC-LV-02 | CRUD standar dinonaktifkan | Coba `/admin/leave-request/create` | ✅ **404** — pengajuan lewat form khusus |
| TC-LV-03 | Scoping non-`leave.view_all` | Login role tanpa `leave.view_all` | Hanya melihat pengajuan sendiri |
| TC-LV-04 | Batalkan pengajuan | Tombol Cancel | Status jadi cancelled, kuota kembali |

### 12.2 Ajukan Cuti — `/admin/leave-request/create-form`

Field: `user_id`, `leave_type_id`, `start_date`, `end_date`, `reason`, `attachment`.

| ID | Skenario | Langkah | Expected |
|---|---|---|---|
| TC-LV-05 | Form termuat | Buka halaman | ✅ 200 |
| TC-LV-06 | Ajukan cuti valid | Isi semua field → Submit | Tersimpan status pending, approval terbentuk |
| TC-LV-07 | Akhir < awal | `end_date` sebelum `start_date` | Ditolak validasi |
| TC-LV-08 | Tanggal tumpang tindih | Ajukan pada rentang yang sudah ada | **Ditolak** — overlap tidak boleh |
| TC-LV-09 | Melebihi kuota | Ajukan lebih dari sisa saldo | Ditolak dengan pesan kuota |
| TC-LV-10 | Melebihi max berturut-turut | Lewati `max_consecutive_days` jenis cuti | Ditolak |
| TC-LV-11 | Lampiran wajib | Jenis cuti `requires_attachment=1`, submit tanpa file | Ditolak |
| TC-LV-12 | Akhir pekan dilewati | Ajukan Jumat–Senin | Total hari = 2, Sabtu–Minggu tidak dihitung |
| TC-LV-13 | Libur nasional dilewati | Ajukan melintasi libur nasional | Hari libur tidak mengurangi kuota |

### 12.3 Kalender Cuti — `/admin/leave-calendar`

| ID | Skenario | Langkah | Expected |
|---|---|---|---|
| TC-LV-14 | Kalender termuat | Buka halaman | ✅ 200 |
| TC-LV-15 | Warna per jenis | 🖱️ Amati kalender | Warna sesuai field `color` jenis cuti |
| TC-LV-16 | Navigasi bulan | 🖱️ Klik bulan berikut/sebelum | Data ikut berpindah |

### 12.4 Saldo Cuti — `/admin/leave-balance`

Field: `user_id`, `leave_type_id`, `year`, `quota`, `carry_over`, `used`.

| ID | Skenario | Langkah | Expected |
|---|---|---|---|
| TC-LV-17 | List & create | Buka list, tambah saldo | ✅ Form 200; tersimpan |
| TC-LV-18 | Kolom `remaining` | Set `quota=12`, `carry_over=6`, `used=3` | `remaining = 15` — carry-over **tidak** hilang |
| TC-LV-19 | Generate saldo | Tombol Generate (`POST .../generate`) | Saldo terbentuk untuk semua karyawan, idempoten |
| TC-LV-20 | Carry over | Tombol Carry Over | Sisa tahun lalu terbawa, dibatasi `max-carry` |
| TC-LV-21 | Karyawan masuk tengah tahun | Generate untuk yang `join_date` bulan Juli | Kuota **prorata**, bukan penuh |
| TC-LV-22 | Generate dua kali | Jalankan Generate berulang | Tidak menggandakan saldo |

### 12.5 Jenis Cuti — `/admin/leave-type`

Field: `name`, `code`, `color`, `default_quota`, `is_paid`, `is_active`,
`max_consecutive_days`, `requires_attachment`.

| ID | Skenario | Langkah | Expected |
|---|---|---|---|
| TC-LV-23 | List & create | Tambah jenis cuti | ✅ Form 200; tersimpan |
| TC-LV-24 | Berbayar vs tidak | Buat satu `is_paid=1`, satu `is_paid=0` | Berdampak beda di payroll (TC-REC-10 vs TC-REC-12) |
| TC-LV-25 | Nonaktifkan jenis | `is_active=0` | Tidak muncul di form pengajuan baru |

### 12.6 Rekap Cuti — `/admin/leave-report`

| ID | Skenario | Langkah | Expected |
|---|---|---|---|
| TC-LV-26 | Rekap termuat | Buka `/admin/leave-report` | ✅ 200 |
| TC-LV-27 | Angka konsisten | Bandingkan terpakai vs saldo | Cocok dengan Saldo Cuti |

---

## 13. Dokumen

### 13.1 Dokumen Karyawan — `/admin/employee-document`

Field: `user_id`, `document_type_id`, `document_number`, `file`,
`issued_date`, `expiry_date`, `notes`.

| ID | Skenario | Langkah | Expected |
|---|---|---|---|
| TC-DOC-01 | List & form | Buka list dan create | ✅ Keduanya 200 |
| TC-DOC-02 | Upload dokumen | Upload PDF sesuai jenis | Tersimpan di disk **`local` (privat)**, bukan `public` |
| TC-DOC-03 | Ekstensi tidak diizinkan | Upload `.exe` saat hanya `pdf,jpg` diizinkan | Ditolak |
| TC-DOC-04 | Melebihi ukuran maks | Upload file > `max_file_size_mb` | Ditolak |
| TC-DOC-05 | Expiry wajib | Jenis `has_expiry=1`, submit tanpa `expiry_date` | Ditolak |
| TC-DOC-06 | Unduh sebagai HR | Unduh dokumen milik karyawan lain | Berhasil — HR melihat semua |
| TC-DOC-07 | Unduh sebagai pemilik | Unduh dokumen sendiri | Berhasil |
| TC-DOC-08 | Unduh milik orang lain | Karyawan A unduh dokumen karyawan B | **Ditolak** (403/404) |
| TC-DOC-09 | URL ditebak | Akses path file langsung tanpa lewat app | **Tidak dapat diakses** — disk privat |
| TC-DOC-10 | Hapus dokumen | Delete record | File fisik ikut terhapus |

### 13.2 Kelengkapan Dokumen — `/admin/employee-document/completeness`

| ID | Skenario | Langkah | Expected |
|---|---|---|---|
| TC-DOC-11 | Halaman termuat | Buka halaman | ✅ 200 |
| TC-DOC-12 | Dokumen wajib kurang | Karyawan belum unggah jenis `is_required=1` | Ditandai belum lengkap |
| TC-DOC-13 | Setelah dilengkapi | Unggah dokumen yang kurang | Berubah jadi lengkap |

### 13.3 Jenis Dokumen — `/admin/document-type`

Field: `name`, `code`, `allowed_extensions`, `max_file_size_mb`,
`has_expiry`, `is_required`.

| ID | Skenario | Langkah | Expected |
|---|---|---|---|
| TC-DOC-14 | List & create | Tambah jenis dokumen | ✅ Form 200; tersimpan |
| TC-DOC-15 | Aturan berlaku | Ubah `allowed_extensions` → coba unggah | Aturan baru langsung berlaku |
| TC-DOC-16 | Notifikasi kedaluwarsa | Set dokumen expiry < 30 hari → `documents:notify-expiring --days=30` | Notifikasi terkirim ke pemilik |

---

## 14. Pajak & BPJS

> ⚠️ Verifikasi tarif hasil seed terhadap regulasi terbaru sebelum payroll
> sungguhan. `TaxRateSeeder` mengacu PMK 101/2016 dan UU HPP No. 7/2021, dan
> JKK diisi kelas risiko terendah (0,24%).

### 14.1 Profil Pajak Karyawan — `/admin/tax-profile`

Field: `user_id`, `npwp`, `tax_status`, `tax_method`, `bpjs_kesehatan`,
`bpjs_ketenagakerjaan`, `bpjs_tk_jht`, `bpjs_tk_jkk`, `bpjs_tk_jkm`, `bpjs_tk_jp`.

| ID | Skenario | Langkah | Expected |
|---|---|---|---|
| TC-TAX-01 | List & create | Tambah profil pajak | ✅ Form 200; tersimpan |
| TC-TAX-02 | Tanpa NPWP | Kosongkan `npwp` → hitung PPh 21 | Kena surcharge **20%** |
| TC-TAX-03 | Status PTKP | Ubah `tax_status` (TK/0, K/2, dst.) | PTKP ikut berubah, PPh 21 menyesuaikan |
| TC-TAX-04 | Toggle BPJS | Matikan `bpjs_kesehatan` | Iuran Kesehatan tidak dipotong |

### 14.2 Rekap Pajak Tahunan — `/admin/tax-report/annual`

| ID | Skenario | Langkah | Expected |
|---|---|---|---|
| TC-TAX-05 | Halaman termuat | Buka halaman | ✅ 200 |
| TC-TAX-06 | Dasar SPT | Amati angka setahun | Bruto, biaya jabatan, PTKP, PPh 21 lengkap |
| TC-TAX-07 | Biaya jabatan dibatasi | Karyawan bergaji tinggi | Biaya jabatan 5%, **maksimum 6.000.000/tahun** |
| TC-TAX-08 | Hitung ulang | `POST /admin/tax-report/recalculate` | Angka diperbarui memakai tarif tahun bersangkutan |

### 14.3 Rekap BPJS Bulanan — `/admin/tax-report/bpjs`

| ID | Skenario | Langkah | Expected |
|---|---|---|---|
| TC-TAX-09 | Halaman termuat | Buka halaman | ✅ 200 |
| TC-TAX-10 | Batas Kesehatan | Gaji di atas 12.000.000 | Iuran dihitung dari **batas 12.000.000** |
| TC-TAX-11 | Batas JP | Gaji di atas 10.042.300 | Iuran JP memakai batas tersebut |
| TC-TAX-12 | JHT tanpa batas | Gaji tinggi | JHT 2% / 3,7% tanpa plafon |
| TC-TAX-13 | JKK & JKM | Amati kolom | Ditanggung **pemberi kerja** saja |

### 14.4 Tarif PTKP / Lapisan PPh 21 / Tarif BPJS

Field PTKP: `year`, `status`, `amount` · PPh 21: `year`, `lower_bound`,
`upper_bound`, `rate` · BPJS: `year`, `type`, `employee_rate`,
`employer_rate`, `max_salary`.

| ID | Skenario | Langkah | Expected |
|---|---|---|---|
| TC-TAX-14 | List & create ketiganya | Buka masing-masing create | ✅ Ketiganya 200 |
| TC-TAX-15 | Tarif per tahun | Buat tarif tahun berbeda | Perhitungan historis memakai tarif **tahunnya sendiri** |
| TC-TAX-16 | Tahun tidak tersedia | Hitung untuk tahun yang belum ada tarifnya | Memakai tahun terbit **terakhir**, bukan nol |
| TC-TAX-17 | Lapisan tumpang tindih | Buat bracket yang saling tumpang tindih | Ditolak atau perilaku terdefinisi |

---

## 15. Persetujuan — `/admin/approval`

| ID | Skenario | Langkah | Expected |
|---|---|---|---|
| TC-APR-01 | List termuat | Buka `/admin/approval` | 🌐 Tabel AJAX terisi **2 dari 2** approval |
| TC-APR-02 | CRUD dinonaktifkan | Coba `/admin/approval/create` | ✅ **404** |
| TC-APR-03 | Scoping SA/HR | Login `siti@` / `rina@` | ✅ Melihat **2 dari 2** approval |
| TC-APR-04 | Scoping MGR | Login `budi@` | 🌐 Melihat **1 dari 2** — hanya yang jadi tanggung jawabnya. Satu-satunya modul yang scoping timnya sudah benar |
| TC-APR-05 | Halaman detail | `/admin/approval/1/detail` | ✅ 200, riwayat langkah tampil |
| TC-APR-06 | Setujui | Tombol Approve | Status maju ke langkah berikutnya atau selesai |
| TC-APR-07 | Tolak tanpa alasan | Tombol Reject, alasan kosong | **Ditolak** — alasan wajib |
| TC-APR-08 | Tolak dengan alasan | Reject + alasan | Status rejected, alasan tersimpan |
| TC-APR-09 | Bukan approver | Login user yang bukan approver langkah aktif | **Ditolak** |
| TC-APR-10 | Aksi dua kali | Approve pada approval yang sudah selesai | **Ditolak** — bukan status pending |
| TC-APR-11 | Rantai manager → HR | Ajukan cuti Ahmad → Budi approve → Rina approve | `approved_by` = **Rina** (approver terakhir), bukan Budi |
| TC-APR-12 | Alasan penolakan terakhir | Rantai bertingkat lalu ditolak di langkah akhir | `rejection_reason` dari penolak **terakhir** |
| TC-APR-13 | Dua approver bersamaan | 🖱️ Dua tab approve serentak | Hanya satu berhasil — row lock |
| TC-APR-14 | Batalkan | Tombol Cancel | Status cancelled, efek samping dibatalkan |
| TC-APR-15 | Tanpa flow aktif | Nonaktifkan flow modul → ajukan cuti | Error konfigurasi yang jelas, bukan 500 mentah |

---

## 16. Audit Log — `/admin/audit-log`

| ID | Skenario | Langkah | Expected |
|---|---|---|---|
| TC-AUD-01 | List termuat | Buka `/admin/audit-log` | 🌐 "Menampilkan 1 hingga 10 dari **184** masukan." — jejak audit aktif terisi |
| TC-AUD-02 | Read-only | Coba `/admin/audit-log/create` | ✅ **404** — create/update/delete ditolak |
| TC-AUD-03 | Perubahan tercatat | Edit seorang karyawan → buka audit log | Entri baru: aksi, pelaku, IP, user agent |
| TC-AUD-04 | Detail perubahan | Buka detail entri | Nilai lama vs baru tampil |
| TC-AUD-05 | Prune | `php artisan audit:prune --days=90` | Entri lebih tua dari 90 hari terhapus |

---

## 17. Pengaturan (super_admin saja)

| ID | Skenario | Langkah | Expected |
|---|---|---|---|
| TC-SET-01 | Menu tersembunyi | Login `rina@` / `budi@` | 🌐 Dropdown **Pengaturan** tidak ada di DOM sidebar manager (tapi manager tetap melihat 44 link admin lain — DEF-03) |
| TC-SET-02 | Akses paksa Role | Login `budi@` → ketik `/admin/role` | ✅ **403** |
| TC-SET-03 | Akses paksa Permission | Login `rina@` → `/admin/permission` | ✅ **403** |
| TC-SET-04 | Akses paksa Alur Persetujuan | Login `budi@` → `/admin/approval-flow` | ✅ **403** |
| TC-SET-05 | Role — list & create | Login `siti@` → `/admin/role` | 🌐 Dropdown **Pengaturan** tampil untuk super_admin; 200; field `name`, `guard_name`, `permissions` |
| TC-SET-06 | Ubah permission role | Centang/hapus permission → Save | Berlaku setelah user terkait login ulang |
| TC-SET-07 | Permission read-only | `/admin/permission/create` | ✅ **404** — hanya bisa dilihat |
| TC-SET-08 | Alur Persetujuan | Create flow modul `leave` | ✅ Form 200; field `name`, `module`, `is_active`, `steps` |
| TC-SET-09 | Dua flow aktif satu modul | Aktifkan dua flow `leave` | Ditolak — satu flow aktif per modul |
| TC-SET-10 | Flow tanpa langkah | Aktifkan flow tanpa step → ajukan cuti | `RuntimeException` konfigurasi, pesan jelas |
| TC-SET-11 | Step persetujuan | Create step | ✅ Form 200; `approval_flow_id`, `step_order`, `approver_type`, `approver_role_id`, `approver_user_id` |
| TC-SET-12 | Approver = manager pengaju | Set `approver_type` = manager | Atasan langsung pengaju yang jadi approver |
| TC-SET-13 | Urutan langkah | Buat step 1 dan 2 | Dieksekusi berurutan, tidak boleh lompat |

---

## 18. Notifikasi

| ID | Skenario | Langkah | Expected |
|---|---|---|---|
| TC-NOT-01 | Halaman notifikasi | `/admin/notification` | ✅ 200 |
| TC-NOT-02 | Endpoint jumlah belum dibaca | `/admin/notification/unread-count` | ✅ 200, `application/json` |
| TC-NOT-03 | Lonceng topbar | 🖱️ Amati topbar | Badge menampilkan jumlah belum dibaca |
| TC-NOT-04 | Tandai satu dibaca | Klik satu notifikasi | Badge berkurang satu |
| TC-NOT-05 | Tandai semua dibaca | Tombol Mark all read | Badge jadi nol |
| TC-NOT-06 | Channel database selalu ditulis | Picu aksi bernotifikasi | Baris masuk tabel `notifications` |
| TC-NOT-07 | Kegagalan kirim tidak rollback | Rusakkan config mail → picu notifikasi | **Aksi pemicu tetap tersimpan**, pengiriman gagal diam-diam dicatat |
| TC-NOT-08 | WhatsApp fallback | `FONNTE_TOKEN` kosong | Pakai `LogWhatsAppGateway` — hanya mencatat, tidak berpura-pura terkirim |
| TC-NOT-09 | Preferensi notifikasi | Matikan channel email | Notifikasi tidak dikirim via email |

---

## 19. Portal Karyawan — `/my`

Login sebagai `ahmad@demo.test`. Berbagi login dan guard yang sama dengan
Backpack — tidak ada sistem auth kedua.

| ID | Skenario | Langkah | Expected |
|---|---|---|---|
| TC-MY-01 | Dashboard | Buka `/my` | ✅ 200, ringkasan pribadi |
| TC-MY-02 | Riwayat kehadiran | `/my/attendance` | ✅ 200, hanya presensi sendiri |
| TC-MY-03 | Daftar slip gaji | `/my/salary` | ✅ 200, hanya rekap sendiri |
| TC-MY-04 | Detail slip sendiri | `/my/salary/4` (Ahmad = user 4) | ✅ **200** |
| TC-MY-05 | Slip milik orang lain | `/my/salary/1`, `/2`, `/3`, `/5` | 🌐 Diuji di sesi browser sungguhan: `1:404 2:404 3:404 4:200 5:404` — **tidak ada IDOR** |
| TC-MY-06 | Cuti — daftar | `/my/leave` | ✅ 200, hanya pengajuan sendiri |
| TC-MY-07 | Cuti — form | `/my/leave/create` | ✅ 200; field `leave_type_id`, `start_date`, `end_date`, `reason`, `attachment` |
| TC-MY-08 | Ajukan cuti | Isi form → Submit | Tersimpan pending, approval terbentuk, saldo dicek |
| TC-MY-09 | Tanpa parameter user | Amati form | 🌐 Query DOM: **tidak ada `[name="user_id"]`** — id user tidak pernah diterima dari request |
| TC-MY-10 | Batalkan cuti sendiri | `POST /my/leave/{id}/cancel` | Berhasil, kuota kembali |
| TC-MY-11 | Batalkan cuti orang lain | Kirim id milik karyawan lain | **Ditolak** |
| TC-MY-12 | Kasbon | `/my/loan` | ✅ 200, hanya kasbon sendiri |
| TC-MY-13 | Profil | `/my/profile` | ✅ 200; field `phone`, `address`, `image`, `email`, password |
| TC-MY-14 | Batas edit profil | Cari field departemen / status kepegawaian | 🌐 Query DOM atas `department_id`, `employment_status`, `position_id`, `salary`, `manager_id` — **nol hasil**. Hanya HR yang boleh mengubah |
| TC-MY-15 | Ganti password | `POST /my/password` | `current_password` wajib benar; password baru berlaku |
| TC-MY-16 | Notifikasi | `/my/notifications` | ✅ 200; mark-as-read berfungsi |
| TC-MY-17 | Admin buka portal | Login `siti@` → `/my` | ✅ 200 — admin juga punya portal pribadi |

---

## 20. Defect diketahui

Ditemukan saat penyusunan dokumen ini, terhadap aplikasi berjalan dengan data demo.

### DEF-01 — Cetak Rekap Gaji selalu 500 bila logo perusahaan kosong

**Severity:** tinggi (fitur cetak slip gaji tidak bisa dipakai sama sekali)
**Test case:** TC-REC-09, TC-COMP-04
**Reproduksi:** login `siti@` → Gajian → Rekap Gaji → klik **Print**
(`/admin/salary-recap/print?id=1`)
**Hasil:** HTTP 500 — `getimagesize(): Read of 8192 bytes failed with errno=21 Is a directory`

Penyebab di [SalaryRecapCrudController.php:279-280](../app/Http/Controllers/Admin/SalaryRecapCrudController.php#L279-L280):

```php
$company->image = Storage::path("public/$company->image");
$isCompanyImage = strlen($company->image) > 0;
```

Saat `image` NULL, `Storage::path("public/")` mengembalikan path **direktori**
`storage/app/public`. Guard `strlen(...) > 0` mengecek path yang sudah diberi
prefix — yang tidak pernah kosong — sehingga `isCompanyImage` bernilai true,
blade merender `<img src="{{$company->image}}">` menunjuk direktori, lalu dompdf
memanggil `getimagesize()` atasnya. Guard harus dievaluasi pada `image` mentah
**sebelum** di-prefix.

### DEF-02 — `set-payment` adalah GET yang mengubah data dan 500 tanpa `?method=`

**Severity:** sedang
**Test case:** TC-REC-06
**Reproduksi:** ketik `/admin/salary-recap/1/set-payment` langsung di address bar
**Hasil:** HTTP 500 — `Column 'method' cannot be null`

Dari UI aman karena tombol selalu menyertakan `?method=cash` / `?method=transfer`.
Tetapi [SetPaymentOperation.php](../app/Http/Controllers/Admin/Operations/SetPaymentOperation.php)
mendaftarkannya sebagai `Route::get` dan langsung menulis `paid = 1` sebelum
menyimpan. Akibatnya: (a) bisa terpicu prefetch browser atau crawler,
(b) tanpa `method` transaksi gagal separuh jalan. Sebaiknya jadi POST dan
`method` divalidasi.

### DEF-03 — Manager punya akses tulis penuh meski hanya diberi permission baca

**Severity:** tinggi
**Test case:** TC-USER-15, TC-DASH-09, dan seluruh matriks bagian 2
**Reproduksi:** login `budi@` → buka `/admin/branch/create` → simpan cabang baru
**Hasil:** tersimpan (sudah diverifikasi lewat POST sungguhan, data uji dihapus kembali)

Role `manager` hanya memiliki 14 permission dan semuanya bersifat baca
(`presence.view`, `salary.view`, `report.view`, `approval.act`, dst.) — tidak ada
satu pun `.create` / `.edit` / `.delete`. Namun permission tersebut tidak pernah
diperiksa: [routes/backpack/custom.php](../routes/backpack/custom.php#L24-L31)
tidak memasang middleware `permission:`, dan hanya 4 controller yang punya guard.
Form create terbuka untuk `/admin/user`, `/admin/salary`, `/admin/bpjs-rate`,
`/admin/ptkp-rate`, `/admin/company-profile`, dan lainnya. Sidebar juga
menampilkan 41 link admin penuh kepada manager.

### DEF-04 — "Team visibility" manager belum diterapkan pada data karyawan

**Severity:** sedang
**Test case:** TC-USER-15, TC-PRES-01
**Hasil:** manager melihat **5 dari 5** user dan **110 dari 110** presensi —
identik dengan super_admin.

Scoping tim baru berlaku di modul approval (TC-APR-04 lulus: 1 dari 2). Menu
Users dan Kehadiran belum. Dokumen [HRIS_SETUP.md](HRIS_SETUP.md) menyebut scope
manager adalah "Team visibility + acting on approvals".

> Keempat defect ini **tidak tertangkap PHPUnit** — 150 test lulus semua, karena
> tidak ada test yang menembak route CRUD sebagai manager maupun merender PDF
> slip gaji tanpa logo. DEF-01 dan DEF-04 kini tertangkap harness browser
> ([tests/browser/ui-test.mjs](../tests/browser/ui-test.mjs)) sebagai 2 FAIL,
> sehingga akan otomatis berubah hijau begitu diperbaiki.

---

## 21. Perilaku benar yang mudah disalahartikan

Jangan laporkan sebagai bug:

| Pengamatan | Penjelasan |
|---|---|
| `/admin/user/1/print` dan `/print-all` → 302 ke Profile Perusahaan | Guard yang benar saat background ID card belum diunggah |
| `/admin/salary-recap/create` → 403 | Rekap dibuat oleh command, bukan input manual |
| `/admin/leave-request/create`, `/admin/audit-log/create`, `/admin/approval/create` → 404 | `denyAccess` disengaja |
| `/admin/permission/create` → 404 | Permission hanya bisa dilihat |
| Employee dialihkan dari seluruh `/admin/*` ke `/my` | `CheckIfAdmin` bekerja sesuai rancangan |
| Buka URL list Backpack via `curl` menghasilkan tabel kosong | Baris dimuat lewat `POST /admin/<entity>/search` |
| `/` mengalihkan ke `/scan` | Halaman muka aplikasi memang halaman scan |
| Angka dashboard tertinggal beberapa menit | Cache 5 menit yang disengaja |
| Pembayaran gaji tidak mengirim transaksi ke ACC | `ACC_ACTIVE` tidak diset di `.env` |
| User tanpa role sama sekali bisa masuk admin | Disengaja, agar akun lama sebelum upgrade role tidak terkunci |
| Dashboard menampilkan "Hadir 0 dari 5" dan "Total Gaji Rp 0, 0 rekap" | Data demo sengaja ditaruh di bulan **sebelumnya** — rekap gaji mengukur satu bulan penuh, jadi bulan berjalan yang baru separuh akan terbaca seperti absen massal |
| Grafik tren 12 bulan datar sampai Jul 26 | Hanya satu bulan data demo yang terisi |
| Halaman `/scan` tidak menampilkan video di headless | Izin kamera ditolak; halaman tetap tidak crash — perilaku benar |

---

## 22. Cakupan & keterbatasan

**✅ Level HTTP** — kode status, content-type, ukuran unduhan, scoping baris
lewat endpoint `search`, dan satu POST sungguhan untuk membuktikan DEF-03.

**🌐 Level browser** (Chromium via Playwright, 19 PASS / 2 FAIL) — sudah
mencakup rendering Chart.js, isi tabel AJAX Backpack pada 6 modul, visibilitas
menu per role, elemen pemindai QR, dan scoping portal karyawan. Skrip:
[tests/browser/ui-test.mjs](../tests/browser/ui-test.mjs).

Kedua FAIL bukan kegagalan harness, melainkan **DEF-01** (cetak slip gaji 500)
dan **DEF-04** (manager melihat semua karyawan) yang memang belum diperbaiki.

**🖱️ Belum terotomasi** — masih perlu tangan:

| Area | Alasan |
|---|---|
| Scan QR sungguhan (TC-SCAN-05 s/d 07) | Butuh kamera fisik atau injeksi stream video |
| Geofence dalam/luar radius (TC-SCAN-08 s/d 11) | Butuh mock geolokasi + akun uji per cabang |
| Mass update jadwal (TC-SCHM-02) | Interaksi pilih-banyak |
| Filter bar (TC-USER-14, TC-PRES-08) | `HasSimpleFilters` berbasis parameter GET |
| Balapan dua approver (TC-APR-13) | Butuh dua sesi paralel |
| Kalender cuti (TC-LV-15, TC-LV-16) | Navigasi bulan interaktif |
| Alur upload dokumen (TC-DOC-02 s/d 05) | Butuh berkas contoh per jenis dokumen |

Menambah test ke harness: tambahkan blok baru di
[tests/browser/ui-test.mjs](../tests/browser/ui-test.mjs) memakai helper
`login()`, `rowCount()`, `pass()`, dan `fail()` yang sudah ada. Tabel Backpack
selalu diakses lewat `#crudTable`, bukan `table.datatable`.

> **Catatan MCP:** Playwright MCP sudah terdaftar dan sehat
> (`claude mcp list` → `playwright: ✓ Connected`), tetapi skema tool MCP
> di-wire saat sesi dimulai. Server yang ditambahkan di tengah sesi baru bisa
> dipakai setelah Claude Code di-restart. Harness di atas berjalan sebagai
> library Node, jadi tidak bergantung pada MCP.
