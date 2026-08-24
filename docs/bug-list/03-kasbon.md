# Bug List — Modul 03 Kasbon

Test case: [../test-cases/03-kasbon.md](../test-cases/03-kasbon.md)

| Hasil suite | 18 PASS / 0 FAIL |
|---|---|
| Bug modul ini | BUG-008, BUG-009 — keduanya ✅ **sudah diperbaiki** |
| Bug lintas modul | BUG-003 ✅ sudah diperbaiki |

---

## BUG-008 — Nominal kasbon menerima nol dan negatif

| | |
|---|---|
| **Severity** | 🟠 Tinggi (berdampak uang) |
| **Status** | ✅ **SUDAH DIPERBAIKI** |
| **Test case** | `LOAN-V-02`, `LOAN-V-03`, `LPAY-V-03` |

### Reproduksi

Buat kasbon untuk karyawan mana pun dengan `amount = -500000`, lalu ulangi
dengan `amount = 0`. Berlaku sama di Pembayaran Kasbon.

### Hasil aktual (sebelum perbaikan)

**Keduanya tersimpan.** Aturannya hanya `'amount' => 'required|integer'` —
`integer` menerima bilangan negatif dan nol.

Yang membuatnya berbahaya: kasbon bernilai negatif berbalik menjadi
**tambahan gaji** lewat kolom `loan_cut` saat rekap dihitung. Jadi ini bukan
sekadar data kotor, tetapi jalur untuk menaikkan gaji lewat form kasbon.

### Perbaikan

`min:1` ditambahkan pada kedua Form Request, plus `user_id` diperketat dari
`string` menjadi `exists:users,id`:

```php
// LoanRequest & LoanPaymentRequest
'user_id' => 'required|exists:users,id',
'amount'  => 'required|integer|min:1',
'date'    => 'required|date',
```

Pesan validasinya juga dirapikan — sebelumnya berbunyi "Kolom amount harus
diisi", kini "Nominal kasbon harus diisi".

### Verifikasi

Empat kombinasi diuji, semuanya kini ditolak: kasbon `-500000`, kasbon `0`,
pembayaran `-500000`, pembayaran `0`.

---

## BUG-009 — Pembayaran kasbon bisa melebihi sisa tagihan

| | |
|---|---|
| **Severity** | 🟠 Tinggi (berdampak uang) |
| **Status** | ✅ **SUDAH DIPERBAIKI** |
| **Test case** | `LPAY-V-02`, `LPAY-V-04` |

### Reproduksi

Sisa kasbon Ahmad Rp 2.000.000. Catat pembayaran Rp 999.000.000.

### Hasil aktual (sebelum perbaikan)

Diterima. Sisa tagihan menjadi **−997.500.000**. Tidak ada pengecekan saldo
sama sekali — sisa negatif kemudian berbalik menjadi tambahan gaji, sama seperti
BUG-008.

### Perbaikan

Aturan closure di [LoanPaymentRequest](../../app/Http/Requests/LoanPaymentRequest.php)
membandingkan nominal terhadap sisa sesungguhnya, dan mengecualikan baris yang
sedang diedit agar menurunkan nominal pembayaran lama tidak ikut tertolak:

```php
$kasbon  = (int) Loan::where('user_id', $userId)->sum('amount');
$dibayar = (int) LoanPayment::where('user_id', $userId)
    ->when($this->input('id'), fn ($q, $id) => $q->where('id', '!=', $id))
    ->sum('amount');
$sisa = $kasbon - $dibayar;

if ($sisa <= 0)          $fail('Karyawan ini tidak punya sisa kasbon yang perlu dibayar.');
if ((int) $value > $sisa) $fail('Pembayaran melebihi sisa kasbon (sisa: Rp …).');
```

### Verifikasi

Lima kasus diuji:

| Kasus | Hasil |
|---|---|
| Bayar Rp 999.000.000 saat sisa Rp 2.000.000 | ✅ ditolak, sisa tetap Rp 2.000.000 |
| Bayar **tepat** Rp 2.000.000 | ✅ diterima, sisa jadi 0 |
| Bayar Rp 1 saat sisa sudah 0 | ✅ ditolak |
| Edit pembayaran Rp 1.000.000 → Rp 800.000 | ✅ diterima (baris sendiri dikecualikan) |
| Edit pembayaran → Rp 99.000.000 | ✅ ditolak, nilai lama bertahan |

> Batas ini sengaja diterapkan di lapisan validasi, bukan di observer, supaya
> pesannya muncul di form dan tidak ada penulisan separuh jalan.

---

---

## BUG-003 — Manager bisa membuat kasbon dan mencatat pembayaran

| | |
|---|---|
| **Severity** | 🔴 Kritis |
| **Test case** | `loan/A-mgr-write`, `loan-payment/A-mgr-write` |

Login `budi@demo.test` → `/admin/loan/create` dan `/admin/loan-payment/create`
keduanya **HTTP 200**.

