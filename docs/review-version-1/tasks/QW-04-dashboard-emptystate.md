# QW-04 — Empty-state / "mulai di sini" pada dashboard

**Status:** [x] DONE — commit: `(uncommitted, terverifikasi)`
**File utama:** `resources/views/admin/dashboard.blade.php`
**File pendukung:** `app/Services/DashboardService.php` (tambah flag kondisi kosong)
**Menutup:** Lensa 1 (Paham di tahap awal) · Prioritas P1

## Masalah
Instance baru (0 karyawan / 0 rekap) menampilkan banyak "0" dan "Rp 0" tanpa arahan. User baru tak tahu langkah pertama.

## Perubahan
1. Di `DashboardService` (dipakai `DashboardController@index`, lihat baris 18–20), tambah data:
   ```php
   'needsOnboarding' => \App\Models\User::count() <= 1,   // hanya admin
   'onboardingSteps' => [
       ['label'=>'Lengkapi Profil Perusahaan','done'=>\App\Models\CompanyProfile::exists(),'url'=>backpack_url('company-profile')],
       ['label'=>'Tambah Departemen & Cabang','done'=>\App\Models\Department::exists(),'url'=>backpack_url('department')],
       ['label'=>'Tambah / Import Karyawan','done'=>\App\Models\User::count()>1,'url'=>backpack_url('user')],
       ['label'=>'Atur Struktur Gaji','done'=>\App\Models\Salary::exists(),'url'=>backpack_url('salary')],
   ],
   ```
2. Di `dashboard.blade.php`, sebelum kartu KPI, render kartu checklist **hanya jika** `$needsOnboarding` true — tampilkan langkah dgn status ✓/○ + tombol menuju masing-masing (link ke Setup Wizard bila WIZ-* sudah ada).

## Verifikasi
1. Kosongkan DB (fresh) → dashboard menampilkan kartu "Mulai di sini" + langkah.
2. Setelah data terisi (`DemoDataSeeder`) → kartu hilang, dashboard normal.
3. Tambah browser test: assert kartu muncul saat 0 karyawan, hilang saat ada data.
4. Regresi `phpunit` tetap hijau.
