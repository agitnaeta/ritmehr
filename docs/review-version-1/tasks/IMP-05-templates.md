# IMP-05 — View import + template Excel

**Status:** [ ] TODO — commit: `______`
**File:**
- `resources/views/admin/import/user.blade.php` (BARU)
- `resources/views/admin/import/salary.blade.php` (BARU)
- route + method `*.import.template` yang men-download template (via `UserTemplateExport` / `SalaryTemplateExport`, atau file statis di `storage/app/templates/`)
**Bagian dari:** Import Excel (menutup RV1-002, Lensa 4)
**Depends:** IMP-03, IMP-04

## Tanggung jawab
Halaman upload sederhana + tautan unduh template header yang benar. Semua label Bahasa Indonesia.

## Isi view (contoh user)
- Instruksi singkat: "Unduh template, isi, lalu unggah."
- Tombol **Unduh Template** → `route('user.import.template')`.
- Form upload `file` (accept `.xlsx,.xls,.csv`) → POST `user.import.store`.
- Setelah submit, tampilkan ringkasan hasil (berapa masuk, berapa error baris).

## Template header
- **Karyawan:** `nama, email, tgl_bergabung, departemen, cabang, jabatan, password`
- **Gaji:** `email, gaji_pokok, lembur_1x, denda_per_menit, potongan_absen`

> Header harus cocok dengan `WithHeadingRow` di IMP-01/IMP-02 (snake_case).

## Verifikasi
1. Klik "Unduh Template" → file .xlsx dgn header benar terunduh.
2. Isi template → upload → data masuk sesuai IMP-01/02.
3. Baris error dilaporkan di UI, tidak menggagalkan seluruh batch.
