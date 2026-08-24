# Modul 04 — Penggajian

Dropdown **Gajian**: Gaji (komponen) dan Rekap Gaji (hasil perhitungan bulanan).

---

## 4.1 Gaji — `/admin/salary`

| | |
|---|---|
| **Controller** | [SalaryCrudController](../../app/Http/Controllers/Admin/SalaryCrudController.php) |
| **Model / tabel** | `App\Models\Salary` / `salaries` |
| **Validasi** | [SalaryRequest](../../app/Http/Requests/SalaryRequest.php) |
| **Operasi** | Create ✔ · Read ✔ · Update ✔ · Delete ✔ |

**Field:** `user_id`, `amount`, `overtime_amount`, `overtime_type`,
`extra_time`, `extra_time_rule`, `fine`, `fine_type`, `fine_per_minute`,
`unpaid_leave_deduction`

**Validasi:** `amount` required·integer · `overtime_amount` required·integer ·
`overtime_type` required·**in:flat,hour** · `fine_type` required

### CREATE

| ID | Skenario | Data | Expected | Status |
|---|---|---|---|---|
| SAL-C-01 | Buka form | — | Form 10 field | ✅ 200 |
| SAL-C-02 | Komponen gaji lengkap | Semua field terisi | Tersimpan | ⬜ |
| SAL-C-03 | Lembur flat | `overtime_type=flat`, `overtime_amount=50000` | Lembur dibayar tetap per kejadian | ⬜ |
| SAL-C-04 | Lembur per jam | `overtime_type=hour` | Lembur = jam × tarif | ⬜ |
| SAL-C-05 | Denda per menit | `fine_type` per menit, `fine_per_minute=1000` | Potongan = menit telat × 1000 | ⬜ |
| SAL-C-06 | Denda flat | `fine_type` flat, `fine=25000` | Potongan tetap, tidak tergantung menit | ⬜ |
| SAL-C-07 | Potongan cuti unpaid | Isi `unpaid_leave_deduction` | Dipakai saat cuti tidak berbayar disetujui | ⬜ |

### READ / UPDATE / DELETE

| ID | Skenario | Langkah | Expected | Status |
|---|---|---|---|---|
| SAL-R-01 | List | Buka `/admin/salary` | Tabel AJAX terisi 5 baris (data demo) | ⬜ |
| SAL-R-02 | Detail | `/admin/salary/1/show` | Semua komponen tampil | ⬜ |
| SAL-U-01 | Naikkan gaji pokok | Ubah `amount` | Rekap **bulan berjalan** memakai nilai baru setelah dihitung ulang | ⬜ |
| SAL-U-02 | Ubah tipe lembur | flat → hour | Perhitungan lembur berubah | ⬜ |
| SAL-U-03 | Rekap lama tidak berubah | Edit gaji → lihat rekap bulan lalu | Rekap historis **tidak** ikut berubah sampai dihitung ulang | ⬜ |
| SAL-D-01 | Hapus komponen gaji | Delete | Karyawan tanpa komponen gaji tidak membuat rekap crash | ⬜ |

### VALIDASI

| ID | Skenario | Data | Expected | Status |
|---|---|---|---|---|
| SAL-V-01 | Submit kosong | Semua kosong | ✅ 302 kembali ke form | ✅ |
| SAL-V-02 | `overtime_type` tidak sah | `overtime_type=bulanan` | Ditolak — hanya `flat` atau `hour` | ⬜ |
| SAL-V-03 | `amount` bukan angka | `amount=abc` | Ditolak | ⬜ |
| SAL-V-04 | Nominal negatif | `amount=-1000` | Ditolak atau perilaku terdefinisi | ⬜ |
| SAL-V-05 | Tanpa `fine_type` | Kosongkan | Ditolak — wajib | ⬜ |
| SAL-V-06 | Gaji ganda | Dua baris untuk user yang sama | Ditolak, atau perilaku terdefinisi jelas | ⬜ |

---

## 4.2 Rekap Gaji — `/admin/salary-recap`

| | |
|---|---|
| **Controller** | [SalaryRecapCrudController](../../app/Http/Controllers/Admin/SalaryRecapCrudController.php) |
| **Operasi khusus** | [SetPaymentOperation](../../app/Http/Controllers/Admin/Operations/SetPaymentOperation.php) |
| **Service** | [SalaryService](../../app/Services/SalaryService.php) |
| **Operasi** | Create ✖ (**403**) · Read ✔ · Update ✔ · Delete ✔ · Bayar · Hitung ulang · Export · Cetak |

Rekap **tidak dibuat manual** — dihasilkan command
`php artisan salary:calculate` / `salary:recalculate`.

### CREATE

| ID | Skenario | Langkah | Expected | Status |
|---|---|---|---|---|
| REC-C-01 | Create dinonaktifkan | Buka `/admin/salary-recap/create` | **403** — memang sengaja ditutup | ✅ |
| REC-C-02 | Generate via command | `php artisan salary:calculate` | Rekap terbentuk untuk semua karyawan aktif | ⬜ |
| REC-C-03 | Jalankan dua kali | Ulangi command bulan yang sama | Tidak menggandakan baris | ⬜ |
| REC-C-04 | Karyawan resigned | Jalankan command | Karyawan `resigned` **tidak** ikut (`User::employed()`) | ⬜ |

### READ

