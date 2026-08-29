# UM-09 — Import 1000+ user: background process + partial error handling (PLAN)

**Poin Capt** · Tipe: Struktural · Urgensi: Tinggi · Risiko: Sedang
**Status:** [ ] PLAN — menunggu keputusan Capt sebelum eksekusi

**Status:** [x] DONE (2026-08-29) — SEMUA import background (revisi Capt), progress polling, partial error + unduh CSV.
Test: UserImportJobTest (2) + UserImportBackgroundFlowTest (4) + FASE1 speed; regresi Import/User/Preview/Locale 52 PASS.

---

## 1. Gejala (dari Capt)
- Import 1000 user → interface **loading di request** (bukan background) →
  **"Maximum execution time of 30 seconds exceeded"**.
- Pertanyaan: kalau dari 1000 baris ada yang error sebagian, **bagaimana handle-nya**.

## 2. Akar masalah (TERUKUR, bukan asumsi)
| # | Penyebab | Bukti | Dampak |
|---|----------|-------|--------|
| A | **bcrypt 1000×** | `BCRYPT_ROUNDS=12`, terukur **225,8 ms/hash** → **±226 detik** utk 1000 baris | Ini pembunuh utama — sendirian sudah 7× lipat limit 30s |
| B | **N+1 query** | `UserImport::model()` panggil `firstOrCreate` utk departemen+cabang+jabatan per baris → ±4×1000 = 4000 query | Menambah beban besar |
| C | **Sinkron di request** | `importStore()` → `Excel::import()` inline; `php artisan serve` timeout 30s | Request diblokir sampai selesai/timeout |
| D | **Driver queue `sync`** | `.env QUEUE_CONNECTION=sync`, tabel `jobs` belum ada (hanya `failed_jobs`) | `ShouldQueue` jalan inline = no-op, bukan async |

> Kesimpulan: "jadikan background" **tidak cukup** hanya nambah `ShouldQueue`.
> Wajib benahi (A) bcrypt + (B) N+1 + (C/D) infra queue sekaligus, kalau tidak
> worker pun tetap lambat/berat.

## 3. Partial error handling — kondisi SAAT INI
- `UserImport` **sudah** `WithValidation` + `SkipsOnFailure` + `SkipsFailures`.
  Artinya baris invalid **sudah** di-skip & dikumpulkan di `$import->failures()`,
  **baris valid tetap masuk** — pondasi partial-error sudah ada.
- Yang KURANG:
  - Hasil error hanya tampil sekali di akhir (setelah proses sinkron selesai) —
    percuma kalau request keburu timeout.
  - Tidak ada persistensi hasil (kalau user pindah halaman, hilang).
  - Tidak ada laporan error yang bisa **diunduh** (baris mana, kolom apa, alasan).
  - NIK duplikat / email invalid / departemen kosong tercampur tanpa ringkasan rapi.

---

## 4. Rencana solusi — breakdown PER FILE

> Prinsip: satu-per-satu, tiap langkah ada test. Fitur struktural (halaman
> status/progress) → **mockup HTML dulu** sebelum dikoding penuh.

### FASE 0 — Infra queue (prasyarat)  ·  Status: [ ] TODO
- [ ] `database/migrations/xxxx_create_jobs_table.php` — `php artisan queue:table` + migrate (driver `database`).
- [ ] `database/migrations/xxxx_create_job_batches_table.php` — `php artisan queue:batches-table` (untuk progress via Bus::batch).
- [ ] `.env` / `.env.example` — `QUEUE_CONNECTION=database`.
- [ ] Dokumen cara jalankan worker: `php artisan queue:work` (dev) / supervisor (prod).
- Test: `php artisan queue:work --once` memproses 1 job dummy.

### FASE 1 — Hilangkan bottleneck bcrypt & N+1  ·  Status: [ ] TODO
File: `app/Imports/UserImport.php`
- [ ] **bcrypt**: untuk import massal, hash password **sekali** kalau semua sama
      (mayoritas pakai `password` default), atau turunkan cost khusus import,
      ATAU (rekomендasi) hash password default SEKALI di luar loop lalu reuse;
      hanya hash ulang bila kolom `password` per-baris berbeda.
      > Target: dari 226s → < 1s untuk kasus password seragam.
- [ ] **N+1**: pre-load map `nama→id` untuk Department/Branch/Position SEKALI di
      `__construct` (atau cache statis), ganti `firstOrCreate` per baris jadi
      lookup map + kumpulkan entity baru untuk `insert` batch.
- [ ] Tambah `WithChunkReading` (chunk 100–200) + `WithBatchInserts` (batch 100)
      untuk hemat memori & query.
- Test PHPUnit: import 1000 baris (sync, di test) selesai < 5 detik + semua masuk.

