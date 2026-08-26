# M09 — Recruitment ✅ DONE

> **Status:** ✅ DONE (2026-08-25) · **Prioritas:** ⚪ was optional → dikerjakan atas permintaan Capt

## Ringkasan
Pipeline rekrutmen end-to-end: lowongan (job opening) → pelamar (applicant) →
wawancara (interview) → **hire → auto-create User** (probation). Papan pipeline
kanban (drag-drop antar tahap) + kalender jadwal wawancara.

## Evaluasi Bisnis (7 Poin) — hasil implementasi
- **E1. Proses bisnis** ✅ — siklus utuh: buat lowongan (CRUD) → terima pelamar (CRUD + upload CV) → jadwalkan wawancara (CRUD) → gerakkan antar tahap di papan pipeline → **Terima** → `RecruitmentService::hire()` bikin User (status probation, inherit dept/jabatan/cabang dari lowongan) + notif ke hr_admin. Bukan tabel mati.
- **E2. Integrasi keluar** ✅ (self-managed) — tak ada dependensi job board eksternal; semua internal. Integrasi LinkedIn/JobStreet bisa nyusul via M15 kalau diminta (tidak dibangun — YAGNI).
- **E3. Tampilan** ✅ — **papan kanban** per tahap (Melamar/Seleksi/Wawancara/Penawaran/Diterima) dengan drag-drop native, bukan tabel. Jadwal wawancara → **kalender bulanan** (warna per mode: tatap muka/online/telepon), bukan tabel.
- **E4. Third-party config** ➖ — tak ada. (CV disimpan lewat StorageManager M16 yang sudah ada.)
- **E5. Keterkaitan** ✅ — hire nyambung ke User/M01 (dept/jabatan/cabang), notif M03 (hr_admin diberi tahu), onboarding lanjut ke M06 dokumen. Menu "Rekrutmen" terpadu (A-Z), ter-gate `recruitment.view`.
- **E6. Bahasa** ✅ (bertahap) — judul menu via `__('menu.recruitment')` (id+en). Label form ID (ikut pola M13 bertahap).
- **E7. Currency** ✅ — rentang gaji lowongan pakai `money()` (ikut `setting('default_currency')`), bukan hardcode Rp.

## Keputusan desain
- **Hire idempoten & aman.** `hire()` dua kali → User yang SAMA (tak duplikat). Pelamar yang sudah `hired` **tidak bisa** dikembalikan ke tahap lain (`moveStage` lempar `DomainException`) — history konsisten, akun tak yatim.
- **Pelamar tanpa email** tetap bisa di-hire: dibuatkan email placeholder unik (`pelamarN.xxxx@recruit.local`) yang dikoreksi HR saat onboarding — biar akun bisa dibuat tanpa nabrak unique constraint.
- **Password acak** di-set saat hire (tak pernah dikirim plain); karyawan reset lewat flow normal.

## Komponen
- **Migration** `2026_08_25_100001_create_recruitment_tables` — `job_openings`, `applicants` (stage + hired_user_id + cv_path), `interviews` (scheduled_at + mode + status + score).
- **Model** `JobOpening` (remainingVacancies/hiredCount/salaryRangeLabel), `Applicant` (PIPELINE stages + labels), `Interview`.
- **Service** `RecruitmentService` — `hire()` (transactional, idempoten), `moveStage()` (guard un-hire).
- **CRUD** `JobOpeningCrudController`, `ApplicantCrudController` (upload CV), `InterviewCrudController`.
- **Controller** `RecruitmentController` — pipeline board, calendar, moveStage (JSON), hire.
- **View** `admin/recruitment/pipeline.blade.php` (kanban drag-drop + tombol Terima), `calendar.blade.php`.
- **Permission** `recruitment.view` / `recruitment.edit` (super_admin + hr_admin), route ter-gate.
- **Menu** dropdown "Rekrutmen" (`__('menu.recruitment')`), sub-item A-Z.

## Automation Test
- **PHPUnit** `RecruitmentTest` — 14/14: hire bikin user + link back + inherit dept/jabatan/probation; hire idempoten; pelamar tanpa email dapat placeholder; moveStage maju; move→hired provision user; hired tak bisa mundur (DomainException); stage tak dikenal ditolak; remainingVacancies; guard pipeline (403 tanpa `recruitment.view`, 200 dengan); hire endpoint butuh `recruitment.edit` (403 viewer); hire via endpoint bikin user; moveStage endpoint update; kalender render.
- **Playwright** `m09-recruitment.mjs` — 7/7: dropdown Rekrutmen ada; buat lowongan via form CRUD; tambah pelamar via form CRUD; papan pipeline 5 kolom + kartu; **pindah ke penawaran + klik Terima → user dibuat** (native click, bukan API bypass); kalender wawancara render; nol JS error.
- **Regression:** `php artisan test` → **257 passed (538 assertions)** (naik dari 243; +14 test baru), nol regresi.

## Definition of Done — tercapai ✅
- Siklus rekrutmen jalan end-to-end lewat UI; hire otomatis bikin karyawan; papan kanban + kalender; ter-gate izin; menu terpadu; teruji PHPUnit + Playwright + regresi hijau.
