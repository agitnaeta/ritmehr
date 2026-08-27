# IMP-04 — Import Operation di SalaryCrudController

**Status:** [x] DONE — commit: `(uncommitted)` · end-to-end: basic 7.5jt→8.25jt, amount auto-recalc, unmatched email dikumpulkan
**File:** `app/Http/Controllers/Admin/SalaryCrudController.php` (UBAH) + `routes/backpack/custom.php` (UBAH)
**Referensi desain:** [`../mockup/import.html`](../mockup/import.html) (tab "Import Gaji")
**Bagian dari:** Import Gaji (RV1-002) · **Depends:** IMP-02 (SalaryImport), IMP-05 (view+template)

## Desain
Pola identik IMP-03, entity Gaji. Tombol "Import Excel" di toolbar list `/admin/salary`, hanya utk role tulis gaji. Alur pratinjau→konfirmasi (mockup tab "Import Gaji").

## Perubahan
1. Route (`custom.php`):
```php
Route::get ('salary/import',          [SalaryCrudController::class,'importForm'])->name('salary.import.form');
Route::get ('salary/import/template', [SalaryCrudController::class,'importTemplate'])->name('salary.import.template');
Route::post('salary/import/preview',  [SalaryCrudController::class,'importPreview'])->name('salary.import.preview');
Route::post('salary/import',          [SalaryCrudController::class,'importStore'])->name('salary.import.store');
```
2. Tombol di `setupListOperation()` (dekat blok kolom yg baru diformat QW-02).
3. Method `importStore`:
```php
public function importStore(Request $r) {
    $this->crud->hasAccessOrFail('create');
    $r->validate(['file'=>'required|file|mimes:xlsx,xls,csv']);
    \Maatwebsite\Excel\Facades\Excel::import(new \App\Imports\SalaryImport, $r->file('file'));
    \Prologue\Alerts\Facades\Alert::success('Struktur gaji berhasil diimpor.')->flash();
    return redirect($this->crud->route);
}
```
> `amount` (total) di-recalc otomatis oleh observer M20; import hanya set `basic_salary` dkk.

## Cek per file (verifikasi)
- [ ] Tombol Import tampil utk role berwenang; manager → 403.
- [ ] Upload template → row `salaries` ter-upsert by email; total ter-recalc.
- [ ] Format ribuan QW-02 tetap tampil benar setelah import.
- [ ] Regresi `crud-suite.mjs` (Penggajian 4/4) + `phpunit` hijau.
