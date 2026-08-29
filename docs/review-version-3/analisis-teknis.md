# Analisis Teknis RitmeHR — Keamanan · Optimasi · Best Practice

> Tanggal: 2026-08-29 · Penyusun: Hermes Agent · Build/commit: `cd1ba8d` (master)
> Stack terverifikasi: Laravel 12.68 · PHP ^8.2 · Backpack CRUD 6.8 · MySQL (docker :3307)
> Metode: audit kode nyata (grep pola, baca controller/service/route/config), bukan asumsi.
> Cakupan: 37 CRUD controller · 55 model · 19 service · 67 migrasi · 51 file test.

---

## Ringkasan Eksekutif

Secara arsitektur RitmeHR **rapi dan disiplin**: ada lapisan Service (19), Repository,
Observer, Notification, Form Request (12), `$fillable` di seluruh 55 model (nol
`guarded=[]`), dan eager-loading yang konsisten (81 `with(...)`). Identitas absensi
mandiri diambil dari sesi — **bukan dari body request** — jadi tahan spoofing. Semua raw
SQL parameter-less (nol permukaan SQL injection), tidak ada `eval`/`unserialize`, proses
Python CV pakai argumen array (bukan shell string), upload CV tervalidasi & disk privat.

**Tiga temuan yang harus ditindak lebih dulu:**
1. 🔴 **Tidak ada rate-limit di endpoint login/registrasi mana pun** (admin, kandidat,
   portal) → brute-force & credential-stuffing terbuka lebar.
2. 🟠 **`QUEUE_CONNECTION=sync` + ekstraksi CV (proses Python) jalan sinkron di dalam
   request publik `career/lamar`** → response lambat, rawan timeout, mudah dijadikan DoS.
3. 🟠 **`env()` dipanggil 10× di dalam `app/`** → nilai jadi `null` begitu `config:cache`
   dijalankan di produksi (jebakan klasik Laravel).

Sisanya adalah pengerasan sebelum produksi (`APP_DEBUG=true`, driver `file`, tanpa CI).

---

## Validasi Ulang (Self-Review, 2026-08-29)

> Audit awal di-review ulang dengan verifikasi kode tambahan. **Verdict: SETUJU dengan
> analisis awal — semua temuan 🔴/🟠 dikonfirmasi valid, dan re-review menemukan 3
> kekuatan tambahan yang sebelumnya belum terdokumentasi.** Tak ada temuan yang perlu
> diturunkan/dibatalkan. Rincian pembuktian:

| Klaim awal | Hasil verifikasi ulang | Status |
|---|---|---|
| SEC-1: login tak di-throttle | **Dikonfirmasi.** `config/backpack/base.php` HANYA punya `password_recovery_throttle_access` (`:98`) & `email_verification_throttle_access` (`:72`) — **tak ada throttle untuk login POST**. Login kandidat/portal juga polos. | ✅ Valid |
| Anti-spoof absensi kuat | **Dikonfirmasi + diperluas.** Bukan cuma absensi — SELURUH portal bebas IDOR (lihat SEC-KUAT-1). | ✅ Diperkuat |
| Mass-assignment aman | **Dikonfirmasi + diperluas.** `profileUpdate` pakai allowlist eksplisit, bukan `$request->all()` (lihat SEC-KUAT-2). | ✅ Diperkuat |

### Temuan positif tambahan (hasil re-review)

| Kode | Aspek | Bukti |
|---|---|---|
| **SEC-KUAT-1** | **Nol IDOR di seluruh portal `/my`.** Tiap resource di-scope ke pemilik lewat sesi, bukan menerima id mentah. Menebak id orang lain → `404`, bukan bocor. | `PortalController.php` — `ownedSalaryRecap()` (`SalaryRecap::where('id',$id)->where('user_id',$me->id)->firstOrFail()`), leave `:151,217`, notification `:320`, loan `:236-237`; `TrainingPortalController::ownedEnrollment()` dipakai di `submit()`/`result()`/`quiz()` |
| **SEC-KUAT-2** | **Allowlist mass-assignment di `profileUpdate` — anti privilege-escalation.** Karyawan hanya bisa ubah `phone/address/email/image`; `role/salary/employment_status` tak pernah masuk array update. | `PortalController.php:265-269` (komentar eksplisit `:263-264`: "salary, department and employment status stay with HR") |
| **SEC-KUAT-3** | **Pemisahan guard berlapis.** Kandidat karier di guard `candidate` yang terpisah total dari guard `backpack` (admin/karyawan) — kompromi satu guard tak menyentuh yang lain. | `CandidateAuthController.php` (`Auth::guard('candidate')`), `routes/web.php:56` |

