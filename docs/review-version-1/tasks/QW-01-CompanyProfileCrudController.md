# QW-01 — Perbaiki typo "Pofile Perusahaan"

**Status:** [x] DONE — commit: `(uncommitted, terverifikasi)`
**File:** `app/Http/Controllers/Admin/CompanyProfileCrudController.php`
**Menutup:** RV1-006 (🟢 Rendah) · Prioritas P2
**Bukti bug:** `../screenshots/05-company-profile-typo.png`

## Masalah
Baris 34 memberi nama entity dengan typo → tampil di heading H1, `<title>`, dan breadcrumb sebagai "Pofile Perusahaan". PUEBI: "profil" (bukan "profile").

## Perubahan (1 baris)
```php
// SEBELUM (line 34)
CRUD::setEntityNameStrings('Profile Perusahaan', 'Pofile Perusahaan');

// SESUDAH
CRUD::setEntityNameStrings('Profil Perusahaan', 'Profil Perusahaan');
```

## Verifikasi
1. `php artisan serve`, login `siti@demo.test`, buka `/admin/company-profile`.
2. Heading, tab title, dan breadcrumb harus baca **"Profil Perusahaan"**.
3. Regresi tetap hijau: `node tests/browser/crud-suite.mjs` (company-profile 1/1).
