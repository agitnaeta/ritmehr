# UM-09 — Import 1000+ user via background/queue

**Poin Capt #9** · Tipe: Struktural · Urgensi: Tinggi · Risiko: Sedang
**Status:** [ ] TODO · ⚠️ Ada **Keputusan Terbuka** (infra queue)

---

## Konteks
Import saat ini sinkron dalam request. 1000+ baris → request lambat / timeout.
Butuh proses latar (queue) + indikator progress supaya admin tak menunggu blank.

## Akar masalah (terverifikasi)
- `UserCrudController::importStore()` (`:461-491`) memanggil `Excel::import()` **sinkron**.
- Tak ada job status / progress tracking.

## ⚠️ Realita infra queue (terverifikasi — WAJIB dibaca)
Ini juga berlaku untuk UM-10 & UM-11 mode background.
- `.env` **`QUEUE_CONNECTION=sync`** → semua job jalan **inline di request**, bukan async.
- **Tabel `jobs` BELUM ADA** — hanya `failed_jobs` (`2019_08_19_000000_create_failed_jobs_table.php`).
  Untuk driver `database` butuh migration `jobs` (`php artisan queue:table` + migrate).
- Preseden: **`app/Jobs/ExtractCvJob.php` sudah `implements ShouldQueue` dan di-dispatch**
  (`CareerController:94`), TAPI karena driver `sync` ia berjalan **inline** — jadi pola
  "queue" di repo saat ini praktis no-op. Jangan tiru buta; butuh infra nyata.
- `maatwebsite/excel ^3.1` **mendukung** `WithChunkReading` + queued import — tapi
  bergantung pada driver queue di atas.

Artinya: "background process" untuk 1000+ user **bukan sekadar tambah `ShouldQueue`** —
butuh (a) tabel `jobs`, (b) `QUEUE_CONNECTION=database`, (c) worker `queue:work` jalan.

## Rencana solusi (desain)
1. **Queued import** — `UserImport implements ShouldQueue, WithChunkReading`
   (Maatwebsite `queueImport`) → proses per-chunk di worker.
2. **Tabel status** `import_jobs` (atau reuse pola job existing): id, user_id (pengunggah),
   total_rows, processed, imported, skipped, status (queued/processing/done/failed),
   errors (json), file_path. Update saat chunk selesai (`WithEvents`/`AfterImport`).
3. **UI**: setelah upload → tampilkan halaman status + polling (JS `fetch` tiap 3s ke
   endpoint status by job id) → progress bar + ringkasan saat selesai (imported/skipped/errors).
4. **Endpoint**: `GET /admin/user/import/{job}/status` (JSON), scoped ke pengunggah/HR.
5. Worker: `php artisan queue:work`.

File yang disentuh (rencana):
- `app/Imports/UserImport.php` (ShouldQueue + event hooks)
- `app/Http/Controllers/Admin/UserCrudController.php` (importStore → dispatch, status endpoint)
- `database/migrations/xxxx_create_import_jobs_table.php`
- `app/Models/ImportJob.php`
- `resources/views/admin/import/user.blade.php` + partial status/polling
- `routes/backpack/custom.php` (route status)

## Keputusan Terbuka (TANYA Capt sebelum eksekusi)
1. **Driver queue?** `database` (paling sederhana, cukup `queue:work`) vs Redis/Horizon.
   Cek `.env` `QUEUE_CONNECTION` existing. Rekomendasi: `database` dulu (YAGNI).
2. **Worker jalan di mana?** Perlu supervisor/systemd di server, atau cukup manual
   `queue:work` saat dev? (Untuk demo/dev bisa manual.)
3. **Batas ukuran file** import? (mis. max 5MB / 20rb baris).
4. **Notifikasi selesai**: cukup polling di halaman, atau + notifikasi in-app (M-notif)?

## Rencana test UI
`tests/browser/um-09-import-queue.mjs` + PHPUnit:
- TC1 (PHPUnit): dispatch import → job masuk queue (fake), status row terbuat.
- TC2 (PHPUnit): jalankan job sync di test → imported count benar, status `done`.
- TC3 (browser): upload file → halaman status muncul + progress → selesai tampil ringkasan.
- TC4: file dengan baris invalid → errors terlaporkan, baris valid tetap masuk.

## Definition of Done
Keputusan Terbuka terjawab; import berjalan di queue tanpa memblokir request;
progress terlihat; test PASS (dengan worker/sync test); update Status + centang README.
