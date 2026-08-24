# Modul 03 — Kasbon

Dropdown **Kasbon**: Rekap, Kasbon, Pembayaran Kasbon.

---

## 3.1 Kasbon — `/admin/loan`

| | |
|---|---|
| **Controller** | [LoanCrudController](../../app/Http/Controllers/Admin/LoanCrudController.php) |
| **Model / tabel** | `App\Models\Loan` / `loans` |
| **Validasi** | [LoanRequest](../../app/Http/Requests/LoanRequest.php) |
| **Operasi** | Create ✔ · Read ✔ · Update ✔ · Delete ✔ · Detail · Cetak PDF · Unduh Excel |

**Field:** `user_id`, `amount`, `date`
**Validasi:** `user_id` required·string · `amount` required·integer · `date` required·date

### CREATE

| ID | Skenario | Data | Expected | Status |
|---|---|---|---|---|
| LOAN-C-01 | Buka form | — | Form 3 field | ✅ 200 |
| LOAN-C-02 | Ajukan kasbon | Ahmad, `1000000`, hari ini | Tersimpan, muncul di rekap | ⬜ |
| LOAN-C-03 | Kasbon kedua | Ajukan lagi untuk orang sama | Terakumulasi di rekap, bukan menimpa | ⬜ |
| LOAN-C-04 | Tanggal mundur | `date` bulan lalu | Tersimpan dan masuk rekap bulan itu | ⬜ |
| LOAN-C-05 | Approval terbentuk | Create kasbon | Bila flow modul `loan` aktif, approval otomatis dibuat | ⬜ |

### READ

| ID | Skenario | Langkah | Expected | Status |
|---|---|---|---|---|
| LOAN-R-01 | List | Buka `/admin/loan` | Tabel AJAX terisi | ⬜ |
| LOAN-R-02 | Detail kasbon | `/admin/loan/1/detail` | 200; riwayat cicilan tampil | ✅ |
| LOAN-R-03 | Rekap | `/admin/loan/recap` | 200; saldo per karyawan | ✅ |
| LOAN-R-04 | Saldo konsisten | Bandingkan rekap vs (kasbon − pembayaran) | Angka cocok | ⬜ |
| LOAN-R-05 | Sisa di dashboard | Bandingkan kartu "Sisa Kasbon" | 🌐 Rp 2.000.000 pada data demo — cocok dengan rekap | 🌐 |

### UPDATE

| ID | Skenario | Data | Expected | Status |
|---|---|---|---|---|
| LOAN-U-01 | Ubah nominal | Naikkan `amount` | Sisa & cicilan terhitung ulang | ⬜ |
| LOAN-U-02 | Ubah karyawan | Ganti `user_id` | Pindah ke rekap karyawan baru, hilang dari yang lama | ⬜ |
| LOAN-U-03 | Ubah tanggal | Geser ke bulan lain | Berpindah periode rekap | ⬜ |
| LOAN-U-04 | Edit setelah dicicil | Ubah nominal kasbon yang sudah dibayar sebagian | Sisa tidak jadi negatif | ⬜ |

### DELETE

| ID | Skenario | Langkah | Expected | Status |
|---|---|---|---|---|
| LOAN-D-01 | Hapus kasbon | Delete | Hilang dari rekap | ⬜ |
| LOAN-D-02 | Hapus kasbon bercicilan | Hapus yang punya `loan_payments` | Pembayaran terkait tertangani, tidak yatim | ⬜ |
| LOAN-D-03 | Dampak ke gaji | Hapus lalu hitung ulang rekap gaji | Potongan kasbon hilang dari slip | ⬜ |

### VALIDASI

