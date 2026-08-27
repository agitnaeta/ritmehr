# IMP-04 — Tombol Import di SalaryCrudController

**Status:** [ ] TODO — commit: `______`
**File:** `app/Http/Controllers/Admin/SalaryCrudController.php` (UBAH)
**Bagian dari:** Import Excel Gaji (menutup RV1-002, Lensa 4)
**Depends:** IMP-02 (SalaryImport), IMP-05 (template & view)

## Perubahan
Pola sama seperti IMP-03, untuk entity salary:
1. Route:
   ```php
   Route::get('salary/import',  [SalaryCrudController::class, 'importForm'])->name('salary.import.form');
   Route::post('salary/import', [SalaryCrudController::class, 'importStore'])->name('salary.import.store');
   ```
2. Tombol "Import Excel" di list salary — hanya utk permission tulis gaji.
3. Method:
   ```php
   public function importStore(Request $r) {
       $this->crud->hasAccessOrFail('create');
       $r->validate(['file' => 'required|file|mimes:xlsx,xls,csv']);
       \Maatwebsite\Excel\Facades\Excel::import(new \App\Imports\SalaryImport(), $r->file('file'));
       \Prologue\Alerts\Facades\Alert::success('Struktur gaji berhasil diimpor.')->flash();
       return redirect($this->crud->route);
   }
   ```

## Verifikasi
1. `/admin/salary` menampilkan tombol Import utk role berwenang; manager ditolak (403).
2. Upload template → row gaji ter-upsert, total `amount` ter-recalc otomatis.
3. Regresi: `crud-suite.mjs` (Penggajian 4/4) + `phpunit` hijau.
