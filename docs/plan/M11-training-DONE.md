# M11 — Training & Development (Mini-LMS Internal)

> **Status:** ✅ DONE (implemented & tested) · **Dibuat:** 2026-08-26 · **Selesai:** 2026-08-26
> **Prioritas:** ⚪ optional → dikerjakan atas permintaan Capt
>
> ⚠️ Catatan: file ini ditulis ulang setelah folder `docs/plan/` sempat terhapus
> oleh restrukturisasi "review-version-1" (workstream terpisah). Kode M11 tidak
> terpengaruh — semua utuh & teruji.

---

## 0. Ringkasan Implementasi (apa yang benar-benar dibangun)

| Fase | Isi | Test |
|---|---|---|
| **M11-1** | 4 tabel (`trainings`, `training_materials`, `training_questions`, `training_enrollments`) + 4 model + `TrainingService` (grade auto, enroll idempoten, reset, archive/restore, YouTube embed) | 12 PHPUnit |
| **M11-2** | Admin `TrainingController` + view index/create/manage (tab **Materi/Latihan/Peserta/Detail** inline) + publish/archive/restore + reset percobaan + menu + permission | (browser) |
| **M11-3** | Peserta `/my/training`: daftar → baca materi (teks + lampiran + **YouTube embed**) → kuis auto-grade → hasil LULUS/TIDAK → **sertifikat A4 print-to-PDF** | (browser) |
| **ACCEPTANCE** | E2E 1 skrip: admin buat pelatihan+materi+soal → enroll karyawan → publish → peserta baca → kuis → LULUS (skor 100) → sertifikat ter-render + DB `passed` | 11/11 Playwright |

**Total test: 12 PHPUnit (33 assertions) + 11 Playwright (m11-training) HIJAU.
Full regression: 403 PHPUnit (1035 assertions) HIJAU — nol regresi (baseline 378 → 403).**

### Keputusan Capt yang ter-encode
1. 1 set kuis per pelatihan · 2. pilihan ganda auto-grade · 3. KKM per-pelatihan (`passing_score`) ·
4. **sertifikat PDF saat lulus** (`certificate_no` unik, template A4 landscape) ·
5. **batas 3× percobaan** → `locked`, hanya HR bisa reset ·
6. materi **lampiran (StorageManager M16) DAN/ATAU YouTube URL** (auto-embed).

### Model Data
```
trainings              id, title, description, trainer_id, category,
                       passing_score(70), max_attempts(3),
                       status(draft|published|archived), archived_at
training_materials     id, training_id, position, title, content,
                       attachment_path, video_url
training_questions     id, training_id, position, question,
                       option_a..d, correct_option(a|b|c|d)
training_enrollments   id, training_id, user_id,
                       status(enrolled|passed|failed|locked),
                       score, attempts, passed_at,
                       certificate_no, certificate_issued_at
                       UNIQUE(training_id, user_id)
```

Grading: `score = benar × (100 ÷ jumlah_soal)`; `≥ passing_score` → passed (+cert);
gagal & `attempts ≥ max_attempts` → locked.

### File yang dibuat/diubah
- Migration `2026_08_26_150001_create_training_tables.php`.
- Model `Training`, `TrainingMaterial`, `TrainingQuestion`, `TrainingEnrollment`.
- Service `app/Services/TrainingService.php`.
- Admin `app/Http/Controllers/Admin/TrainingController.php` + `resources/views/admin/training/{index,create,manage}.blade.php`.
- Portal `app/Http/Controllers/Portal/TrainingPortalController.php` + `resources/views/portal/training_{index,show,quiz,result,certificate}.blade.php`.
- Routes: 16 admin (`routes/backpack/custom.php`) + 6 portal (`routes/web.php`).
- Permission `training.view`/`training.edit`/`training.enroll_self` (seeder) + menu (id/en) + portal nav.
- Tests `tests/Feature/TrainingGradingTest.php` + `tests/browser/m11-training.mjs` + `_m11_helper.php`.
- Mockup UI `docs/plan/mockup/m11-*.html`.

### Alur Bisnis (UI → UI)
**Pelatih/HR:** Buat pelatihan → tab Materi (tambah bab urut, lampiran/YouTube) → tab
Latihan (soal PG + tandai kunci) → tab Peserta (multiselect + Pilih Semua) → Terbitkan →
kelak Arsipkan. **Peserta (`/my/training`):** daftar assigned → baca materi berurutan →
Mulai Latihan → kumpulkan → auto-grade → LULUS (sertifikat) / TIDAK LULUS (ulang, maks 3×).

### Pitfall saat eksekusi
- **Tombol form tanpa `type="submit"`** → Playwright `button[type="submit"]` tak match &
  submit tak jalan (halaman diam). Semua tombol submit di `manage.blade.php` diberi
  `type="submit"` eksplisit.
- Notif tipe baru (`training_passed`/`training_assigned`) tak perlu template —
  `NotificationTemplates::render` fallback ke `data['title']/['body']` untuk tipe tak dikenal.

---

## 1. Evaluasi Bisnis (7 Poin) — hasil
- **E1** ✅ siklus utuh: buat pelatihan → materi → latihan → enroll → belajar → kuis → LULUS/TIDAK + sertifikat.
- **E2** ➖ internal penuh (LMS eksternal = YAGNI).
- **E3** ✅ materi editor inline berurut; kuis PG; hasil badge LULUS/TIDAK + skor; sertifikat A4.
- **E4** ✅ lampiran materi lewat StorageManager (disk dari config M15/M16).
- **E5** ✅ peserta = User (M01); notif assigned/passed via M03; menu "Pelatihan" terpadu.
- **E6** ✅ bertahap — menu `__('menu.training')` (id/en); label form ID (pola M13).
- **E7** ➖ biaya pelatihan tak dibangun (YAGNI).

## 2. Definition of Done — tercapai ✅
1. Pelatih buat pelatihan + materi + soal dari satu layar bertab. ✅
2. Peserta baca → kerjakan latihan → auto LULUS/TIDAK by KKM. ✅
3. Pelatihan bisa diarsip & dipulihkan, data tetap. ✅
4. Lampiran materi lewat StorageManager; video via YouTube embed. ✅
5. Sertifikat PDF saat lulus (print A4). ✅
6. Batas 3× percobaan → locked, HR bisa reset. ✅
7. Menu + permission + portal nav; PHPUnit + Playwright hijau, nol regresi. ✅
