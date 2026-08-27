# IMP-05 — View import + template Excel (baru)

**Status:** [x] DONE — commit: `(uncommitted)` · template xlsx ter-generate, 3 blade + 2 export
**Referensi desain:** [`../mockup/import.html`](../mockup/import.html) · bukti: `../mockup/preview-import-upload.png`, `../mockup/preview-import-preview.png`
**Bagian dari:** Import Excel (RV1-002, Lensa 4) · **Depends:** IMP-03, IMP-04

## Desain yang disepakati (dari mockup)
Satu layar dengan **step-line 4 fase**: `1 Unduh template · 2 Unggah file · 3 Pratinjau & validasi · 4 Hasil`.
- **Fase 1+2 (satu layar):** kartu biru "Belum punya file? → Unduh Template", lalu dropzone/input file `.xlsx/.xls/.csv` + catatan kolom.
- **Fase 3 (pratinjau):** alert oranye ringkas ("N baris terbaca. X valid · Y bermasalah"), tabel dgn kolom **Status** (badge ok hijau / error merah) + baris error di-highlight merah, toggle **"Lewati baris bermasalah"**, tombol hijau **"Impor N baris valid"**.
- **Fase 4 (hasil):** empty-state ✅ "Impor selesai", ringkasan masuk/dilewati + daftar baris error, tombol "Lihat daftar" & "Impor lagi".

## File yang dibuat
| File | Isi |
|---|---|
| `resources/views/admin/import/user.blade.php` | Layar import Karyawan (fase 1-4 di atas), form ke `user.import.*`. |
| `resources/views/admin/import/salary.blade.php` | Sama, entity Gaji, form ke `salary.import.*`. |
| `resources/views/admin/import/_partials/preview-table.blade.php` | Tabel pratinjau reusable (Status + kolom dinamis + row-highlight). |
| `app/Exports/UserTemplateExport.php` | Template kosong header karyawan. |
| `app/Exports/SalaryTemplateExport.php` | Template kosong header gaji. |

## Template header (cocokkan dgn WithHeadingRow IMP-01/02)
- **Karyawan:** `nama, email, tgl_bergabung, departemen, cabang, jabatan, password`
- **Gaji:** `email, gaji_pokok, lembur_1x, denda_per_menit, potongan_absen`

## Alur pratinjau (2 langkah submit)
1. POST file → controller parse (belum commit) → tampilkan fase 3 dgn hasil validasi.
2. Konfirmasi "Impor" → controller jalankan `Excel::import` sungguhan → fase 4.

## Cek per file (verifikasi)
- [ ] "Unduh Template" → .xlsx header benar terunduh (user & salary).
- [ ] Upload file → fase 3 menampilkan tabel valid/error sesuai isi.
- [ ] Konfirmasi → data masuk DB, fase 4 menampilkan ringkasan + baris dilewati.
- [ ] Tampilan cocok dgn mockup (badge, highlight, toggle).