| ID | Skenario | Data | Expected | Status |
|---|---|---|---|---|
| LOAN-V-01 | Submit kosong | Semua kosong | ✅ 302 kembali ke form | ✅ |
| LOAN-V-02 | Nominal negatif | `amount=-500` | Ditolak | ⬜ |
| LOAN-V-03 | Nominal nol | `amount=0` | Ditolak atau perilaku terdefinisi | ⬜ |
| LOAN-V-04 | Nominal bukan angka | `amount=abc` | Ditolak — `integer` | ⬜ |
| LOAN-V-05 | Nominal desimal | `amount=1000.50` | Ditolak — `integer` | ⬜ |
| LOAN-V-06 | Tanggal tidak valid | `date=32-13-2026` | Ditolak — `date` | ⬜ |

### OPERASI KHUSUS

| ID | Skenario | Langkah | Expected | Status |
|---|---|---|---|---|
| LOAN-X-01 | Unduh rekap Excel | `/admin/loan/download` | 200, `.xlsx` ±6,4 KB | ✅ |
| LOAN-X-02 | Cetak detail PDF | `/admin/loan/1/print-detail` | 200, `application/pdf` | ✅ |
| LOAN-X-03 | Unduh detail Excel | `/admin/loan/1/download-detail` | 200, `.xlsx` ±6,3 KB | ✅ |
| LOAN-X-04 | Isi PDF | Buka PDF hasil cetak | Nama, nominal, cicilan, sisa benar | ⬜ |
| LOAN-X-05 | Potong dari gaji | Jalankan rekap gaji | Cicilan muncul sebagai `loan_cut` di slip | ⬜ |

### AKSES

| ID | Role | Expected | Status |
|---|---|---|---|
| LOAN-A-01 | SA / HR | Akses penuh | ✅ 200 |
| LOAN-A-02 | MGR | Punya `loan.view` saja — ⚠️ tetapi bisa create/edit/delete (DEF-03) | ⚠️ |
| LOAN-A-03 | EMP | Dialihkan; lihat kasbon sendiri di `/my/loan` | 🌐 |

---

## 3.2 Pembayaran Kasbon — `/admin/loan-payment`

| | |
|---|---|
| **Controller** | [LoanPaymentCrudController](../../app/Http/Controllers/Admin/LoanPaymentCrudController.php) |
| **Validasi** | [LoanPaymentRequest](../../app/Http/Requests/LoanPaymentRequest.php) |

**Field:** `user_id`, `amount`, `date`
**Validasi:** sama dengan Kasbon

| ID | Skenario | Data | Expected | Status |
|---|---|---|---|---|
| LPAY-C-01 | Buka form | — | Form 3 field | ✅ 200 |
| LPAY-C-02 | Catat pembayaran | Ahmad, `250000` | Sisa kasbon berkurang 250.000 | ⬜ |
| LPAY-C-03 | Kode konfirmasi | Setelah simpan, cek `confirm_code` | Terisi dan unik | ⬜ |
| LPAY-C-04 | Pelunasan penuh | Bayar tepat sebesar sisa | Sisa jadi 0, status lunas | ⬜ |
| LPAY-R-01 | List | Buka list | Tabel AJAX terisi | ⬜ |
| LPAY-R-02 | Detail | `/admin/loan-payment/1/show` | Rincian tampil | ⬜ |
| LPAY-U-01 | Ubah nominal | Naikkan pembayaran | Sisa kasbon terhitung ulang | ⬜ |
| LPAY-U-02 | Ubah tanggal | Geser periode | Rekap periode menyesuaikan | ⬜ |
| LPAY-D-01 | Hapus pembayaran | Delete | Sisa kasbon **kembali** seperti sebelum dibayar | ⬜ |
| LPAY-V-01 | Submit kosong | Semua kosong | ✅ 302 kembali ke form | ✅ |
| LPAY-V-02 | Bayar melebihi sisa | `amount` > sisa | Ditolak, atau sisa tidak negatif | ⬜ |
| LPAY-V-03 | Nominal negatif | `amount=-100` | Ditolak | ⬜ |
| LPAY-V-04 | Bayar tanpa kasbon | User yang tidak punya kasbon | Ditolak dengan pesan jelas | ⬜ |
| LPAY-A-01 | Akses MGR | Login `budi@` | ⚠️ Terbuka meski hanya punya `loan_payment.view` (DEF-03) | ⚠️ |
