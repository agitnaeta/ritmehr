# QW-02 — Tabel Gaji: format ribuan + kolom nilai tak ter-collapse

**Status:** [x] DONE — commit: `(uncommitted, terverifikasi)`
**File:** `app/Http/Controllers/Admin/SalaryCrudController.php`
**Menutup:** RV1-004 (🟡 currency tanpa ribuan) + RV1-003 (🟡 kolom nilai tersembunyi) · Prioritas P1
**Bukti bug:** `../screenshots/03-salary-list.png`

## Masalah
Di `setupListOperation()` (sekitar baris 185–202) kolom nilai hanya diberi `->prefix($cur)`:
- Nilai tampil mentah "Rp12031000" tanpa pemisah ribuan (RV1-004).
- Kolom "Gaji" ikut ter-collapse oleh responsive DataTables di viewport sedang, sehingga hanya "Nama Karyawan" terlihat (RV1-003).

## Perubahan
Pada loop kolom (baris ~197–202), untuk kolom bernilai uang (`amount`, `overtime_amount`, `fine_per_minute`, `fine`, `unpaid_leave_deduction`, `extra_time`) tambahkan tipe number + format ribuan, dan **pin** kolom "Gaji" (`amount`) agar tak pernah di-collapse.

```php
// contoh untuk kolom amount ("Gaji") — jadikan priority tertinggi + format ribuan
$this->crud->column('amount')
    ->label('Gaji')
    ->type('number')
    ->decimals(0)
    ->dec_point(',')
    ->thousands_sep('.')
    ->prefix($cur . ' ')
    ->priority(0);   // 0 = jangan pernah collapse
```
Terapkan `->type('number')->decimals(0)->dec_point(',')->thousands_sep('.')` ke semua kolom uang.
Untuk kolom non-esensial (Denda Per-Menit, dll) beri `->priority()` angka besar supaya itu yang collapse duluan, bukan kolom Gaji.

> Alternatif jika `->thousands_sep()` tak tersedia di versi Backpack: pakai `->type('closure')->function(fn($e)=>$cur.' '.number_format($e->amount,0,',','.'))`.

## Verifikasi
1. `/admin/salary` pada viewport 1440px: kolom **Gaji terlihat** tanpa expand baris.
2. Nilai tampil **"Rp 12.031.000"** (ada pemisah ribuan), konsisten dgn dashboard.
3. Regresi: `node tests/browser/crud-suite.mjs` (Penggajian 4/4) + `phpunit` tetap hijau.