| ID | Skenario | Langkah | Expected | Status |
|---|---|---|---|---|
| REC-R-01 | List | Buka `/admin/salary-recap` | Tabel AJAX **5 dari 5** baris | 🌐 |
| REC-R-02 | Detail | `/admin/salary-recap/1/show` | 200; rincian komponen lengkap | ✅ |
| REC-R-03 | Filter bulan | Gunakan `filter_monthly` | Hanya rekap bulan terpilih | ⬜ |
| REC-R-04 | Kolom perhitungan | Amati `work_day`, `late_day`, `loan_cut`, `abstain_cut`, `received` | Terisi konsisten: received = gaji + lembur − semua potongan | ⬜ |

### UPDATE — pembayaran

| ID | Skenario | Langkah | Expected | Status |
|---|---|---|---|---|
| REC-U-01 | Bayar tunai | Klik **Bayar Cash** | `?method=cash` → 302 ke list; `paid=1`, `method=cash`, alert sukses | ✅ |
| REC-U-02 | Bayar transfer | Klik **Bayar Transfer** | `?method=transfer` → `method=transfer` | ⬜ |
| REC-U-03 | Bayar dua kali | Klik Bayar pada rekap yang sudah `paid=1` | Perilaku terdefinisi — tidak menggandakan transaksi akuntansi | ⬜ |
| REC-U-04 | **Tanpa parameter method** | Ketik `/admin/salary-recap/1/set-payment` di address bar | ⚠️ **GAGAL — 500** `Column 'method' cannot be null`; `paid` sudah terlanjur diset. Lihat DEF-02 | ✅ ⚠️ |
| REC-U-05 | Edit rekap manual | Ubah nilai lewat form edit | Tersimpan; namun akan tertimpa bila dihitung ulang | ⬜ |
| REC-U-06 | Transaksi akuntansi | `ACC_ACTIVE=true` → bayar | Transaksi WITHDRAWAL tercatat ke ACC | ⬜ |
| REC-U-07 | Akuntansi nonaktif | `ACC_ACTIVE` tidak diset | Pembayaran tetap berhasil, **tidak** kirim transaksi eksternal | ✅ |

### UPDATE — hitung ulang

| ID | Skenario | Langkah | Expected | Status |
|---|---|---|---|---|
| REC-X-01 | Hitung ulang satu rekap | Tombol **Recalculate** | 302 kembali ke list, angka diperbarui | ✅ |
| REC-X-02 | Hitung ulang massal | `php artisan salary:recalculate` | Semua rekap periode diperbarui | ⬜ |
| REC-X-03 | Hitung ulang setelah koreksi presensi | Edit presensi → recalculate | Angka gaji mengikuti presensi terbaru | ⬜ |
| REC-X-04 | Hitung ulang rekap terbayar | Recalculate rekap yang `paid=1` | Perilaku terdefinisi — status bayar tidak hilang diam-diam | ⬜ |

### Perhitungan cuti vs absen — inti perbaikan M2

| ID | Skenario | Data demo | Expected | Status |
|---|---|---|---|---|
| REC-B-01 | Cuti berbayar disetujui | Ahmad, 3 hari | **Tidak** dihitung absen, **tidak** dipotong | ⬜ |
| REC-B-02 | Absen tanpa keterangan | Dewi, 2 hari | Dihitung absen **dan** dipotong | ⬜ |
| REC-B-03 | Cuti tidak berbayar disetujui | Buat cuti `is_paid=0` | **Tidak** dihitung absen, **tetapi** dipotong | ⬜ |
| REC-B-04 | Cuti masih pending | Ajukan tanpa persetujuan | Tetap dihitung absen — pending tidak memaafkan apa pun | ⬜ |
| REC-B-05 | Akhir pekan | Rekap bulan penuh | Hari libur jadwal tidak dihitung absen | ⬜ |
| REC-B-06 | Libur nasional | Bulan berisi libur nasional | Tidak dihitung absen | ⬜ |

> Data demo sengaja menempatkan Ahmad dan Dewi berdampingan: kekurangan
> kehadiran sama, hasil payroll berlawanan. Uji keduanya berpasangan.

### DELETE

| ID | Skenario | Langkah | Expected | Status |
|---|---|---|---|---|
| REC-D-01 | Hapus rekap | Delete | Terhapus; bisa dibentuk ulang lewat command | ⬜ |
| REC-D-02 | Hapus rekap terbayar | Delete rekap `paid=1` | Perilaku terdefinisi terhadap transaksi akuntansi | ⬜ |

### OPERASI KHUSUS

| ID | Skenario | Langkah | Expected | Status |
|---|---|---|---|---|
| REC-X-05 | Export Excel | `/admin/salary-recap/export` | 200, `.xlsx` ±6,8 KB | ✅ |
| REC-X-06 | **Cetak slip PDF** | Tombol Print (`/admin/salary-recap/print?id=1`) | ⚠️ **GAGAL — 500** `getimagesize(): ... Is a directory`. Lihat DEF-01 | 🌐 ⚠️ |
| REC-X-07 | Cetak setelah logo diunggah | Unggah logo di Profil Perusahaan → cetak ulang | Seharusnya PDF terbit — uji ulang untuk memastikan DEF-01 hanya soal logo kosong | ⬜ |
| REC-X-08 | Cetak per bulan | `/admin/salary-recap/print?salary_recap=<bulan>` | Semua slip bulan itu dalam satu PDF | ⬜ |

### AKSES

| ID | Role | Expected | Status |
|---|---|---|---|
| REC-A-01 | SA / HR | Akses penuh termasuk bayar | ✅ 200 |
| REC-A-02 | MGR | Punya `salary_recap.view` saja — ⚠️ tetapi tombol bayar & edit terbuka (DEF-03) | ⚠️ |
| REC-A-03 | EMP | Dialihkan; slip sendiri di `/my/salary` | 🌐 |