> **Kesimpulan self-review:** postur keamanan RitmeHR di jalur data-akses (authz, IDOR,
> mass-assignment, injection) **di atas rata-rata** untuk aplikasi Laravel se-usia ini.
> Titik lemahnya bukan di *logika akses* melainkan di *perimeter & operasional*:
> rate-limit (SEC-1), kesiapan config produksi (SEC-2/BP-1), dan async (PERF-1). Ini
> justru kabar baik — memperbaiki 3 hal itu jauh lebih murah daripada membetulkan authz
> yang bocor di banyak tempat.

---

## 1. Keamanan (Security)

### Yang sudah kuat ✅
| Aspek | Bukti |
|---|---|
| Anti-spoof absensi: identitas dari sesi, bukan request | `PortalAttendanceController.php:98` — `$user = $this->me()` (`backpack_user()`), komentar eksplisit di `:20-22` |
| Foto selfie (data pribadi) di-stream dengan access-check, bukan file statis | `PortalAttendanceController.php:193-206` — `abort_unless($owns || $canView, 403)` |
| Upload CV publik tervalidasi ketat + disk privat | `CareerController.php:69` — `mimes:pdf,doc,docx\|max:5120`, `:77` store ke `local` |
| Nol permukaan SQL injection | 19 raw SQL semuanya agregasi/konstanta (`selectRaw('SUM(amount)')`, `whereRaw('1 = 0')`), tanpa interpolasi input |
| Proses eksternal aman dari shell injection | `CvExtractionService.php:70` — `new Process([$python, $script, $absolutePath])` (argumen array) |
| Tidak ada `eval`/`unserialize`/`dd()`/`shell_exec` liar | grep: 0 hit |
| Password: hash via cast, cek `current_password`, `min:8|confirmed` | `PortalController.php:288-301`, `CandidateAuthController.php:31` |
| `$hidden` pada `User` & `Candidate` (password tak bocor di serialisasi) | `User.php:57`, `Candidate.php:25` |
| Kredensial tak masuk repo | `.env` di-`.gitignore` (`.gitignore:8-12`), `git ls-files` → 0 |
| Ganti sesi setelah login (anti session-fixation) | `CandidateAuthController.php:78` — `session()->regenerate()` |

### Temuan
| # | Severity | Temuan | Lokasi | Rekomendasi |
|---|---|---|---|---|
| SEC-1 | 🔴 Kritis | **Login KANDIDAT (`career`) & ganti-password portal tak di-throttle.** `RateLimiter::for` hanya untuk `'api'` (`RouteServiceProvider.php:27`); rute `career/masuk`, `career/daftar`, `/my/password` polos → brute-force. **CATATAN (revisi): login ADMIN Backpack SUDAH aman** — `AuthenticatesUsers` memakai trait `ThrottlesLogins` (5 attempt/menit, terbukti runtime). Jadi lubang nyata hanya di sisi career/portal. | `routes/web.php:41-43,97` (career) — **admin sudah OK** via `vendor/backpack/.../ThrottlesLogins.php` | Tambah `throttle:5,1` pada rute career + `/my/password`. **Sudah dikerjakan → QW-03 DONE.** Sisi admin tak perlu (QW-07 = no-op, terverifikasi). |
| SEC-2 | 🟠 Tinggi | **`APP_DEBUG=true`, `APP_ENV=local`, `LOG_LEVEL=debug`** — kalau ter-deploy apa adanya, stack trace & query bocor ke user. | `.env` | Checklist rilis: `APP_ENV=production`, `APP_DEBUG=false`, `LOG_LEVEL=warning`. Sudah benar untuk dev; jadikan gate deploy. |
| SEC-3 | 🟡 Sedang | Kebijakan password minimal `min:8` saja, tanpa cek kompleksitas/kebocoran. | `PortalController.php:288`, `CandidateAuthController.php:31` | Pakai `Password::min(8)->mixedCase()->numbers()->uncompromised()` (rule bawaan Laravel). |
| SEC-4 | 🟡 Sedang | `changePassword` mengembalikan `back()->with('error', ...)` saat password lama salah — pesan lewat flash, bukan `withErrors`; mudah tak terlihat & tak konsisten dgn form lain. | `PortalController.php:298` | Ganti ke `throw ValidationException` / `withErrors(['current_password'=>...])`. |
| SEC-5 | 🟢 Rendah | Sanctum terpasang tapi `routes/api.php` praktis kosong (hanya `/user`). Permukaan tak terpakai. | `routes/api.php` | Hapus jika tak dipakai, atau dokumentasikan rencananya. |

