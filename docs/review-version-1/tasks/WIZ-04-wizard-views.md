# WIZ-04 — View wizard (baru)

**Status:** [ ] TODO — commit: `______`
**File:** `resources/views/admin/setup/` (BARU)
- `company.blade.php`
- `orgunit.blade.php`
- `admin.blade.php`
- `import.blade.php`
- `_layout.blade.php` (progress bar 4 langkah, dipakai bersama)
**Bagian dari:** Setup Wizard (menutup RV1-001, Lensa 2)
**Depends:** WIZ-02

## Tanggung jawab
UI langkah-per-langkah dengan progress indicator. Ikuti gaya Backpack (extend layout backpack) supaya konsisten. Semua label **Bahasa Indonesia**.

## Isi tiap view
- `_layout`: stepper 1–4 (Perusahaan · Struktur · Admin · Import) dengan highlight step aktif; slot konten.
- `company`: form nama perusahaan, alamat, telepon, logo → POST `setup.save` step `company`.
- `orgunit`: form tambah Departemen + Cabang (boleh multi-row) → step `orgunit`.
- `admin`: konfirmasi/lengkapi profil admin + opsi buat user HR → step `admin`.
- `import`: upload Excel karyawan (link unduh template dari IMP-05) atau tombol "Lewati" → step `import` lalu `setup.finish`.

## Verifikasi
1. Tiap step render tanpa error, tombol Lanjut/Kembali berfungsi.
2. Progress bar menandai step aktif dgn benar.
3. Tak ada teks Inggris yang bocor ke UI.
