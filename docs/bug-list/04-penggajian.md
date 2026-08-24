# Bug List — Modul 04 Penggajian

Test case: [../test-cases/04-penggajian.md](../test-cases/04-penggajian.md)

| Hasil suite | 2 PASS / 2 FAIL |
|---|---|
| Bug modul ini | BUG-001, BUG-002 |
| Bug lintas modul | BUG-003 |

---

## BUG-001 — Cetak slip gaji selalu HTTP 500 bila logo perusahaan kosong

| | |
|---|---|
| **Severity** | 🔴 Kritis — fitur cetak slip gaji **mati total** pada instalasi baru |
| **Status** | ✅ **SUDAH DIPERBAIKI** — PDF terbit, diverifikasi visual dengan & tanpa logo |
| **Test case** | `REC-X-06`, `TC-REC-09`, `COMP-X-01` |

### Reproduksi

1. Login `siti@demo.test`
2. Buka **Gajian → Rekap Gaji**
3. Klik tombol **Print** pada baris mana pun (`/admin/salary-recap/print?id=1`)

### Hasil aktual

```
HTTP 500
getimagesize(): Read of 8192 bytes failed with errno=21 Is a directory
```

Terjadi untuk **semua** rekap, bukan satu baris tertentu.

### Hasil diharapkan

PDF slip gaji terbit. Bila logo belum diunggah, slip tetap terbit **tanpa logo**.

### Akar masalah

