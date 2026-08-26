# M18 — Recruitment UX Overhaul (Kelola Lamaran Terkonsolidasi)

> **Status:** ✅ DONE (implemented & tested) · **Dibuat:** 2026-08-25 · **Selesai:** 2026-08-25
> **Basis:** memperbaiki UX pengelolaan lamaran hasil M09 + M17 (yang sudah DONE).
> **Pemicu:** review Capt — 4 temuan: (1) bukan best-practice penuh, (2) alur sorting→wawancara tidak efisien, (3) terpecah 3 UI, (4) ada lubang retensi file.

---

## 0. Ringkasan Implementasi (apa yang benar-benar dibangun)

| Fase | Isi | Test |
|---|---|---|
| **M18-1** | Preview CV inline (stream ber-otorisasi `recruitment/applicant/{id}/cv`) | 3 PHPUnit + live check |
| **M18-2** | Stage history/timeline (`applicant_stage_logs`) — log tiap transisi (moveStage/hire/reject) | 6 PHPUnit |
| **M18-3** | Drawer detail (offcanvas): CV inline + skor AI + rincian kriteria + wawancara + timeline; endpoint `/detail` JSON | 4 PHPUnit + 8 Playwright |
| **M18-4** | Jadwal wawancara dari drawer (pelamar auto-terisi) + auto-prompt saat drop ke kolom Wawancara | 4 PHPUnit + 6 Playwright |
| **M18-5** | Bulk actions (tolak/pindah massal) + toggle Tabel/Kanban | 5 PHPUnit + 5 Playwright |
| **M18-6** | Retensi file: ghosting purge (opening closed > N hari) + archive ke cold storage (disk ikut config) | 11 PHPUnit (CvRetention) |
| **ACCEPTANCE** | Alur kelola lamaran end-to-end dari 1 UI: drawer→baca CV→AI→jadwal wawancara→terima→karyawan, timeline merekam | 6/6 Playwright |

**Total test: 329 PHPUnit (734 assertions) HIJAU + 5 skrip Playwright M18 (33 skenario) HIJAU.** Nol regresi dari 302 baseline M17.

Semua keputusan Capt dihormati: archive/retensi **ikut config tanpa hardcode**, CV hired **dibiarkan** di `applicant-cv`, ghosting default 90 hari (config).

---

## 1. Masalah (temuan review, berbasis kode nyata)

Pengelolaan lamaran sekarang tersebar di **3 UI terpisah** untuk 1 pekerjaan:

| UI | File | Kekurangan |
|---|---|---|
| DataTable pelamar | `ApplicantCrudController` | statis; tak ada aksi cepat; klik = buka form edit |
| Papan Pipeline (kanban) | `resources/views/admin/recruitment/pipeline.blade.php` | kartu miskin (nama+skor saja); klik kartu tak ada aksi; alasan AI cuma di `title` tooltip; CV harus di-download |
| CRUD Wawancara + Kalender | `InterviewCrudController` + `calendar.blade.php` | **terputus** dari pipeline; jadwalkan wawancara harus pilih ulang pelamar dari dropdown |

**Lubang retensi file** (`recruitment:purge-cvs` + `RecruitmentService::reject()`):
- Pelamar **di-ghosting** (tak pernah ditolak) → CV numpuk selamanya.
- Opsi `archive` ada di setting M15 tapi **tak diimplementasi** (reject cuma `delete`).
- CV pelamar **diterima** tak dipindah ke dokumen karyawan.
- Retensi satu angka global; tak ada per-kondisi.

## 2. Tujuan

Konsolidasi ke **1 UI utama = Papan Pipeline + drawer detail**, jadikan alur sorting→wawancara→hire bisa dari satu kartu tanpa pindah halaman, dan tutup semua lubang retensi CV. DataTable tetap ada sebagai "table view" untuk power-user (bulk ops), Kalender tetap untuk overview.

## 3. Prinsip & Batasan