Role `manager` hanya punya `loan.view` dan `loan_payment.view`. Yang bisa
dilakukan saat ini: menerbitkan kasbon atas nama karyawan mana pun, dan mencatat
pembayaran yang tidak pernah terjadi.

Ini bernilai uang langsung. Kasbon memotong gaji lewat kolom `loan_cut` di rekap
gaji, dan pembayaran mengurangi sisa tagihan — keduanya jalur keuangan, bukan
sekadar data administratif.

Permission yang sudah ada tinggal ditegakkan: `loan.create`, `loan.edit`,
`loan.delete`, `loan_payment.create`, `loan_payment.edit`, `loan_payment.delete`.

Perbaikan: [lintas-modul.md § BUG-003](lintas-modul.md#bug-003--manager-punya-akses-tulis-penuh-tanpa-permission).

---

## Yang sudah benar di modul ini

| Perilaku | Status |
|---|---|
| Kasbon: create, update, delete | ✅ ketiganya |
| Pembayaran Kasbon: create, update, delete | ✅ ketiganya |
| Form kosong ditolak validasi — kedua entity | ✅ |
| Tabel list dimuat AJAX | ✅ |
| Unduh rekap kasbon `.xlsx` | ✅ 200 |
| Cetak detail kasbon PDF | ✅ 200 `application/pdf` |
| Halaman rekap `/admin/loan/recap` | ✅ 200 |
| Sisa kasbon di dashboard cocok dengan rekap (Rp 2.000.000) | ✅ |
| Employee melihat kasbon sendiri di `/my/loan` | ✅ |

---

## BUG-011 — Menghapus kasbon yang sudah dicicil membuat karyawan terjebak

| | |
|---|---|
| **Severity** | 🟠 Tinggi |
| **Status** | ✅ **SUDAH DIPERBAIKI** |
| **Test case** | `LOAN-D-02` |

Ditemukan justru **karena** perbaikan BUG-009: batas pembayaran kini dihitung
dari sisa sesungguhnya, sehingga sisa negatif berubah dari sekadar data kotor
menjadi penghalang keras.

### Reproduksi

Kasbon Ahmad Rp 3.000.000, sudah dicicil Rp 1.000.000. Hapus kasbonnya.

### Hasil aktual (sebelum perbaikan)

```
sebelum → kasbon=3.000.000 cicilan=1.000.000 sisa=2.000.000
DELETE /admin/loan/1 → 200
sesudah → kasbon=0 cicilan=1.000.000 sisa=-1.000.000
```

Dua dampaknya:

1. **Baris cicilan menggantung** — Rp 1.000.000 tanpa kasbon yang ditagih
2. **Karyawan terjebak permanen** — setiap setoran berikutnya ditolak dengan
   "tidak punya sisa kasbon", karena sisanya negatif

Payroll **tidak** terpengaruh: `loan_cut` tetap 0, tidak berbalik jadi tambahan
gaji. Itu membatasi keparahannya.

### Akar masalah

`loan_payments` **tidak punya kolom `loan_id`** — cicilan dicatat per
**karyawan**, bukan per kasbon:

```
id | user_id | amount | date | created_at | updated_at | salary_recap_id | acc_id
```

Jadi "cicilan milik kasbon ini" tidak terdefinisi di skema, dan cascade delete
bukan perbaikan yang tepat. Yang benar adalah **mencegah penghapusan** yang
membuat buku pembayaran karyawan menggantung.

### Perbaikan

Guard di [LoanCrudController::destroy()](../../app/Http/Controllers/Admin/LoanCrudController.php):

```php
$kasbonLain = (int) Loan::where('user_id', $loan->user_id)
    ->where('id', '!=', $loan->id)->sum('amount');
$dibayar = (int) LoanPayment::where('user_id', $loan->user_id)->sum('amount');

if ($dibayar > $kasbonLain) {
    return response()->json([
        'message' => 'Kasbon ini tidak bisa dihapus karena sudah dicicil Rp '
            . number_format($dibayar - $kasbonLain, 0, ',', '.')
            . '. Hapus atau sesuaikan pembayarannya lebih dulu.',
    ], 422);
}
```

Perhatikan `where('id', '!=', $loan->id)` — perbandingannya terhadap kasbon
**lain** milik karyawan itu, sehingga menghapus salah satu dari beberapa kasbon
tetap boleh selama sisanya masih menutupi cicilan yang sudah masuk.

### Verifikasi

| Kasus | Hasil |
|---|---|
| Hapus kasbon yang sudah dicicil Rp 1.000.000 | ✅ **422** dengan pesan jelas, data utuh |
| Hapus kasbon tanpa cicilan | ✅ **200**, terhapus |

---

## Belum teruji — aturan bisnis

| Test case | Hal yang perlu dipastikan |
|---|---|
| `LPAY-D-01` | Hapus pembayaran → sisa kembali seperti semula |
| `LOAN-X-05` | Cicilan terpotong benar di slip gaji |
| `LPAY-C-03` | `confirm_code` terisi dan unik |
