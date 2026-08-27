# IMP-03 — Tombol Import di UserCrudController

**Status:** [ ] TODO — commit: `______`
**File:** `app/Http/Controllers/Admin/UserCrudController.php` (UBAH)
**Bagian dari:** Import Excel Karyawan (menutup RV1-002, Lensa 4)
**Depends:** IMP-01 (UserImport), IMP-05 (template & view)

## Perubahan
1. Tambah dua route custom (via `setupRoutes` atau di `routes/backpack/custom.php` dekat `Route::crud('user',...)`):
   ```php
   Route::get('user/import',  [UserCrudController::class, 'importForm'])->name('user.import.form');
   Route::post('user/import', [UserCrudController::class, 'importStore'])->name('user.import.store');
   ```
2. Tambah tombol "Import Excel" di list (mirip tombol "User Export" yang sudah ada) — hanya utk `user.create`.
3. Method controller:
   ```php
   public function importForm()  { $this->crud->hasAccessOrFail('create');
                                   return view('admin.import.user', ['template'=>route('user.import.template')]); }
   public function importStore(Request $r) {
       $this->crud->hasAccessOrFail('create');
       $r->validate(['file' => 'required|file|mimes:xlsx,xls,csv']);
       $import = new \App\Imports\UserImport();
       \Maatwebsite\Excel\Facades\Excel::import($import, $r->file('file'));
       Alert::success('Karyawan berhasil diimpor.')->flash();
       return redirect($this->crud->route);
   }
   ```

## Verifikasi
1. `/admin/user` menampilkan tombol "Import Excel" utk super_admin/HR; **tidak** utk manager/employee.
2. Upload template terisi → karyawan bertambah di list.
3. Regresi: `crud-suite.mjs` (Users 3/3, RBAC 403 manager) tetap hijau.