- **Ponytail/YAGNI:** perbaiki yang nyata dipakai HR, bukan bikin fitur spekulatif.
- **WC block-only tak relevan di sini** (ini HRIS Backpack). Ikuti pola Backpack + `blank` view + design token proyek (`portal.css`/theme Tabler admin).
- **Reuse infra:** M16 `StorageManager` untuk archive cold storage; M15 `SettingService` untuk config retensi; `RecruitmentService` untuk aksi bisnis (jangan taruh logika di controller).
- **Guard rail AI dipertahankan:** AI hanya mengurutkan; keputusan tetap manual HR (M17).
- **Test wajib nyata:** PHPUnit + Playwright (klik tombol/drawer asli), tiap fase hijau baru lanjut. Notif TG tiap fase (pola M17).
- **Nol regresi:** 302 test M17 harus tetap hijau. Jalankan `php -d xdebug.mode=off vendor/bin/phpunit`.
- **Bahasa UI:** Indonesia, konsisten dengan label yang ada.

---

## 4. Arsitektur Solusi

```
Papan Pipeline (1 UI utama)
 ├─ Toggle: [Papan Kanban] | [Tabel] ──── table view = DataTable existing (bulk ops)
 ├─ Kartu "kaya": nama, lowongan, skor AI+badge, indikator CV, jml wawancara, tahap
 ├─ Klik kartu → DRAWER slide-in kanan:
 │   ├─ Preview CV (iframe PDF dari route stream ber-otorisasi)
 │   ├─ Skor AI + rincian per-kriteria (ai_reasoning, bukan tooltip)
 │   ├─ Timeline tahap (audit log / stage history)
 │   ├─ Daftar wawancara + tombol "Jadwalkan Wawancara" (pelamar auto-terisi)
 │   └─ Aksi: Pindah tahap · Terima · Tolak
 ├─ Bulk actions: centang banyak → Tolak massal / Pindah tahap massal
 └─ Auto-prompt: geser kartu ke "Wawancara" → modal jadwal muncul

Retensi (cron + service)
 ├─ purge-cvs: tambah kondisi ghosting (non-hired, lowongan closed > N hari)
 ├─ archive: implement opsi archive → StorageManager cold storage (M16)
 ├─ hire: pindahkan CV ke dokumen karyawan (opsional, flag)
 └─ config per-kondisi di M15 Settings
```

---

## FASE M18-1 — Preview CV inline (stream ber-otorisasi)

**Objective:** HR bisa baca CV tanpa download, di dalam drawer.

**Files:**
- Modify: `routes/backpack/custom.php` (tambah route stream CV)
- Modify: `app/Http/Controllers/Admin/RecruitmentController.php` (method `streamCv`)
- Test: `tests/Feature/CvStreamTest.php`

**Langkah:**
1. Tulis test gagal: `test_cv_stream_requires_recruitment_view` (tamu → redirect) + `test_authorized_user_streams_cv` (super_admin → 200, content-type application/pdf) + `test_stream_404_when_cv_purged` (cv_path null → 404).
2. Route: `Route::get('recruitment/applicant/{id}/cv', [RecruitmentController::class, 'streamCv'])->name('recruitment.cv')` di grup `permission:recruitment.view`.
3. Implement `streamCv(int $id)`: `guardView()`, `findOrFail`, abort 404 kalau `cv_path` null, `Storage::disk('local')->response($path)` dengan header inline.
4. Run PHPUnit fase → hijau.
5. Commit.

**Verifikasi:** `php -d xdebug.mode=off vendor/bin/phpunit tests/Feature/CvStreamTest.php`

---

## FASE M18-2 — Stage history / timeline

**Objective:** Rekam & tampilkan perpindahan tahap (kapan, oleh siapa).

**Files:**
- Create: migration `xxxx_create_applicant_stage_logs_table.php` (applicant_id, from_stage, to_stage, actor_id, note, created_at)
- Create: `app/Models/ApplicantStageLog.php`
- Modify: `app/Services/RecruitmentService.php` (`moveStage`, `reject`, `hire` → tulis log)
- Modify: `app/Models/Applicant.php` (relasi `stageLogs`)
- Test: `tests/Feature/StageHistoryTest.php`