### FASE 2 — Model status + tabel  ·  Status: [ ] TODO
- [ ] `database/migrations/xxxx_create_import_jobs_table.php`:
      `id, user_id, type, file_path, total_rows, processed, imported, skipped,
       status(queued|processing|done|failed), errors(json), created_at, updated_at`.
- [ ] `app/Models/ImportJob.php` — casts errors→array, relasi uploader, scope milik user.
- Test: buat ImportJob, update progress, assert casts.

### FASE 3 — Queued import + partial error persist  ·  Status: [ ] TODO
File: `app/Imports/UserImport.php`, `app/Jobs/` (opsional)
- [ ] `UserImport implements ShouldQueue, WithChunkReading` (Maatwebsite `queueImport`).
- [ ] Hook `WithEvents` / `AfterImport` / `SkipsOnFailure` → update `ImportJob`:
      processed, imported, skipped, errors[] (baris#, kolom, pesan).
- [ ] Simpan **laporan error** ke json (dan opsi unduh CSV baris gagal).
- Test PHPUnit: dispatch (Bus::fake) → job batched; jalankan → ImportJob `done`,
  imported/skipped benar, errors terekam.

### FASE 4 — UI status + progress (MOCKUP HTML DULU)  ·  Status: [ ] TODO
- [ ] **Mockup HTML statis** halaman status: progress bar, angka
      total/proses/berhasil/dilewati, tabel error (baris/kolom/alasan), tombol
      "Unduh laporan error" + "Impor lagi". → tunjukkan ke Capt.
- [ ] Setelah disetujui: `resources/views/admin/import/status.blade.php` +
      partial polling (JS `fetch` tiap 3s).
- [ ] `UserCrudController::importStore()` → simpan file, buat ImportJob, dispatch
      queued import, redirect ke halaman status (bukan blok request).
- [ ] `UserCrudController::importStatus(ImportJob $job)` → JSON progress, scoped.
- [ ] `routes/backpack/custom.php` → route status + unduh laporan error.
- Test browser: upload 1000 → redirect status → progress jalan → selesai ringkasan.

### FASE 5 — Ambang & fallback  ·  Status: [ ] TODO
- [ ] File kecil (≤ ambang, mis. 200 baris) → tetap **sinkron** (UX instan).
- [ ] File besar (> ambang) → **background** otomatis.
- [ ] Batas ukuran file & jumlah baris (tolak lebih dari mis. 50rb baris).
- Test: 50 baris sinkron; 1000 baris background.

---

## 5. Strategi partial error (jawaban pertanyaan Capt)
1. **Validasi per baris** (sudah ada, diperluas): email wajib+format, nama wajib,
   NIK `nullable|unique`, departemen/cabang/jabatan opsional.
2. **Baris valid tetap masuk**, baris gagal **dilewati** (bukan gagalkan semua) —
   `SkipsOnFailure` sudah menjamin ini.
3. **Kumpulkan kegagalan** → `ImportJob.errors[]`: `{baris, kolom, nilai, alasan}`.
4. **Ringkasan di UI**: "980 berhasil · 20 dilewati" + tabel error + **unduh CSV
   baris gagal** supaya Capt bisa perbaiki & re-upload hanya yang gagal.
5. **Idempoten**: import keyed by email (`updateOrCreate`) — re-upload aman, tak dobel.

---

## 6. Keputusan Terbuka — ✅ TERKUNCI (Capt, 2026-08-29)
1. **Driver queue**: ✅ `database` (queue:table + migrate, `QUEUE_CONNECTION=database`).
2. **Worker dev**: ✅ manual `php artisan queue:work`; prod pakai supervisor/systemd.
3. **Password default import**: ✅ hash SEKALI untuk baris tanpa kolom password; hash per-baris hanya bila password diisi.
4. **Ambang sinkron vs background**: ✅ **SELALU BACKGROUND** (revisi Capt) — tidak ada
   jalur sinkron. Semua import (berapapun barisnya) masuk queue + tampilkan
   halaman loading/progress. FASE 1 (bcrypt+N+1) tetap dikerjakan agar job cepat.
5. **Laporan error**: ✅ tabel ringkasan di halaman + **unduh CSV baris gagal**
   (baris#, kolom, nilai, alasan) supaya bisa diperbaiki & di-import ulang.
6. **Notifikasi selesai**: polling di halaman status (notif TG opsional, belakangan).

## 7. Rencana test (ringkas)
- PHPUnit: FASE1 (speed+semua masuk), FASE2 (model), FASE3 (batched+errors persist), FASE5 (ambang).
- Browser: upload 1000 → status/progress → ringkasan + unduh error.
- Regresi: suite Import/User/Preview tetap hijau.

## Definition of Done
Keputusan terjawab; import 1000+ tak memblokir request (tak ada timeout 30s);
progress terlihat; partial error terlaporkan + bisa diunduh; baris valid tetap
masuk; test PASS; Status ditandai + README dicentang.
