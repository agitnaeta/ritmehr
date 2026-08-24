# Test Case CRUD Operasional — per Modul

Satu berkas per modul, masing-masing memuat test case **Create, Read, Update,
Delete**, validasi, hak akses per role, dan operasi khusus modul tersebut.

Untuk test case UI menyeluruh lintas modul (navigasi, rendering, alur bisnis),
lihat [../UI_TEST_CASES.md](../UI_TEST_CASES.md).

---

## Daftar modul

| # | Modul | Entity | Berkas |
|---|---|---|---|
| 01 | Users | `users` | [01-users.md](01-users.md) |
| 02 | Absensi | `presences`, `schedules`, `national_holidays` | [02-absensi.md](02-absensi.md) |
| 03 | Kasbon | `loans`, `loan_payments` | [03-kasbon.md](03-kasbon.md) |
| 04 | Penggajian | `salaries`, `salary_recaps` | [04-penggajian.md](04-penggajian.md) |
| 05 | Profil Perusahaan | `company_profiles` | [05-profil-perusahaan.md](05-profil-perusahaan.md) |
| 06 | Konfigurasi Akuntansi | `accs` | [06-akuntansi.md](06-akuntansi.md) |
| 07 | Organisasi | `branches`, `departments`, `positions` | [07-organisasi.md](07-organisasi.md) |
| 08 | Cuti & Izin | `leave_*` | [08-cuti.md](08-cuti.md) |
| 09 | Dokumen | `employee_documents`, `document_types` | [09-dokumen.md](09-dokumen.md) |
| 10 | Pajak & BPJS | `ptkp_rates`, `pph21_brackets`, `bpjs_rates`, `employee_tax_profiles` | [10-pajak-bpjs.md](10-pajak-bpjs.md) |
| 11 | Persetujuan | `approvals` | [11-persetujuan.md](11-persetujuan.md) |
| 12 | Audit Log | `audit_logs` | [12-audit-log.md](12-audit-log.md) |
| 13 | Pengaturan | `roles`, `permissions`, `approval_flows` | [13-pengaturan.md](13-pengaturan.md) |
| 14 | Portal Karyawan | `/my/*` | [14-portal-karyawan.md](14-portal-karyawan.md) |
| 15 | Dashboard & Laporan | — | [15-dashboard-laporan.md](15-dashboard-laporan.md) |

---

## Konvensi penomoran

`<MODUL>-<OPERASI>-<nomor>` — contoh `USER-C-01`, `LOAN-D-02`.

| Kode | Operasi |
|---|---|
| `C` | Create |
| `R` | Read (list & detail) |
| `U` | Update |
| `D` | Delete |
| `V` | Validasi |
| `A` | Akses / hak role |
| `X` | Operasi khusus (export, cetak, hitung ulang, dsb.) |

## Legenda status

| Kode | Arti |
|---|---|
| ✅ | Terverifikasi di level HTTP terhadap aplikasi berjalan |
| 🌐 | Terverifikasi di browser sungguhan (Chromium/Playwright) |
| ⚠️ | Defect diketahui |
| ⬜ | Belum dijalankan |

---

## Prasyarat

```bash
docker compose up -d
php artisan migrate
php artisan db:seed --class=HrisSeeder
php artisan db:seed --class=DemoDataSeeder
php artisan serve
```

Akun uji (semua password `password`): `siti@demo.test` (super_admin),
`rina@demo.test` (hr_admin), `budi@demo.test` (manager),
`ahmad@demo.test` / `dewi@demo.test` (employee).

---

## Matriks operasi CRUD per entity

Diambil dari trait operasi dan pemanggilan `denyAccess()` di tiap controller.

