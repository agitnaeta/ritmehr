# Rencana Perbaikan — Review Version 3 (Keamanan · Optimasi · Best Practice)

> Index checklist per-file. **1 task = 1 file.** Detail tiap task di `tasks/<ID>.md`.
> Sumber temuan: [analisis-teknis.md](analisis-teknis.md). Konvensi: baca kode dulu →
> edit → `php -l` + `view:clear` → PHPUnit → `crud-suite.mjs` → verifikasi browser →
> flip `Status:` ke `[x] DONE` + isi commit SHA.
>
> **Baseline yang wajib tetap hijau:** PHPUnit (`php -d memory_limit=2G -d xdebug.mode=off vendor/bin/phpunit --no-coverage`) + `node tests/browser/crud-suite.mjs` (146).

## Quick Wins (jam-an, edit file existing)

| ID | File | Fokus | Sev | Status |
|---|---|---|---|---|
| QW-01 | `app/Http/Controllers/Admin/SalaryRecapCrudController.php` | PERF-5 hilangkan N+1 export gaji (`user.salary`) | 🟠 | [x] DONE |
| QW-02 | `app/Exports/UserExport.php` | PERF-6 stop dump semua kolom (bocor password) + chunk | 🟠 | [x] DONE |
| QW-03 | `routes/web.php` | SEC-1 throttle auth kandidat + ganti password portal | 🔴 | [x] DONE |
| QW-04 | `app/Http/Controllers/Portal/PortalController.php` | SEC-3 password kuat + SEC-4 error konsisten | 🟡 | [ ] TODO |
| QW-05 | `app/Http/Controllers/Career/CandidateAuthController.php` | SEC-3 password kuat registrasi | 🟡 | [ ] TODO |
| QW-06 | `.env.example` | BP-4 samakan DB_PORT + SEC-2 checklist rilis | 🟢 | [ ] TODO |
| QW-07 | `config/backpack/base.php` | SEC-1 (bag.2) throttle login Backpack | 🔴 | [x] DONE (no-op: sudah aman bawaan) |

## Config / Env Migration (BP-1 / PERF-2 — cegah `config:cache` merusak produksi)

| ID | File | Fokus | Sev | Status |
|---|---|---|---|---|
| CFG-01 | `config/services.php` | Destinasi: pusatkan semua key `env()` | 🟠 | [x] DONE |
| CFG-02 | `app/Services/Matching/QdrantService.php` | `env()` → `config()` | 🟠 | [x] DONE |
| CFG-03 | `app/Services/Matching/LlmScoringManager.php` | `env()` → `config()` | 🟠 | [x] DONE |
| CFG-04 | `app/Services/Matching/EmbeddingManager.php` | `env()` → `config()` | 🟠 | [x] DONE |
| CFG-05 | `app/Services/CvExtractionService.php` | `env()` → `config()` | 🟠 | [x] DONE |
| CFG-06 | `app/Services/TransactionService.php` | `env()` → `config()` | 🟠 | [x] DONE |
| CFG-07 | `app/Services/Acc/Acc.php` | `env()` → `config()` | 🟠 | [x] DONE |

## Struktural (butuh test + review, file baru/refactor)

| ID | File | Fokus | Sev | Status |
|---|---|---|---|---|
| ST-01 | `app/Jobs/ExtractCvJob.php` (baru) | PERF-1 ekstraksi CV ke queue | 🟠 | [ ] TODO |
| ST-02 | `app/Exports/SalaryRecapExport.php` | PERF-7 FromQuery + chunk | 🟡 | [ ] TODO |
| ST-03 | `app/Exports/LoanExport.php` | PERF-7 FromQuery + chunk | 🟡 | [ ] TODO |
| ST-04 | `app/Repositories/LoanRepository.php` | PERF-3 buang subquery ganda O(n) | 🟡 | [ ] TODO |
| ST-05 | `.github/workflows/ci.yml` (baru) | BP-2 CI phpunit + pint | 🟠 | [ ] TODO |

---

## Urutan eksekusi yang disarankan
1. **QW-03 + QW-07** (SEC-1 🔴 — tutup brute-force dulu).
2. **QW-01 + QW-02** (PERF-5/6 🟠 — quick win CPU/RAM export + tutup bocor kolom).
3. **CFG-01 → CFG-02..07** (BP-1 — CFG-01 dulu karena CFG-02..07 bergantung padanya).
4. **QW-04 + QW-05 + QW-06** (pengerasan ringan).
5. **ST-01, ST-05** (queue + CI — fondasi), lalu **ST-02/03/04** (skalabilitas export & query).

## Catatan
- Task bertanda "task pendamping" (mis. ST-01 menyentuh `CareerController.php` &
  `.env`; QW-07 menyentuh `AppServiceProvider.php`) — bila Capt mau ketat **1 file 1
  task**, pecah file pendamping itu jadi task sendiri saat eksekusi. File utama tiap task
  sudah tunggal.
- Semua diff sudah pakai **line number nyata** hasil baca kode (build `cd1ba8d`), bukan
  tebakan. Verifikasi ulang line saat eksekusi kalau ada commit lain mendahului.
