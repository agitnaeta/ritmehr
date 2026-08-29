# UM-10 — Export skala besar via background/queue

**Poin Capt #9** · Tipe: Struktural · Urgensi: Sedang · Risiko: Sedang
**Status:** [x] DONE — export SELALU background (queue) + halaman status + unduh, retensi 24 jam. Keamanan (scope visibleTo) sudah dari B2.
Test UserExportBackgroundTest (5 PASS) + regresi 64 PASS.

---

## Konteks
Export sinkron dalam request. Meski `UserExport` sudah `WithChunkReading` (hemat
memori), dataset sangat besar tetap memblokir request sampai file jadi.

## Akar masalah (terverifikasi)
- `UserCrudController::export()` (`:422-424`) `Excel::download(new UserExport)` **sinkron**.

## 🔴 Temuan keamanan (WAJIB diperbaiki bareng task ini)
- Route `GET /admin/user/export` (`custom.php:62`) **tanpa middleware `permission:`**,
  dan `export()` **tak memanggil `hasAccessOrFail()`**. `setup()` (guard `user.view` +
  scope `visibleTo`) TIDAK jalan untuk route custom → **manager bisa mengunduh SELURUH
  karyawan** (`UserExport::query()` = `User::query()`, tanpa `visibleTo`), kebal permission.
- **Fix wajib:**
  1. Di `export()`: `$this->crud->hasAccessOrFail('list')` atau cek
     `abort_unless(backpack_user()?->can('user.view'), 403)` di awal.
  2. `UserExport` terima scope: query `User::query()->visibleTo(backpack_user())`
     (inject viewer ke constructor export) — manager hanya dapat bawahannya.
  3. (defense in depth) pasang `->middleware('permission:user.view')` pada route/group.
  Lihat `audit-plan.md` §B2.

> ⚠️ **Prasyarat infra queue** sama dengan UM-09 (driver `sync`, tabel `jobs` belum ada,
> `ExtractCvJob` jalan inline). Baca section "Realita Infra" di UM-09 sebelum eksekusi.

## Rencana solusi (desain)
1. **Queued export** — `(new UserExport)->queue('exports/user-{token}.xlsx', 'local')`
   (Maatwebsite queued export, sudah `WithChunkReading`) → worker menulis file ke storage.
2. **Tabel/status** (reuse pola `import_jobs` dari UM-09 → generalisasi jadi
   `export_jobs` atau satu `batch_jobs`): status queued/processing/done, file_path.
3. **UI**: klik "User Export" → jika data > ambang (mis. 2000) → mode background:
   tampilkan status + polling → saat selesai muncul tombol **Unduh**. Data kecil →
   tetap unduh langsung (sinkron) agar UX cepat.
4. **Endpoint unduh**: `GET /admin/user/export/{job}/download` — stream file dari
   storage, scoped ke pembuat/HR, hapus/expire setelah diunduh (atau cron cleanup).

File yang disentuh (rencana):
- `app/Exports/UserExport.php` (sudah chunk; tambah dukungan queue path)
- `app/Http/Controllers/Admin/UserCrudController.php` (export → dispatch, status, download)
- `database/migrations/xxxx_create_export_jobs_table.php` (atau generalisasi UM-09)
- `app/Models/ExportJob.php`
- `resources/views/admin/...` status/polling + tombol unduh
- `routes/backpack/custom.php`

## Keputusan Terbuka (TANYA Capt)
1. **Ambang** data untuk paksa mode background (mis. > 2000 user)?
2. **Retensi file** export di storage: hapus setelah diunduh / cron harian / TTL jam?
3. Sama seperti UM-09: driver queue & worker.
4. Storage tujuan: `local` cukup, atau ikut `StorageManager` (M16) bila pelanggan pakai S3?

## Rencana test UI
`tests/browser/um-10-export-queue.mjs` + PHPUnit:
- TC1 (PHPUnit): export kecil → unduh langsung, file valid (header kolom benar, tanpa password).
- TC2 (PHPUnit): export besar (seed > ambang) → job dispatched, file tertulis, status `done`.
- TC3 (browser): klik Export besar → status + polling → tombol Unduh muncul → file terunduh.

## Definition of Done
Keputusan Terbuka terjawab; export besar tak memblokir; unduh saat siap; password
tak pernah ikut; test PASS; update Status + centang README.
