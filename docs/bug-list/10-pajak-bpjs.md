# Bug List — Modul 10 Pajak & BPJS

Test case: [../test-cases/10-pajak-bpjs.md](../test-cases/10-pajak-bpjs.md)

| Hasil suite | 18 PASS / 7 FAIL — **modul dengan kegagalan terbanyak** |
|---|---|
| Bug modul ini | BUG-005, BUG-006 |
| Bug lintas modul | BUG-003 |

Keempat entity CRUD di modul ini — `ptkp-rate`, `pph21-bracket`, `bpjs-rate`,
`tax-profile` — **tidak punya validasi server sama sekali**. Modul ini menyimpan
tarif pajak dan iuran yang dipakai menghitung potongan gaji sungguhan, sehingga
ketiadaan validasi di sini berdampak paling jauh.

---

## BUG-005 — Empat entity tanpa validasi → HTTP 500

| | |
|---|---|
| **Severity** | 🔴 Kritis |
| **Status** | Terkonfirmasi keempatnya |
| **Test case** | `ptkp-rate/V-empty`, `pph21-bracket/V-empty`, `bpjs-rate/V-empty`, `TXP-V-01` |

### Reproduksi

Buka form create salah satu entity, langsung klik Simpan tanpa mengisi apa pun.

### Hasil aktual

| Entity | Galat |
|---|---|
| `ptkp-rate` | `1364 Field 'year' doesn't have a default value` |
| `pph21-bracket` | `1364 Field 'year' doesn't have a default value` |
| `bpjs-rate` | `1364 Field 'year' doesn't have a default value` |
| `tax-profile` | `1364 Field 'user_id' doesn't have a default value` |

### Perbaikan

```php
// PtkpRateCrudController
CRUD::setValidation([
    'year'   => 'required|integer|min:2000|max:2100',
    'status' => 'required|string|max:10',
    'amount' => 'required|integer|min:0',
]);

// Pph21BracketCrudController
CRUD::setValidation([
    'year'        => 'required|integer|min:2000|max:2100',
    'lower_bound' => 'required|integer|min:0',
    'upper_bound' => 'nullable|integer|gt:lower_bound',
    'rate'        => 'required|numeric|min:0|max:100',
]);

// BpjsRateCrudController
CRUD::setValidation([
    'year'          => 'required|integer|min:2000|max:2100',
    'type'          => 'required|string|max:30',
    'employee_rate' => 'required|numeric|min:0|max:100',
    'employer_rate' => 'required|numeric|min:0|max:100',
    'max_salary'    => 'nullable|integer|min:0',
]);

// EmployeeTaxProfileCrudController
CRUD::setValidation([
    'user_id'    => 'required|exists:users,id',
    'tax_status' => 'required|string|max:10',
    'tax_method' => 'required|string|max:20',
    'npwp'       => 'nullable|string|max:25',
]);
```

Pasang juga di `setupUpdateOperation()`.

---

## BUG-006 — Tarif duplikat → HTTP 500

| | |
|---|---|
| **Severity** | 🟠 Tinggi |
| **Status** | Terkonfirmasi |

### Reproduksi & hasil

| Entity | Data | Galat |
|---|---|---|
| `ptkp-rate` | tahun 2026 + status `TK/0` (sudah ada) | `1062 Duplicate entry '2026-TK/0'` |
| `bpjs-rate` | tahun 2026 + tipe `kesehatan` (sudah ada) | `1062 Duplicate entry '2026-kesehatan'` |
| `tax-profile` | `user_id=4` (sudah punya profil) | `1062 Duplicate entry '4'` |

### Mengapa ini penting di modul pajak

Tarif disimpan **per tahun** justru supaya perhitungan historis memakai tarif
periodenya sendiri. Saat admin menyiapkan tarif tahun baru, menyimpan kombinasi
tahun+status yang sudah ada adalah kesalahan yang **sangat mungkin terjadi** —
dan saat ini berakhir dengan layar 500, bukan pesan "tarif ini sudah ada".

### Perbaikan

```php
'year' => [
    'required', 'integer', 'min:2000', 'max:2100',
    Rule::unique('ptkp_rates')->where(fn ($q) => $q->where('status', request('status'))),
],
```

Pada update tambahkan `->ignore($id)`.

---

## BUG-003 — Manager bisa mengubah tarif pajak dan BPJS

| | |
|---|---|
| **Severity** | 🔴 Kritis |
| **Test case** | `bpjs-rate/A-mgr-write`, `ptkp-rate/A-mgr-write`, `pph21-bracket/A-mgr-write`, `tax-profile/A-mgr-write` |

Manager mendapat HTTP 200 pada keempat form create, padahal role `manager`
**tidak punya satu pun permission pajak** — tidak ada `tax.*` di 14
permission-nya.

Ini varian BUG-003 yang paling serius setelah Audit Log. Manager dapat mengubah:

