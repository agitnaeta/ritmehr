# WIZ-01 — Route Setup Wizard

**Status:** [ ] TODO — commit: `______`
**File:** `routes/backpack/custom.php` (UBAH)
**Referensi desain:** [`../mockup/setup-wizard.html`](../mockup/setup-wizard.html)
**Bagian dari:** Setup Wizard (RV1-001) · **Depends:** WIZ-02

## Perubahan
Di group admin `custom.php` (setelah `Route::crud('user',...)`, ~baris 34):
```php
use App\Http\Controllers\Admin\SetupWizardController;

Route::get ('setup',            [SetupWizardController::class,'index'])->name('setup.index');
Route::get ('setup/{step}',     [SetupWizardController::class,'step'])->name('setup.step')
     ->whereIn('step', ['company','orgunit','admin','import']);
Route::post('setup/{step}',     [SetupWizardController::class,'save'])->name('setup.save');
Route::post('setup/finish',     [SetupWizardController::class,'finish'])->name('setup.finish');
```

## Cek per file (verifikasi)
- [ ] `php artisan route:list --path=setup` → 4 route terdaftar, guard `admin`.
- [ ] `/admin/setup` redirect ke `/admin/setup/company` (bukan 404).
- [ ] Regresi `node tests/browser/crud-suite.mjs` tetap 146/146 (route baru tak sentuh RBAC lama).
