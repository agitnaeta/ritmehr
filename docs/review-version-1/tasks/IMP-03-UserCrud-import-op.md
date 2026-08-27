# IMP-03 — Import Operation di UserCrudController

**Status:** [x] DONE — commit: `(uncommitted)` · form render OK (browser), end-to-end import 2 masuk/1 skip (handler asli)
**File:** `app/Http/Controllers/Admin/UserCrudController.php` (UBAH) + `routes/backpack/custom.php` (UBAH)
**Referensi desain:** [`../mockup/import.html`](../mockup/import.html)
**Bagian dari:** Import Karyawan (RV1-002) · **Depends:** IMP-01 (UserImport), IMP-05 (view+template)

## Desain
Tombol **"Import Excel"** di toolbar list Users (sebelah "User Export" yang sudah ada, baris ~134-135), hanya utk `user.create`. Klik → layar import (mockup) dengan alur pratinjau→konfirmasi.

## Perubahan
1. Route (`custom.php`, dekat `Route::crud('user',...)`):
```php
Route::get ('user/import',          [UserCrudController::class,'importForm'])->name('user.import.form');
Route::get ('user/import/template', [UserCrudController::class,'importTemplate'])->name('user.import.template');
Route::post('user/import/preview',  [UserCrudController::class,'importPreview'])->name('user.import.preview');
Route::post('user/import',          [UserCrudController::class,'importStore'])->name('user.import.store');
```
2. Tombol di `setupListOperation()` — pola sama `addButtonFromView('top',...)` seperti user_export.
3. Method:
```php
public function importForm()     { $this->crud->hasAccessOrFail('create');
                                   return view('admin.import.user'); }
public function importTemplate() { return \Maatwebsite\Excel\Facades\Excel::download(
                                       new \App\Exports\UserTemplateExport, 'template-karyawan.xlsx'); }
public function importPreview(Request $r) { $this->crud->hasAccessOrFail('create');
                                   $r->validate(['file'=>'required|file|mimes:xlsx,xls,csv']);
                                   // parse tanpa commit → kirim rows+errors ke view fase 3
                                }
public function importStore(Request $r) { $this->crud->hasAccessOrFail('create');
                                   \Maatwebsite\Excel\Facades\Excel::import(new \App\Imports\UserImport, $r->file('file'));
                                   \Prologue\Alerts\Facades\Alert::success('Karyawan berhasil diimpor.')->flash();
                                   return redirect($this->crud->route); }
```

## Cek per file (verifikasi)
- [ ] Tombol "Import Excel" tampil utk super_admin/HR; **tidak** utk manager/employee.
- [ ] manager POST `user/import` → 403 (RBAC dipertahankan).
- [ ] Upload template terisi → karyawan bertambah di list.
- [ ] Regresi `crud-suite.mjs` (Users 3/3) tetap hijau.