---

## 2. Optimasi (Performance)

### Yang sudah kuat ✅
| Aspek | Bukti |
|---|---|
| `setting()` (dipanggil 62×) di-cache selamanya, tak hit DB per-panggil | `SettingService.php:292` — `Cache::rememberForever(self::CACHE_KEY, …)`, invalidate di `:384` |
| Eager-loading konsisten → mitigasi N+1 | 81 `with(...)` di seluruh `app/` |
| Paginasi dipakai di listing berat | `PortalController.php:313` `paginate(25)`, 6 `paginate()` di controller |
| Index/foreign key di migrasi | 26 migrasi memakai `index()/unique()/foreignId/foreign()` |

### Temuan
| # | Severity | Temuan | Lokasi | Rekomendasi |
|---|---|---|---|---|
| PERF-1 | 🟠 Tinggi | **Ekstraksi CV (spawn proses Python, timeout 30 dtk) jalan SINKRON di dalam request lamaran publik.** `QUEUE_CONNECTION=sync` + `extractFor()` dipanggil langsung → pelamar menunggu Python selesai; rawan timeout & vektor DoS (banyak upload). 0 job `ShouldQueue` di seluruh app. | `CareerController.php:93` → `CvExtractionService::extractFor` (`:27-40`) → `runExtractor` (`:64-86`, `setTimeout(30)`) | Pindah ke Job `implements ShouldQueue` (`dispatch(new ExtractCvJob($applicant))`), set `QUEUE_CONNECTION=database/redis` + worker. Response lamaran instan, ekstraksi async. |
| PERF-2 | 🟠 Tinggi | **`env()` di runtime** (10×) — selain jebakan config-cache (BP-1), tiap call baca disk `.env` bila cache mati → lambat + tak ter-cache. | `QdrantService.php:18,23`, `LlmScoringManager.php:40,47`, `EmbeddingManager.php:34,41`, `CvExtractionService.php:94`, `TransactionService.php:30`, `Acc.php:21-22` | Pindahkan ke `config/services.php`/`config/*.php`, panggil `config('...')`. Aman untuk `config:cache`. |
| PERF-3 | 🟡 Sedang | Subquery korelasi di `LoanRepository` — `SELECT SUM(...) ... WHERE user_id = users.id` di dalam `selectRaw` → O(n) subquery per baris pada list besar. | `LoanRepository.php:15-24` | Ganti jadi `leftJoin` + `groupBy`, atau kolom agregat ter-cache. Aman untuk skala sekarang, jadi masalah saat data membesar. |
| PERF-4 | 🟡 Sedang | Driver produksi belum siap skala horizontal: `CACHE_DRIVER=file`, `SESSION_DRIVER=file`, `QUEUE_CONNECTION=sync`. | `.env` | Untuk multi-node: Redis (cache+session+queue). Single-node saat ini OK. |

### 2a. Beban CPU per-request: Export & Table (audit lanjutan)

> Pertanyaan Capt: *"apakah setiap request export dan table sudah optimal dari sisi CPU?"*
> **Jawaban singkat: Table (listing) sudah baik; Export BELUM optimal.** Bukti per-jalur:

**✅ Table / listing (Backpack DataTable) — sudah efisien.**
| Aspek | Bukti |
|---|---|
| Eager-load relasi di setiap list → cegah N+1 saat render baris | `addClause('with', …)` di ≥10 CrudController: `PresenceCrudController.php:48`, `LoanCrudController.php:68`, `SalaryRecapCrudController.php:59`, `LeaveRequestCrudController.php:34` (`user,leaveType,approval`), `LeaveBalanceCrudController.php:77`, dst. |
| Paginasi server-side + filter → Backpack `POST /search` hanya ambil 1 halaman | mekanik bawaan DataTable, bukan `->get()` semua |
| Closure column berat (QR generate + `saveQuietly`) ada di **Show**, bukan **List** | `UserCrudController.php:88-102` di dalam `setupShowOperation()` (`:66 autoSetupShowOperation`) → hanya 1 baris/request, bukan N |

**⚠️ Export (Excel) — tiga titik boros CPU/RAM yang perlu dibenahi:**

