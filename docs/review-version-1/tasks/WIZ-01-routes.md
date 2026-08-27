# WIZ-01 — Daftarkan route Setup Wizard

**Status:** [ ] TODO — commit: `______`
**File:** `routes/backpack/custom.php`
**Bagian dari:** Setup Wizard (menutup RV1-001, Lensa 2)
**Depends:** WIZ-02 (controller harus ada)

## Perubahan
Di dalam group admin (`prefix admin`, middleware admin) — sekitar baris 33 setelah `Route::crud('user', ...)` — tambahkan:

```php
use App\Http\Controllers\Admin\SetupWizardController;

Route::get('setup',            [SetupWizardController::class, 'index'])->name('setup.index');
Route::get('setup/{step}',     [SetupWizardController::class, 'step'])->name('setup.step');
Route::post('setup/{step}',    [SetupWizardController::class, 'save'])->name('setup.save');
Route::post('setup/finish',    [SetupWizardController::class, 'finish'])->name('setup.finish');
```
`{step}` = `company|orgunit|admin|import`.

## Verifikasi
1. `php artisan route:list --path=setup` menampilkan 4 route.
2. Buka `/admin/setup` sebagai super_admin → tidak 404.
3. Regresi `crud-suite.mjs` tetap 146 hijau (route baru tak ganggu RBAC existing).