| Entity | Create | Read | Update | Delete | Catatan |
|---|:--:|:--:|:--:|:--:|---|
| `user` | ✔ | ✔ | ✔ | ✔ | + export, cetak ID card |
| `schedule` | ✔ | ✔ | ✔ | ✔ | + mass update |
| `presence` | ✔ | ✔ | ✔ | ✔ | + scan QR |
| `national-holiday` | ✔ | ✔ | ✔ | ✔ | ⚠️ tanpa validasi |
| `loan` | ✔ | ✔ | ✔ | ✔ | + rekap, cetak, unduh |
| `loan-payment` | ✔ | ✔ | ✔ | ✔ | |
| `salary` | ✔ | ✔ | ✔ | ✔ | |
| `salary-recap` | ✖ | ✔ | ✔ | ✔ | create **403** — dibuat oleh command |
| `company-profile` | ✔ | ✔ | ✔ | ✔ | |
| `acc` | ✔ | ✔ | ✔ | ✔ | |
| `branch` | ✔ | ✔ | ✔ | ✔ | |
| `department` | ✔ | ✔ | ✔ | ✔ | cycle guard |
| `position` | ✔ | ✔ | ✔ | ✔ | |
| `leave-type` | ✔ | ✔ | ✔ | ✔ | |
| `leave-balance` | ✔ | ✔ | ✔ | ✔ | ⚠️ tanpa validasi; + generate, carry-over |
| `leave-request` | ✖ | ✔ | ✖ | ✖ | create **404** — lewat form khusus |
| `document-type` | ✔ | ✔ | ✔ | ✔ | |
| `employee-document` | ✔ | ✔ | ✖ | ✔ | controller khusus, bukan CRUD Backpack |
| `tax-profile` | ✔ | ✔ | ✔ | ✔ | ⚠️ tanpa validasi |
| `ptkp-rate` | ✔ | ✔ | ✔ | ✔ | ⚠️ tanpa validasi |
| `pph21-bracket` | ✔ | ✔ | ✔ | ✔ | ⚠️ tanpa validasi |
| `bpjs-rate` | ✔ | ✔ | ✔ | ✔ | ⚠️ tanpa validasi |
| `approval` | ✖ | ✔ | ✖ | ✖ | approve/reject/cancel saja |
| `approval-flow` | ✔ | ✔ | ✔ | ✔ | ⚠️ tanpa validasi; super_admin saja |
| `approval-flow-step` | ✔ | ✔ | ✔ | ✔ | ⚠️ tanpa validasi; super_admin saja |
| `role` | ✔ | ✔ | ✔ | ✔ | super_admin saja |
| `permission` | ✖ | ✔ | ✖ | ✖ | read-only |
| `audit-log` | ✖ | ✔ | ✖ | ✖ | read-only |

---

## ⚠️ Defect lintas modul: 8 entity tanpa validasi server

Diverifikasi dengan mengirim form create **kosong** ke 23 entity. Delapan di
antaranya tidak punya validasi sama sekali, sehingga input kosong lolos ke
database dan memunculkan **HTTP 500 SQL mentah**, bukan pesan validasi:

| Entity | Kolom yang meledak |
|---|---|
| `national-holiday` | `date` |
| `leave-balance` | `user_id` |
| `tax-profile` | `user_id` |
| `ptkp-rate` | `year` |
| `pph21-bracket` | `year` |
| `bpjs-rate` | `year` |
| `approval-flow` | `name` |
| `approval-flow-step` | `approval_flow_id` |

Contoh galat: `SQLSTATE[HY000]: General error: 1364 Field 'date' doesn't have a default value`

Lima belas entity lain menolak dengan benar (302 kembali ke form).
Khusus `national-holiday`, penyebabnya terlihat jelas — seluruh aturan di
[NationalHolidayRequest.php](../../app/Http/Requests/NationalHolidayRequest.php)
**dikomentari**:

```php
public function rules()
{
    return [
        // 'name' => 'required|min:5|max:255'
    ];
}
```

Aturan itu pun menyebut `name`, sedangkan form sebenarnya memakai `date` dan
`info` — jadi seandainya diaktifkan kembali apa adanya, tetap salah sasaran.

Tidak ada baris sampah yang tercipta saat pengujian ini: seluruh 500 gagal di
level INSERT sehingga transaksi batal, dan jumlah baris seluruh tabel tetap
sama sebelum dan sesudah.

Rujukan defect lain: [../UI_TEST_CASES.md](../UI_TEST_CASES.md#20-defect-diketahui).