[SalaryRecapCrudController.php:279-280](../../app/Http/Controllers/Admin/SalaryRecapCrudController.php#L279-L280):

```php
$company->image = Storage::path("public/$company->image");   // baris 279
$isCompanyImage = strlen($company->image) > 0;               // baris 280
```

Guard dievaluasi **setelah** path diberi prefix. Saat `image` bernilai `NULL`,
`Storage::path("public/")` mengembalikan path **direktori**
`/…/storage/app/public` — yang panjangnya jelas bukan nol. Maka
`$isCompanyImage` bernilai `true`, blade merender:

```blade
<img class="company-logo" src="{{$company->image}}"/>
```

menunjuk sebuah direktori, dan dompdf memanggil `getimagesize()` di atasnya.

Pada data demo `company_profiles.image` memang `NULL`, jadi bug ini aktif sejak
instalasi pertama.

### Perbaikan yang disarankan

Evaluasi guard pada nilai **mentah**, sebelum diberi prefix:

```php
$company = CompanyProfile::first();

$isCompanyImage = filled($company?->image);
if ($isCompanyImage) {
    $path = Storage::path("public/{$company->image}");
    $isCompanyImage = is_file($path);        // tahan terhadap berkas yang hilang
    $company->image = $path;
}

$pdf = Pdf::loadView('salary-recap.print', compact('recaps', 'isCompanyImage', 'company'));
```

Pastikan [print.blade.php:54](../../resources/views/salary-recap/print.blade.php#L54)
menghormati `$isCompanyImage`:

```blade
@if($isCompanyImage)
    <img class="company-logo" src="{{$company->image}}"/>
@endif
```

Pola yang benar sudah ada di aplikasi ini — cetak ID card mengalihkan pengguna ke
Profil Perusahaan ketika `id_card` kosong, alih-alih meledak. Ikuti pola itu.

### Verifikasi

`node tests/browser/crud-suite.mjs` → `salary-recap/X-print` harus PASS
(200, `application/pdf`). Uji dua kondisi: logo kosong **dan** logo terisi.

---

## BUG-002 — `set-payment` adalah GET yang mengubah data dan 500 tanpa `?method`

| | |
|---|---|
| **Severity** | 🟡 Sedang |
| **Status** | ✅ **SUDAH DIPERBAIKI** — GET kini 405, `method` divalidasi, pembayaran ganda ditolak |
| **Test case** | `REC-U-04`, `TC-REC-06` |

### Reproduksi

Ketik langsung di address bar: `/admin/salary-recap/1/set-payment`

### Hasil aktual

```
HTTP 500
SQLSTATE[23000]: Column 'method' cannot be null
  (update `salary_recaps` set `paid` = 1, `method` = ? … where `id` = 1)
```

Perhatikan urutannya: `paid = 1` **sudah dikirim** ke query yang gagal.

### Hasil diharapkan

Perubahan status pembayaran hanya lewat POST, dengan `method` tervalidasi.

### Catatan

Dari UI hal ini **aman** — tombol Bayar Cash / Bayar Transfer selalu menyertakan
`?method=cash` atau `?method=transfer`, dan alurnya sudah diverifikasi berhasil
(302 kembali ke list, `paid=1`, `method` terisi, alert sukses).

Yang berisiko adalah sifat GET-nya:

- **Bisa terpicu tanpa niat** — prefetch browser, crawler, atau scanner keamanan
  yang menelusuri tautan bisa menandai gaji sebagai terbayar.
- **Gagal separuh jalan** — tanpa `method`, `paid` terlanjur diset di memori
  sebelum query gagal.

### Akar masalah

[SetPaymentOperation.php](../../app/Http/Controllers/Admin/Operations/SetPaymentOperation.php):

```php
Route::get($segment.'/{id}/set-payment', [       // ← GET untuk operasi yang menulis
    'as'   => $routeName.'.setPayment',
    'uses' => $controller.'@setPayment',
]);

public function setPayment()
{
    $recap = $this->crud->getCurrentEntry();
    $recap->paid = 1;                                        // diset lebih dulu…
    $recap->method = $this->crud->getRequest()->get('method'); // …baru diisi, tanpa validasi
    $recap->saveQuietly();
    …
}
```

Route `recalculate-salary` di berkas yang sama juga GET, tetapi idempoten
sehingga risikonya jauh lebih kecil.

### Perbaikan yang disarankan

```php
Route::post($segment.'/{id}/set-payment', [
    'as'        => $routeName.'.setPayment',
    'uses'      => $controller.'@setPayment',
    'operation' => 'setPayment',
]);

public function setPayment(Request $request)
{
    $data = $request->validate([
        'method' => 'required|in:cash,transfer',
    ]);

    $recap = $this->crud->getCurrentEntry();
    abort_if(! $recap, 404);

    DB::transaction(function () use ($recap, $data) {
        $recap->forceFill(['paid' => 1, 'method' => $data['method']])->saveQuietly();
        app(TransactionService::class)->updateRecordSalaryToACC($recap);
    });

    Alert::add('success', "<strong>Berhasil</strong><br>Berhasil bayar secara {$recap->method}")->flash();
    return redirect(route('salary-recap.index'));
}
```

Ubah pula tombolnya jadi form POST — lihat
`resources/views/vendor/backpack/crud/buttons/set_payment_cash.blade.php` dan
`set_payment_transfer.blade.php`.

Sekalian pertimbangkan menolak pembayaran ganda:

```php
abort_if($recap->paid, 422, 'Rekap gaji ini sudah dibayar.');
```

### Verifikasi

- POST dengan `method=cash` → berhasil
- POST tanpa `method` → pesan validasi, `paid` **tidak** berubah
- GET ke URL lama → 405 Method Not Allowed

---

## BUG-003 — Manager bisa membuat komponen gaji

| | |
|---|---|
| **Severity** | 🔴 Kritis |
| **Test case** | `salary/A-mgr-write` |

Manager mendapat HTTP 200 pada `/admin/salary/create` meski hanya punya
`salary.view`. Artinya manager dapat mengubah gaji pokok, tarif lembur, dan
aturan denda seluruh karyawan.

Rekap Gaji pun terbuka — termasuk tombol **Bayar**, yang memicu transaksi
akuntansi bila `ACC_ACTIVE` aktif.

Detail dan perbaikan: [lintas-modul.md § BUG-003](lintas-modul.md#bug-003--manager-punya-akses-tulis-penuh-tanpa-permission).

Permission yang relevan sudah ada dan tinggal ditegakkan: `salary.edit`,
`salary.pay`, `salary.recalculate`, `salary_recap.edit`.

---

## Yang sudah benar di modul ini

| Perilaku | Status |
|---|---|
| `salary-recap/create` ditolak **403** — rekap hanya dibuat command | ✅ |
| Export rekap gaji `.xlsx` | ✅ 200 |
| Hitung ulang gaji (`recalculate-salary`) | ✅ 302, angka diperbarui |
| Bayar lewat tombol UI (`?method=cash`) | ✅ tersimpan benar |
| Employee dialihkan dari `/admin/salary-recap` ke `/my` | ✅ |
| Tanpa `ACC_ACTIVE`, pembayaran tidak kirim transaksi eksternal | ✅ |