**Langkah:**
1. Test gagal: `test_move_stage_writes_a_log`, `test_hire_logs_transition`, `test_reject_logs_transition`, `test_log_records_actor`.
2. Migration + model (guarded fillable, cast created_at).
3. Wire ke `RecruitmentService` (actor = `backpack_user()?->id`, fallback null untuk cron).
4. Relasi `Applicant::stageLogs()` (latest first).
5. Run PHPUnit fase → hijau. Commit.

**Verifikasi:** PHPUnit fase + pastikan `RecruitmentTest` lama tetap hijau.

---

## FASE M18-3 — Drawer detail pelamar (jantung overhaul)

**Objective:** Klik kartu → panel geser berisi CV, rincian AI, timeline, wawancara, aksi.

**Files:**
- Modify: `app/Http/Controllers/Admin/RecruitmentController.php` (method `applicantDetail` → JSON)
- Modify: `routes/backpack/custom.php` (route `recruitment/applicant/{id}/detail`)
- Modify: `resources/views/admin/recruitment/pipeline.blade.php` (markup drawer + fetch JS)
- Test: `tests/Feature/ApplicantDetailTest.php` + browser `tests/browser/m18-drawer.mjs`

**Langkah:**
1. Test gagal PHPUnit: `applicantDetail` balikkan JSON {applicant, ai_reasoning, interviews[], stage_logs[], cv_url} + guard view.
2. Implement `applicantDetail(int $id)`: eager-load `interviews`, `stageLogs`, `jobOpening`; return `response()->json([...])`.
3. Route di grup `permission:recruitment.view`.
4. Drawer HTML (offcanvas Bootstrap/Tabler) + JS: klik kartu → fetch detail → render (iframe CV, badge skor + list kriteria dari `ai_reasoning.criteria`, timeline dari `stage_logs`, list wawancara).
5. Browser test `m18-drawer.mjs`: seed 1 lowongan+1 pelamar (CV nyata), buka pipeline, klik kartu → drawer muncul → assert isi (nama, iframe CV `src` mengandung `/cv`, section AI, section timeline).
6. Run PHPUnit + Playwright fase → hijau. Commit.

**Verifikasi:** browser test PASS + tidak ada JS error.

**Pitfall:** Backpack `blank` view — drawer JS taruh di `@section('after_scripts')`. Offcanvas butuh Bootstrap bundle (sudah ada di theme). Kartu jangan `draggable` bentrok dengan klik → bedakan `click` vs `dragstart` (guard: abaikan klik kalau baru saja drag).

---

## FASE M18-4 — Jadwalkan wawancara dari drawer + auto-prompt

**Objective:** Buat wawancara tanpa pindah ke CRUD; pelamar auto-terisi; geser ke tahap Wawancara → modal jadwal muncul.

**Files:**
- Modify: `app/Http/Controllers/Admin/RecruitmentController.php` (method `scheduleInterview` POST)
- Modify: `routes/backpack/custom.php` (route)
- Modify: `resources/views/admin/recruitment/pipeline.blade.php` (form jadwal di drawer + modal auto-prompt)
- Test: `tests/Feature/ScheduleInterviewInlineTest.php` + `tests/browser/m18-interview.mjs`

**Langkah:**
1. Test gagal: `test_schedule_interview_inline_creates_row` (POST applicant_id+scheduled_at+mode+interviewer → Interview dibuat, ke-link ke applicant), `test_requires_edit_permission`, `test_validation_rejects_past_or_missing`.
2. Implement `scheduleInterview` (validate, `Interview::create`, opsional pindah stage ke `interview`, return JSON).
3. Route grup `permission:recruitment.view` (aksi cek `guardEdit`).
4. Drawer: form ringkas (tanggal, mode, pewawancara dropdown, lokasi/link) → submit fetch → refresh list wawancara di drawer.
5. Auto-prompt: saat drop kartu ke kolom `interview` → buka modal jadwal (pelamar prefilled).
6. Browser test: buka drawer → isi jadwal → submit → assert wawancara muncul di drawer + DB.
7. Run PHPUnit + Playwright → hijau. Commit.