| # | Severity | Temuan | Lokasi | Rekomendasi |
|---|---|---|---|---|
| PERF-5 | 🟠 Tinggi | **N+1 di dalam loop export gaji.** `export()` hanya eager-load `with(['user'])`, tetapi `SalaryRecapExport::map()` mengakses `$row->user->salary->fine_type` → relasi `salary` di-lazy-load **satu query per baris**. Untuk N recap = N query tambahan + overhead hydrasi model saat file dibangun (semua di CPU request). | `SalaryRecapCrudController.php:258` (`with(['user'])`) vs `SalaryRecapExport.php:80` (`$row->user->salary->...`) | Ubah eager-load jadi `with(['user.salary'])` di `export()` (method `print()` di `:273-275` sudah benar melakukannya — samakan). Ini quick win murni satu baris. |
| PERF-6 | 🟠 Tinggi | **`UserExport` = `User::all()` — tanpa filter, tanpa `select`, tanpa chunk.** Seluruh baris + SELURUH kolom (termasuk `password` hash, `remember_token`) dimuat ke Collection RAM sekaligus lalu di-serialize. Pada ratusan/ribuan karyawan → lonjakan memori + CPU, dan **membocorkan kolom sensitif ke file Excel**. | `UserExport.php:15` (`User::all()`), dipanggil `UserCrudController.php:423` | (a) `FromCollection` → **`FromQuery` + `WithChunkReading`** (proses per-1000, memori rata). (b) `select([...])` kolom yang perlu saja — jangan sertakan password/token. (c) Untuk data besar: `implements ShouldQueue` + kirim link unduh. |
| PERF-7 | 🟡 Sedang | **Semua Export pakai `FromCollection` (materialisasi penuh di RAM), bukan `FromQuery`/chunk.** `SalaryRecapExport`, `LoanExport`, `UserExport` menahan seluruh hasil di memori + membangun sheet dalam satu proses sinkron (`QUEUE_CONNECTION=sync`) → CPU request spike sebanding jumlah baris. Aman di data kecil, jadi masalah saat tumbuh. | `SalaryRecapExport.php:13`, `LoanExport.php:10`, `UserExport.php:8` | Migrasi ke `FromQuery` + `WithChunkReading` (1000/chunk). Untuk export besar & berulang: jadikan `ShouldQueue` (sejalan dgn PERF-1) agar request HTTP tak menahan pembangunan file. |

> **Ringkas CPU-per-request:** listing sudah di jalur yang benar (eager-load + paginasi
> server-side, closure berat hanya di Show). Yang belum optimal adalah **export**: satu
> N+1 nyata (PERF-5), satu dump tanpa batas + bocor kolom sensitif (PERF-6), dan pola
> `FromCollection`-sinkron yang tidak menskala (PERF-7). Ketiganya effort rendah–sedang
> dan tak menyentuh logika bisnis.

---

## 3. Best Practice (Kualitas Kode)

### Yang sudah kuat ✅
| Aspek | Bukti |
|---|---|
| Lapisan arsitektur bersih | 19 Service, Repository, Observer, Notification, `Support/` |
| Mass-assignment aman | `$fillable` di 53/55 model, **nol** `guarded=[]`, tak ada `create($request->all())` di flow publik |
| Form Request untuk entitas inti | 12 file di `app/Http/Requests/` (User, Salary, Loan, Presence, dst.) |
| Cakupan test tinggi | 51 file `*Test.php` + harness browser `tests/browser/*.mjs` |
| Validasi input konsisten | Tiap endpoint publik `->validate([...])` dgn pesan Bahasa |
| Logging terkanal | `Log::channel('daily_log')` di jalur error CV |

### Temuan
| # | Severity | Temuan | Lokasi | Rekomendasi |
|---|---|---|---|---|
| BP-1 | 🟠 Tinggi | **`env()` di luar `config/` merusak `config:cache`.** Begitu `php artisan config:cache` jalan di produksi, semua `env()` di service mengembalikan `null` → Qdrant/LLM/Embedding/Acc/Python-bin diam-diam mati. | 10 lokasi (lihat PERF-2) | Sama dgn PERF-2: `config('...')` only. Ini bug produksi menunggu terjadi, bukan sekadar gaya. |
| BP-2 | 🟠 Tinggi | **Tidak ada CI** (lanjutan temuan v2). 51 test + harness browser tak dijalankan otomatis per-PR → regres lolos diam-diam. | `.github/workflows` (kosong) | GitHub Actions: matrix PHP 8.2/8.3, `phpunit` tiap PR (memory flags), lint `pint`. |
| BP-3 | 🟡 Sedang | Sebagian controller (Portal/Career) validasi inline, sementara admin sudah pakai Form Request → inkonsistensi. | `PortalController.php`, `CandidateAuthController.php` | Ekstrak ke Form Request agar seragam + reusable (mis. `ChangePasswordRequest`, `CandidateRegisterRequest`). |
| BP-4 | 🟡 Sedang | `.env.example` `DB_PORT` vs docker `:3307` (temuan v2 masih relevan) — friksi onboarding. | `.env.example` vs `docker-compose.yml` | Samakan default port. |
| BP-5 | 🟢 Rendah | `LOG_LEVEL=debug` untuk lokal OK, tapi tak ada rotasi eksplisit yg didokumentasikan untuk `daily_log`. | `config/logging.php` | Pastikan `days` retention di channel `daily`. |

