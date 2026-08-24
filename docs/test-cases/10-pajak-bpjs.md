# Modul 10 — Pajak & BPJS

Dropdown **Pajak & BPJS**: Profil Pajak Karyawan, Rekap Pajak Tahunan,
Rekap BPJS Bulanan, Tarif PTKP, Lapisan PPh 21, Tarif BPJS.

| | |
|---|---|
| **Service** | [TaxService](../../app/Services/TaxService.php) |
| **Tabel** | `employee_tax_profiles`, `ptkp_rates`, `pph21_brackets`, `bpjs_rates` |

> ⚠️ **Verifikasi tarif hasil seed terhadap regulasi terbaru sebelum payroll
> sungguhan.** `TaxRateSeeder` mengacu PMK 101/2016 dan UU HPP No. 7/2021 pada
> saat ditulis, dan JKK diisi kelas risiko terendah (0,24%).

Tarif **tidak di-hardcode** — PTKP, lapisan, dan persentase BPJS disimpan per
tahun, karena pemerintah merevisinya dan perhitungan ulang historis harus
memakai tarif periodenya sendiri.

> ⚠️ **Keempat entity CRUD di modul ini tanpa validasi server.** Submit kosong
> menghasilkan HTTP 500 SQL mentah. Lihat baris `-V-01` masing-masing.

---

## 10.1 Profil Pajak Karyawan — `/admin/tax-profile`

**Field:** `user_id`, `npwp`, `tax_status`, `tax_method`, `bpjs_kesehatan`,
`bpjs_ketenagakerjaan`, `bpjs_tk_jht`, `bpjs_tk_jkk`, `bpjs_tk_jkm`, `bpjs_tk_jp`

| ID | Skenario | Data | Expected | Status |
|---|---|---|---|---|
| TXP-C-01 | Buka form | — | Form 10 field | ✅ 200 |
| TXP-C-02 | Profil lengkap | User + NPWP + status PTKP | Tersimpan | ⬜ |
| TXP-C-03 | Tanpa NPWP | Kosongkan `npwp` | Tersimpan; kena surcharge 20% saat hitung PPh 21 | ⬜ |
| TXP-R-01 | List | Buka list | 5 profil pada data demo | ⬜ |
| TXP-U-01 | Ubah status PTKP | TK/0 → K/2 | PTKP berubah, PPh 21 menyesuaikan | ⬜ |
| TXP-U-02 | Matikan BPJS Kesehatan | `bpjs_kesehatan=0` | Iuran Kesehatan tidak dipotong | ⬜ |
| TXP-U-03 | Matikan JP | `bpjs_tk_jp=0` | Iuran JP tidak dipotong; pengurang PPh 21 ikut berubah | ⬜ |
| TXP-U-04 | Tambahkan NPWP | Isi NPWP pada yang sebelumnya kosong | Surcharge 20% hilang | ⬜ |
| TXP-D-01 | Hapus profil | Delete | Perhitungan pajak karyawan itu memakai default, tidak crash | ⬜ |
| TXP-V-01 | **Submit kosong** | Semua kosong | ⚠️ **GAGAL — 500** `Field 'user_id' doesn't have a default value` | ✅ ⚠️ |
| TXP-V-02 | Profil ganda | Dua profil untuk user yang sama | Ditolak — satu profil per karyawan | ⬜ |
| TXP-V-03 | Format NPWP salah | `npwp=abc` | Ditolak atau dinormalkan | ⬜ |
| TXP-V-04 | Status PTKP tidak dikenal | `tax_status=XX/9` | Ditolak | ⬜ |

---

## 10.2 Tarif PTKP — `/admin/ptkp-rate`

**Field:** `year`, `status`, `amount`

