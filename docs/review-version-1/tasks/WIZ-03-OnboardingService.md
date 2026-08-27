# WIZ-03 — OnboardingService (baru)

**Status:** [ ] TODO — commit: `______`
**File:** `app/Services/OnboardingService.php` (BARU)
**Referensi desain:** [`../mockup/setup-wizard.html`](../mockup/setup-wizard.html)
**Bagian dari:** Setup Wizard (RV1-001)

## Tanggung jawab
Validasi + **merge langsung ke DB** per step (tanpa tabel perantara — preferensi Capt), sediakan data untuk render, dan penanda selesai (tabel `settings`).

## Field aktual (dari skema DB — pakai ini persis)
- `company_profiles`: `name, address, phone, email, image`
- `departments`: `name, code, parent_id, head_user_id`
- `branches`: `company_profile_id, name, code, address, phone, is_active`
- admin = update `users` row yang login (name, email, department_id); opsi buat HR user baru.

## Kontrak
```php
namespace App\Services;

class OnboardingService
{
    public function context(string $step): array;   // data utk view (mis. daftar dept utk select admin)
    public function save(string $step, array $in): void;
    public function isComplete(): bool;              // settings 'onboarding_complete'
    public function markComplete(): void;

    // internal per step:
    // company → CompanyProfile::updateOrCreate([], [name,address,phone,email])
    // orgunit → validasi min 1; Department::firstOrCreate(name), Branch::firstOrCreate(name, company_profile_id)
    // admin   → backpack_user()->update(name,email,department_id); if buat_hr → User::create + assignRole('hr_admin')
    // import  → jika ada file → Excel::import(new UserImport, file); else skip
}
```

## Validasi per step (rules)
- company: `name required`
- orgunit: minimal satu departemen & satu cabang non-kosong
- admin: `name required`, `email required|email`
- import: `file nullable|file|mimes:xlsx,xls,csv`

## Cek per file (verifikasi)
- [ ] Unit test `OnboardingServiceTest`: tiap `save()` menulis row DB benar (company/dept/branch/user).
- [ ] `orgunit` menolak bila semua input kosong.
- [ ] `isComplete()` false → true setelah `markComplete()`.
- [ ] `phpunit` hijau.