---

## Scorecard

| # | Lensa | Verdict | Inti | Bukti |
|---|---|---|---|---|
| 1 | Anti-spoof & authz absensi | ✅ Kuat | Identitas dari sesi, selfie ter-proteksi | `PortalAttendanceController.php:98,193` |
| 2 | Injection (SQL/shell/deserial) | ✅ Kuat | Raw SQL parameter-less, Process array-arg, no eval | grep audit |
| 3 | Rate-limit auth | ❌ Bermasalah | Nol throttle login/registrasi | `RouteServiceProvider.php:27` (api-only) |
| 4 | Kesiapan produksi (config) | ⚠️ Perlu perbaikan | `APP_DEBUG=true`, `env()` runtime, driver file | `.env`, 10× `env()` |
| 5 | Async / beban request | ⚠️ Perlu perbaikan | Ekstraksi CV sinkron di request publik | `CareerController.php:93` |
| 6 | Mass-assignment & validasi | ✅ Kuat | `$fillable` penuh, 12 Form Request, validasi tiap endpoint | `app/Models/*`, `app/Http/Requests/*` |
| 7 | N+1 & caching | ⚠️ Perlu perbaikan | Listing eager-load ✅, tapi export gaji N+1 (`user.salary` lazy) | `SalaryRecapExport.php:80` |
| 8 | Export skalabilitas CPU/RAM | ⚠️ Perlu perbaikan | Semua `FromCollection` sinkron; `UserExport=User::all()` bocor kolom sensitif | PERF-5/6/7 |
| 9 | CI / guardrail regres | ❌ Bermasalah | Tak ada workflow | `.github/workflows` kosong |

---

## Rencana Perbaikan (prioritas)

**Quick wins (jam-an):**
1. `SEC-1` — Tambah `throttle:5,1` pada rute auth + aktifkan throttle login Backpack. *(dampak keamanan tertinggi, effort rendah)*
2. `PERF-5` — `with(['user'])` → `with(['user.salary'])` di `SalaryRecapCrudController::export()`. *(hapus N+1 export, satu baris)*
3. `PERF-6` — `UserExport`: buang `User::all()`, `select` kolom aman (tanpa password/token) + filter. *(cegah bocor data + hemat RAM)*
4. `BP-1`/`PERF-2` — Pindahkan 10 `env()` → `config/*.php`, panggil `config()`. *(cegah bug produksi diam-diam)*
5. `SEC-2` — Dokumentasikan checklist rilis (`APP_DEBUG=false`, `APP_ENV=production`).
6. `SEC-3` — Naikkan rule password ke `Password::min(8)->mixedCase()->numbers()->uncompromised()`.

**Struktural (butuh test + review):**
7. `PERF-1` — Job `ShouldQueue` untuk ekstraksi CV + set queue driver. *(hilangkan blocking di request publik)*
8. `PERF-7` — Migrasi Export ke `FromQuery`+`WithChunkReading` (+`ShouldQueue` utk export besar). *(skalabilitas CPU/RAM export)*
9. `BP-2` — GitHub Actions CI (phpunit + pint per PR).
10. `PERF-3` — Refactor subquery `LoanRepository` → join+groupBy saat data membesar.
11. `BP-3` — Seragamkan validasi ke Form Request di Portal/Career.

> Tiap perbaikan sudah di-breakdown per-file (1 task = 1 file) di
> **[rencana-perbaikan.md](rencana-perbaikan.md)** + folder `tasks/` dengan flag
> `[ ]TODO/[x]DONE`, line nyata, diff konkret, dan bagian Verifikasi. Wajib test +
> verifikasi UI sebelum flip DONE.

---

## Catatan metodologi
Audit ini berbasis pembacaan kode langsung (controller, service, route, config, migrasi)
dan pemetaan pola via grep — **belum** menjalankan pentest dinamis (mis. mencoba brute-force
nyata) atau profiling beban. Temuan `🔴/🟠` di atas cukup jelas dari kode; verifikasi
dinamis (load test untuk PERF-1, uji throttle untuk SEC-1) direkomendasikan sebagai langkah
lanjut sebelum rilis produksi.