| ID | Skenario | Data | Expected | Status |
|---|---|---|---|---|
| PTK-C-01 | Buka form | — | Form 3 field | ✅ 200 |
| PTK-C-02 | Tambah tarif | 2027, TK/0, nominal | Tersimpan | ⬜ |
| PTK-R-01 | List | Buka list | 12 baris pada data demo | ⬜ |
| PTK-U-01 | Revisi nominal | Ubah `amount` tahun berjalan | Perhitungan berikutnya memakai nilai baru | ⬜ |
| PTK-D-01 | Hapus tarif | Delete | Perhitungan jatuh ke tahun terbit terakhir | ⬜ |
| PTK-V-01 | **Submit kosong** | Semua kosong | ⚠️ **GAGAL — 500** `Field 'year' doesn't have a default value` | ✅ ⚠️ |
| PTK-V-02 | Duplikat tahun+status | Kombinasi yang sudah ada | Ditolak | ⬜ |
| PTK-V-03 | Tahun tidak masuk akal | `year=99` | Ditolak | ⬜ |
| PTK-V-04 | Nominal negatif | `amount=-1000` | Ditolak | ⬜ |

## 10.3 Lapisan PPh 21 — `/admin/pph21-bracket`

**Field:** `year`, `lower_bound`, `upper_bound`, `rate`

| ID | Skenario | Data | Expected | Status |
|---|---|---|---|---|
| PPH-C-01 | Buka form | — | Form 4 field | ✅ 200 |
| PPH-C-02 | Tambah lapisan | 2027, 0–60 juta, 5% | Tersimpan | ⬜ |
| PPH-C-03 | Lapisan teratas | `upper_bound` kosong = tanpa batas | Tersimpan sebagai lapisan tertinggi | ⬜ |
| PPH-R-01 | List | Buka list | 5 lapisan pada data demo | ⬜ |
| PPH-U-01 | Ubah tarif | 5% → 6% | Perhitungan berikutnya menyesuaikan | ⬜ |
| PPH-D-01 | Hapus lapisan tengah | Delete | ⚠️ Menimbulkan celah rentang — pastikan terdeteksi | ⬜ |
| PPH-V-01 | **Submit kosong** | Semua kosong | ⚠️ **GAGAL — 500** `Field 'year' doesn't have a default value` | ✅ ⚠️ |
| PPH-V-02 | **Rentang tumpang tindih** | Dua lapisan yang beririsan | Ditolak atau perilaku terdefinisi | ⬜ |
| PPH-V-03 | Celah rentang | Lapisan tidak bersambung | Terdeteksi | ⬜ |
| PPH-V-04 | Batas bawah > batas atas | `lower=100`, `upper=50` | Ditolak | ⬜ |
| PPH-V-05 | Tarif > 100% | `rate=150` | Ditolak | ⬜ |

## 10.4 Tarif BPJS — `/admin/bpjs-rate`

**Field:** `year`, `type`, `employee_rate`, `employer_rate`, `max_salary`

| ID | Skenario | Data | Expected | Status |
|---|---|---|---|---|
| BPJ-C-01 | Buka form | — | Form 5 field | ✅ 200 |
| BPJ-C-02 | Tambah tarif Kesehatan | 1% karyawan / 4% pemberi kerja, plafon 12.000.000 | Tersimpan | ⬜ |
| BPJ-C-03 | Tarif tanpa plafon | `max_salary` kosong (JHT) | Tersimpan; iuran dihitung dari gaji penuh | ⬜ |
| BPJ-R-01 | List | Buka list | 5 tarif pada data demo | ⬜ |
| BPJ-U-01 | Ubah plafon | Naikkan `max_salary` | Iuran gaji tinggi menyesuaikan | ⬜ |
| BPJ-U-02 | Ubah persentase | Ubah `employee_rate` | Potongan berikutnya menyesuaikan | ⬜ |
| BPJ-D-01 | Hapus tarif | Delete | Jatuh ke tahun terbit terakhir | ⬜ |
| BPJ-V-01 | **Submit kosong** | Semua kosong | ⚠️ **GAGAL — 500** `Field 'year' doesn't have a default value` | ✅ ⚠️ |
| BPJ-V-02 | Duplikat tahun+tipe | Kombinasi yang sudah ada | Ditolak | ⬜ |
| BPJ-V-03 | Persentase negatif | `employee_rate=-1` | Ditolak | ⬜ |
| BPJ-V-04 | Tipe tidak dikenal | `type=XYZ` | Ditolak | ⬜ |

