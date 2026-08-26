# M10 — Performance Management ✅ DONE

> **Status:** ✅ DONE (2026-08-25) · **Prioritas:** ⚪ was optional → dikerjakan atas permintaan Capt

## Ringkasan
Siklus penilaian kinerja end-to-end: **siklus (review cycle) → KPI (katalog + bobot) →
generate review per karyawan → self-review + penilaian manajer (skor 1-5 per KPI) →
finalisasi (skor akhir rata-rata TERTIMBANG) → papan skor (bar chart)**.

## Evaluasi Bisnis (7 Poin) — hasil implementasi
- **E1. Proses bisnis** ✅ — siklus penuh lewat UI: set periode (CRUD) → set KPI + bobot (CRUD) → "Buat Penilaian" (generate review+item per karyawan, idempoten) → karyawan isi self-review → manajer nilai → finalisasi (skor terkunci + notif ke karyawan). Bukan tabel mati.
- **E2. Integrasi keluar** ➖ — internal penuh.
- **E3. Tampilan** ✅ — form penilaian (self vs manajer berdampingan per KPI), **papan skor dashboard = bar chart Chart.js** (skor akhir per karyawan + rata-rata), bukan cuma tabel.
- **E4. Third-party config** ➖ — tidak ada.
- **E5. Keterkaitan** ✅ — reviewer otomatis = `manager_id` karyawan (M01), notif finalisasi via M03, menu "Kinerja" terpadu. (Feed ke payroll/bonus sengaja TIDAK dibangun — YAGNI; skor akhir tersedia kalau nanti diperlukan.)
- **E6. Bahasa** ✅ (bertahap) — judul menu via `__('menu.performance')` (id+en). Label form ID (pola M13 bertahap).
- **E7. Currency** ➖ — N/A (skor, bukan uang). Bonus tak dibangun.

## Keputusan desain
- **Skor akhir = rata-rata TERTIMBANG skor manajer** per KPI (bobot di-snapshot ke `review_items.weight` saat generate, jadi ubah bobot KPI nanti tak meng-korupsi review lama). Self-score untuk refleksi/diskusi, tak masuk skor akhir.
- **Generate idempoten + top-up.** Jalankan ulang untuk siklus → tak duplikat review; KPI baru yang ditambah mid-siklus otomatis di-top-up sebagai item ke review yang sudah ada. Aman untuk karyawan/KPI baru.
- **Finalisasi = aksi sengaja & mengunci.** Setelah `finalized`, self/manager submit ditolak (`DomainException`) — skor akhir tak bisa diutak-atik. Finalisasi butuh minimal ada skor manajer (else ditolak).
- **Ownership ketat.** Karyawan hanya bisa buka/isi review MILIKNYA (`user_id === me`); manajer hanya review yang dia-reviewer atau super_admin/hr_admin. Dicek di controller (bukan cuma middleware) + diuji.
- **Skor di-clamp 1..5** di service (input nakal 9 → 5).

## Komponen
- **Migration** `2026_08_25_110001_create_performance_tables` — `review_cycles`, `kpis`, `reviews` (unique cycle+user, final_score, status), `review_items` (self/manager score + weight snapshot, unique review+kpi).
- **Model** `ReviewCycle`, `Kpi`, `Review` (status labels), `ReviewItem`.
- **Service** `PerformanceService` — `generateReviews` (idempoten), `submitSelf`/`submitManager` (clamp + guard finalized), `finalize` (weighted score + notif), `weightedManagerScore`, `cycleScoreboard`.
- **CRUD** `ReviewCycleCrudController`, `KpiCrudController` (edit-gated `performance.edit`).
- **Controller** `PerformanceController` — index (penilaian saya + tim), show (form self/manager), generate, submitSelf, submitManager, finalize, scoreboard.
- **View** `admin/performance/index.blade.php`, `review.blade.php` (form self+manajer+finalisasi), `scoreboard.blade.php` (bar chart).
- **Permission** `performance.view` / `performance.edit` / `performance.review_self`. super_admin+hr_admin: semua; manager: view+edit+self; employee: review_self. Route cycle/KPI/scoreboard/manager/finalize ter-gate `performance.edit`; self-service (index/show/self) di-guard controller + ownership.
- **Menu** dropdown "Kinerja" (`__('menu.performance')`), sub-item A-Z; "Penilaian Saya" untuk semua, item admin (KPI/Papan Skor/Siklus) hanya untuk `performance.edit`.

## Automation Test
- **PHPUnit** `PerformanceTest` — 12/12: generate 1 review+item per KPI per karyawan; idempoten + top-up KPI baru; tanpa KPI aktif → throw; reviewer = manager_id; **finalize = weighted (5*3+1*1)/4 = 4.00**; finalize tanpa skor manajer → throw; review finalized tak bisa diedit → throw; self-score clamp ke 5; scoreboard butuh `performance.edit` (403); karyawan buka review sendiri OK / orang lain 403; owner submit self via endpoint; index list review saya.
- **Playwright** `m10-performance.mjs` — 7/7: dropdown Kinerja; buat KPI via form; buat siklus via form; **Buat Penilaian** (generate); **buka review → nilai manajer → finalisasi → skor akhir tampil** (native select/click, bukan API bypass); papan skor render bar chart; nol JS error.
- **Regression:** `php artisan test` → **269 passed (560 assertions)** (naik dari 257; +12 test baru), nol regresi.

## Definition of Done — tercapai ✅
- Siklus penilaian jalan end-to-end lewat UI; skor akhir tertimbang + terkunci saat finalisasi; papan skor chart; ownership + permission ketat; menu terpadu; teruji PHPUnit + Playwright + regresi hijau.