**Verifikasi:** browser PASS; `InterviewCrudController` lama tetap berfungsi (tak dihapus, jadi fallback/backoffice).

---

## FASE M18-5 — Bulk actions + toggle Tabel/Kanban

**Objective:** Centang banyak pelamar → tolak/pindah massal; toggle ke table view.

**Files:**
- Modify: `resources/views/admin/recruitment/pipeline.blade.php` (checkbox per kartu + bar aksi massal + toggle link)
- Modify: `app/Http/Controllers/Admin/RecruitmentController.php` (method `bulkAction`)
- Modify: `routes/backpack/custom.php` (route)
- Test: `tests/Feature/BulkActionTest.php` + `tests/browser/m18-bulk.mjs`

**Langkah:**
1. Test gagal: `test_bulk_reject_rejects_all_selected`, `test_bulk_move_stage`, `test_bulk_ignores_hired`, `test_requires_edit`.
2. Implement `bulkAction` (validate array ids + action in reject|move, loop lewat `RecruitmentService`, transaksi, return count).
3. UI: checkbox di kartu, bar muncul saat ≥1 dicentang (Tolak Terpilih / Pindah ke…), toggle "Tabel" → link ke `/admin/applicant`.
4. Browser test: centang 3 → tolak massal → assert 3 pindah ke kolom Ditolak.
5. Run PHPUnit + Playwright → hijau. Commit.

---

## FASE M18-6 — Retensi file: tutup semua lubang

**Objective:** Ghosting purge + archive cold storage (ikut config) + retensi per-kondisi (ikut config). Semua nilai dari M15 Settings, tak ada hardcode.

**Files:**
- Modify: `app/Console/Commands/PurgeRejectedCvs.php` (purge multi-kondisi)
- Modify: `app/Services/RecruitmentService.php` (`reject` hormati `archive`)
- Modify: `app/Services/SettingService.php` (config baru)
- Modify: `app/Console/Kernel.php` (jadwal tetap harian)
- Test: `tests/Feature/CvRetentionTest.php` (extend)

**Config baru M15 (semua punya default fallback, nilai efektif dari DB):**
- `recruitment_ghost_retention_days` (int, default 90) — hapus CV non-hired setelah lowongan closed sekian hari.
- `recruitment_archive_disk` (select, default `''` = ikut provider aktif StorageManager) — disk tujuan arsip. Pilihan diisi dari daftar disk yang tersedia; **tak ada disk dipatok di kode**.
- `recruitment_reject_action` (sudah ada: delete|archive).
- `recruitment_cv_retention_days` (sudah ada, default 30).

**Langkah:**
1. Test gagal:
   - `test_ghosted_cv_purged_after_opening_closed` (non-hired + lowongan closed > `ghost_retention_days` → CV dihapus). Set config via `SettingService`, jangan hardcode angka di assertion selain yang di-set.
   - `test_reject_with_archive_uses_configured_disk` (setting `archive` + `archive_disk` tertentu → CV pindah ke disk itu via StorageManager; `cv_path` diupdate; `cv_purged_at` TETAP null karena masih ada, cuma dingin).
   - `test_archive_falls_back_to_active_provider_when_disk_blank` (archive_disk kosong → pakai provider aktif StorageManager, bukan disk hardcoded).
   - `test_hired_cv_retained` (hired → CV tak ke-purge, tetap di `applicant-cv`).
2. Config M15: tambah 2 definisi (`recruitment_ghost_retention_days`, `recruitment_archive_disk`) di grup `rekrutmen_ai`. Options `archive_disk` di-generate dari disk yang dikenal (local + apa pun yang aktif), bukan literal.
3. `reject()`: kalau `recruitment_reject_action === 'archive'` → resolve disk dari `setting('recruitment_archive_disk')` ?: provider aktif `StorageManager`; pindahkan file; set `cv_path` ke lokasi arsip; JANGAN set `cv_purged_at`.
4. `PurgeRejectedCvs`: tambah query kedua — non-hired + `jobOpening.status=closed` + `closed_at <= now()->subDays(setting('recruitment_ghost_retention_days', 90))` → purge (hormati archive juga). Semua ambang dari config.
5. Run PHPUnit fase → hijau. Commit.

