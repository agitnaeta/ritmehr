# WIZ-03 — OnboardingService (baru)

**Status:** [ ] TODO — commit: `______`
**File:** `app/Services/OnboardingService.php` (BARU)
**Bagian dari:** Setup Wizard (menutup RV1-001, Lensa 2)

## Tanggung jawab
Logika bisnis wizard: validasi tiap step, **merge langsung ke DB** (tanpa tabel perantara/tombol import manual — sesuai preferensi Capt), progres, dan penanda selesai.

## Kontrak method
```php
namespace App\Services;

class OnboardingService
{
    /** data + state utk render satu step */
    public function context(string $step): array;

    /** validasi + simpan step ke DB (CompanyProfile / Department+Branch / User admin / import) */
    public function save(string $step, array $input): void;

    /** step berikutnya atau null bila terakhir */
    public function nextStep(string $step): ?string;

    /** true bila setup sudah pernah diselesaikan (baca settings) */
    public function isComplete(): bool;

    /** tandai selesai (settings key 'onboarding_complete' = true) */
    public function markComplete(): void;
}
```
- Step `company` → upsert `CompanyProfile`.
- Step `orgunit` → buat `Department` + `Branch`.
- Step `admin` → lengkapi profil admin / buat HR user.
- Step `import` → panggil `UserImport` (IMP-01) bila file diunggah, else skip.
- Simpan flag di tabel `settings` (sudah ada) supaya QW-04 & middleware bisa cek.

## Verifikasi
1. Unit test `OnboardingServiceTest`: tiap `save()` menulis row DB yang benar.
2. `isComplete()` true setelah `markComplete()`.
3. `phpunit` hijau.