- **Tarif PTKP** dan **lapisan PPh 21** → mengubah pajak terutang seluruh karyawan
- **Tarif BPJS** → mengubah potongan iuran seluruh karyawan
- **Profil pajak** → termasuk NPWP dan status PTKP karyawan lain, yang menentukan
  surcharge 20%

Perubahan tarif berlaku pada perhitungan berikutnya dan pada setiap
`tax-report/recalculate`, sehingga dampaknya menyebar ke slip gaji dan laporan
SPT.

Perbaikan: [lintas-modul.md § BUG-003](lintas-modul.md#bug-003--manager-punya-akses-tulis-penuh-tanpa-permission).

Modul ini juga **belum punya permission sendiri** — tidak ada `tax.view` /
`tax.edit` di daftar 54 permission. Perlu ditambahkan lebih dulu di
[RolesAndPermissionsSeeder](../../database/seeders/RolesAndPermissionsSeeder.php),
lalu diberikan ke `super_admin` dan `hr_admin` saja.

---

## Yang sudah benar di modul ini

| Perilaku | Status |
|---|---|
| Create data valid pada keempat entity | ✅ tersimpan |
| Update lewat form UI pada keempat entity | ✅ tersimpan |
| Delete pada keempat entity | ✅ terhapus |
| Tabel list dimuat via AJAX | ✅ 12 PTKP · 5 PPh21 · 5 BPJS · 5 profil |
| `/admin/tax-report/annual` | ✅ 200 |
| `/admin/tax-report/bpjs` | ✅ 200 |
| Employee dialihkan dari `/admin/bpjs-rate` ke `/my` | ✅ |

---

## ✅ Perhitungan sudah diuji — semuanya benar

Diuji langsung terhadap `TaxService` dengan data demo. **Tidak ada bug
ditemukan** di seluruh perhitungan.

### BPJS — plafon dan penanggung

Gaji Rp 8.000.000 (di bawah semua plafon) dan Rp 20.000.000 (di atas plafon):

| Komponen | Gaji 8jt | Gaji 20jt | Verifikasi |
|---|---|---|---|
| Kesehatan karyawan 1% | 80.000 | **120.000** | ✅ plafon 12.000.000 diterapkan |
| Kesehatan pemberi kerja 4% | 320.000 | **480.000** | ✅ plafon diterapkan |
| JHT karyawan 2% | 160.000 | **400.000** | ✅ tanpa plafon |
| JHT pemberi kerja 3,7% | 296.000 | **740.000** | ✅ tanpa plafon |
| JP karyawan 1% | 80.000 | **100.423** | ✅ plafon 10.042.300 diterapkan |
| JP pemberi kerja 2% | 160.000 | **200.846** | ✅ plafon diterapkan |
| JKK 0,24% | 19.200 | 48.000 | ✅ hanya di `employer_total` |
| JKM 0,3% | 24.000 | 60.000 | ✅ hanya di `employer_total` |
| **Total karyawan** | 320.000 | **620.423** | ✅ JKK & JKM **tidak** memotong karyawan |
| **Total pemberi kerja** | 819.200 | 1.528.846 | ✅ |

### PPh 21

| Uji | Hasil |
|---|---|
| Surcharge tanpa NPWP | dengan NPWP 939.925 → tanpa NPWP **1.127.910**, rasio tepat **1,2** ✅ |
| Gaji di bawah PTKP (Rp 2.000.000) | PPh 21 = **0**, tidak negatif ✅ |
| Biaya jabatan | `min($annualGross * 0.05, 6_000_000)` — plafon benar per konstanta ✅ |

### THR

| Masa kerja | THR | Verifikasi |
|---|---|---|
| 18 bulan | Rp 5.000.000 | ✅ satu bulan penuh |
| 6 bulan | Rp 2.500.000 | ✅ prorata |
| 20 hari | Rp 0 | ✅ nihil |

> **Catatan metodologi.** Uji pertama saya keliru melaporkan surcharge NPWP
> tidak berlaku. Penyebabnya `$user->taxProfile` adalah relasi Eloquent yang
> ter-cache setelah akses pertama — mengubah profil lewat instance lain dalam
> proses yang sama tidak terbaca. Setelah diuji dengan `User::find()` segar tiap
> perhitungan, rasionya tepat 1,2. Kodenya tidak pernah salah.

---

## Belum teruji

- Tahun tanpa tarif → jatuh ke tahun terbit terakhir (`TAX-X-15`)
- Lapisan PPh 21 tumpang tindih / bercelah (`PPH-V-02`, `PPH-V-03`)
- `POST /admin/tax-report/recalculate` (`TAX-R-03`)
- Konsistensi rekap vs kolom statutori di `salary_recaps` (`TAX-R-04`)

> ⚠️ Ingat peringatan di [HRIS_SETUP.md](../HRIS_SETUP.md): verifikasi tarif hasil
> seed terhadap regulasi terbaru sebelum payroll sungguhan. `TaxRateSeeder`
> mengacu PMK 101/2016 dan UU HPP No. 7/2021, dan JKK diisi kelas risiko
> terendah (0,24%).