**Pitfall:** `StorageManager::disk()` balikkan Filesystem provider aktif. Untuk archive: kalau `recruitment_archive_disk` kosong → pakai itu (subfolder `cold/` bila lokal); kalau diisi → resolve `Storage::disk($name)`. JANGAN patok nama disk di kode — baca config. CV pelamar **hired biarkan** di `applicant-cv` (keputusan Capt).

---

## FASE M18-7 — Acceptance + regססi + dokumentasi

**Objective:** Buktikan alur end-to-end baru & nol regresi.

**Langkah:**
1. Browser acceptance `tests/browser/m18-acceptance.mjs`: 5 pelamar 1 lowongan → HR buka pipeline → klik kartu → baca CV di drawer → lihat skor AI → jadwalkan wawancara dari drawer → geser ke offer → terima → karyawan dibuat. Semua dari 1 UI.
2. Full regression: `php -d xdebug.mode=off vendor/bin/phpunit` → target semua hijau (302 + tambahan M18).
3. Update `docs/plan/M18-recruitment-ux-DONE.md` (ringkasan) + rename + README index.
4. Notif TG final.

---

## 5. Files Ringkasan (yang bakal disentuh)

- `app/Http/Controllers/Admin/RecruitmentController.php` — streamCv, applicantDetail, scheduleInterview, bulkAction
- `resources/views/admin/recruitment/pipeline.blade.php` — drawer, bulk, toggle, auto-prompt
- `app/Services/RecruitmentService.php` — stage log, archive
- `app/Console/Commands/PurgeRejectedCvs.php` — ghosting + archive
- `app/Services/SettingService.php` — config retensi baru
- Migration + `app/Models/ApplicantStageLog.php` — timeline
- `routes/backpack/custom.php` — 4 route baru
- Tests: 6 PHPUnit baru + 4 Playwright baru

## 6. Risiko & Tradeoff

- **Drawer JS di Backpack blank view** — theme Tabler punya Bootstrap; offcanvas harus diverifikasi jalan. Mitigasi: fase M18-3 punya browser test khusus.
- **Klik vs drag pada kartu** — bisa bentrok. Mitigasi: flag `justDragged`.
- **Archive tanpa disk kedua** — kalau provider tunggal, archive = subfolder cold. Diputuskan saat eksekusi setelah baca `StorageManager`.
- **Tidak menghapus 3 UI lama** — DataTable & CRUD Wawancara tetap ada (fallback/backoffice), hanya bukan jalur utama. Mengurangi risiko regresi.

## 7. Urutan Eksekusi

M18-1 (preview CV) → M18-2 (timeline) → M18-3 (drawer, butuh 1&2) → M18-4 (wawancara inline) → M18-5 (bulk+toggle) → M18-6 (retensi) → M18-7 (acceptance). Tiap fase: test hijau + notif TG sebelum lanjut.

## 8. Keputusan Terkunci (Capt, 2026-08-25)

1. **Archive CV = IKUT CONFIG, tak boleh hardcode.** Disk tujuan arsip dibaca dari
   setting M15 (`recruitment_archive_disk`), default mengikuti provider aktif
   `StorageManager`. Kalau HR pilih S3/Nextcloud/dll → arsip ke sana; kalau lokal →
   subfolder `cold/`. Tak ada disk yang dipatok di kode.
2. **CV pelamar diterima = BIARKAN di `applicant-cv`.** Tidak auto-pindah ke Dokumen
   Karyawan (M06). (Fase "hire pindah CV" DIBATALKAN dari rencana.)
3. **Retensi = SEMUA lewat config, tak ada angka hardcode.** Baik retensi rejected
   (`recruitment_cv_retention_days`, sudah ada, default 30) maupun ghosting
   (`recruitment_ghost_retention_days`, baru, default 90) dibaca dari M15 Settings.
   Default hanya fallback saat setting kosong — nilai efektif selalu dari config.