---

## 10.5 Perhitungan — TaxService

### BPJS

| ID | Skenario | Kondisi | Expected | Status |
|---|---|---|---|---|
| TAX-X-01 | Kesehatan normal | Gaji 8.000.000 | 1% karyawan / 4% pemberi kerja dari 8.000.000 | ⬜ |
| TAX-X-02 | **Kesehatan kena plafon** | Gaji 20.000.000 | Dihitung dari **12.000.000**, bukan 20.000.000 | ⬜ |
| TAX-X-03 | **JHT tanpa plafon** | Gaji 20.000.000 | 2% / 3,7% dari gaji penuh | ⬜ |
| TAX-X-04 | **JP kena plafon** | Gaji 15.000.000 | Dihitung dari **10.042.300** | ⬜ |
| TAX-X-05 | JKK & JKM | Semua gaji | **Ditanggung pemberi kerja saja**, tidak memotong karyawan | ⬜ |

### PPh 21

| ID | Skenario | Kondisi | Expected | Status |
|---|---|---|---|---|
| TAX-X-06 | Alur anualisasi | Gaji bulanan | bruto × 12 → − biaya jabatan → − JHT+JP karyawan → − PTKP → lapisan progresif → ÷ 12 | ⬜ |
| TAX-X-07 | **Biaya jabatan dibatasi** | Gaji tinggi | 5% dengan **plafon 6.000.000/tahun** | ⬜ |
| TAX-X-08 | **Surcharge tanpa NPWP** | `npwp` kosong | PPh 21 ditambah **20%** | ⬜ |
| TAX-X-09 | Di bawah PTKP | Gaji kecil | PPh 21 = 0, tidak negatif | ⬜ |
| TAX-X-10 | Lintas lapisan | Gaji yang melewati beberapa lapisan | Progresif, bukan tarif tunggal | ⬜ |

### THR

| ID | Skenario | Masa kerja | Expected | Status |
|---|---|---|---|---|
| TAX-X-11 | ≥ 12 bulan | 18 bulan | **Satu bulan penuh** | ⬜ |
| TAX-X-12 | Antara 1–12 bulan | 6 bulan | **Prorata** | ⬜ |
| TAX-X-13 | < 1 bulan | 20 hari | **Tidak dapat** | ⬜ |

### Tarif per tahun

| ID | Skenario | Langkah | Expected | Status |
|---|---|---|---|---|
| TAX-X-14 | Tarif historis | Hitung ulang periode tahun lalu | Memakai tarif **tahun itu**, bukan tahun berjalan | ⬜ |
| TAX-X-15 | **Tahun belum ada tarifnya** | Hitung untuk tahun tanpa data tarif | Memakai **tahun terbit terakhir**, bukan menghasilkan nol | ⬜ |

---

## 10.6 Laporan

| ID | Skenario | Langkah | Expected | Status |
|---|---|---|---|---|
| TAX-R-01 | Rekap pajak tahunan | `/admin/tax-report/annual` | 200; dasar SPT: bruto, biaya jabatan, PTKP, PPh 21 | ✅ |
| TAX-R-02 | Rekap BPJS bulanan | `/admin/tax-report/bpjs` | 200; rincian Kesehatan / JHT / JP / JKK / JKM | ✅ |
| TAX-R-03 | Hitung ulang | `POST /admin/tax-report/recalculate` | Angka diperbarui memakai tarif tahun bersangkutan | ⬜ |
| TAX-R-04 | Konsistensi | Bandingkan rekap vs kolom statutori di `salary_recaps` | Angka cocok | ⬜ |

## AKSES

| ID | Role | Expected | Status |
|---|---|---|---|
| TAX-A-01 | SA / HR | Akses penuh | ✅ 200 |
| TAX-A-02 | MGR | ⚠️ **Tidak punya permission pajak apa pun**, tetapi seluruh halaman & form terbuka — termasuk mengubah tarif PTKP dan BPJS (DEF-03) | ⚠️ |
| TAX-A-03 | EMP | Dialihkan ke `/my` | 🌐 |
