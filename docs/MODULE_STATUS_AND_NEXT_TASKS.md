# Absensi → HRIS — Module Status & Next Tasks

> **Audit date:** 2026-08-24
> **Method:** Verifikasi langsung ke kode (models, migrations, services, controllers,
> routes, observers, scheduler, tests) — bukan sekadar baca label di `MODULE_PLANS.md`.
> **Legend:** ✅ Done & wired · ⚠️ Ada tapi ada gap · ❌ Belum ada (next task)

---

## Lensa Analisis: "Bisa Dipakai End-to-End?" (bukan cuma "kode ada?")

Pertanyaan Capt yang benar: modul yang punya model+controller+route belum tentu
BISA DIPAKAI. Yang menentukan usable/tidak adalah **apakah proses bisnisnya bisa
diselesaikan lewat UI dari awal sampai akhir**: ada jalur input data, data ngalir
ke tempat konsumsinya, dan entry point-nya keliatan di menu.

Tiga lubang yang dicek untuk tiap modul:
1. **Config ter-seed?** — kalau tabel referensi kosong, fitur mati diam-diam.
2. **Ada entry point operasional di menu sidebar?** — bukan cuma route telanjang.
3. **Data ngalir ke konsumennya?** — input → proses → output kepakai.

### ⚠️ Soal "Akuntansi cuma ada pengaturannya" — ini BY DESIGN, bukan bug

Absensi **bukan** aplikasi akuntansi. Menu "Konfigurasi Akuntansi" (ACC) cuma
memetakan kode transaksi (`GAJIAN`, `KASBON`, `BAYARKASBON`) ke akun sumber/tujuan
di sistem akuntansi **eksternal (Firefly III)** via API (`/api/v1/transactions`).

**Cara data akuntansi masuk:** OTOMATIS. Saat gaji dibayar (`setPayment`) atau
kasbon dibuat/dibayar, `TransactionService` push transaksi ke Firefly III. Jadi
wajar UI-nya cuma setting — transaksinya dilihat DI FIREFLY, bukan di absensi.

**Syarat aktif:** `env('ACC_ACTIVE')=true` + `ACC_HOST` + `ACC_KEY` terisi. Kalau
tidak, `TransactionService` diam-diam no-op (semua method `if(!$this->active) return`).
> **Catatan usability:** karena no-op-nya SILENT, admin bisa ngira gaji "tercatat
> ke akuntansi" padahal ACC nonaktif. Tidak ada indikator status di UI. Minor, tapi
> membingungkan — persis yang Capt rasakan.

### ⚠️ Onboarding gap: `DatabaseSeeder` KOSONG

`database/seeders/DatabaseSeeder.php::run()` kosong. Semua data referensi HRIS
(role, permission, approval flow, jenis cuti, jenis dokumen, tarif pajak/BPJS)
ada di `HrisSeeder` tapi **tidak dipanggil otomatis**. Fresh install harus jalan
manual: `php artisan db:seed --class=HrisSeeder`. Kalau lupa → tidak ada role sama
sekali → tidak ada yang bisa login sebagai admin → semua menu (yang di-gate `@can`)
hilang. Ini bikin sistem "kelihatan kosong" padahal kodenya lengkap.

---

## Ringkasan Cepat

| Modul | Status | Catatan |
|-------|--------|---------|
| M0 Foundation | ✅ | Roles, Approval engine, Audit trail — semua wired |
| M1 Org Structure | ✅ | Department, Position, org-chart |
| M2 Leave Management | ✅ | Terintegrasi ke perhitungan gaji |
| M3 Notification | ✅ | DB + WhatsApp gateway + scheduler |
| M4 Self-Service Portal | ✅ | Route `/my/*` lengkap |
| M5 Tax & BPJS | ⚠️ | Service lengkap, TAPI tidak auto-jalan saat rekap gaji dihitung |
| M6 Employee Documents | ✅ | Upload, expiry alert, completeness |
| M7 Multi-Branch | ✅ | Geofencing per-branch |
| M8 Reporting & Dashboard | ✅ | Dashboard + report attendance/salary/loan/headcount |
| M9 Recruitment | ❌ | Belum ada (optional, LOW) |
| M10 Performance | ❌ | Belum ada (optional, LOW) |
| M11 Training | ❌ | Belum ada (optional, LOW) |

---

## Verdict MVP Proses-Bisnis per Modul (bisa dipakai end-to-end?)

| Modul | Config seed | Entry point menu | Data ngalir | MVP usable? |
|-------|:-----------:|:----------------:|:-----------:|:-----------:|
| M1 Org | — (manual isi) | ✅ Organisasi | ✅ dipakai approval & report | ✅ YA |
| M2 Leave | ✅ LeaveTypeSeeder | ✅ Cuti & Izin | ✅ approve→saldo→gaji | ✅ YA |
| M3 Notif | — | ✅ bell + portal | ✅ di-trigger event nyata* | ✅ YA |
| M4 Portal | — | ✅ `/my/*` navbar | ✅ baca data karyawan | ✅ YA |
| M5 Tax | ✅ TaxRateSeeder | ✅ Pajak & BPJS | ⚠️ **manual recalc** | ⚠️ SEBAGIAN |
| M6 Docs | ✅ DocTypeSeeder | ✅ Dokumen | ✅ upload→simpan→download | ✅ YA |
| M7 Branch | — (manual isi) | ✅ Organisasi>Cabang | ✅ user+scan+dashboard | ✅ YA |
| M8 Report | — | ✅ Dashboard+report | ✅ agregasi data nyata | ✅ YA |

\* Notifikasi ter-trigger dari: leave approve/reject (`LeaveService::notifyOutcome`),
approval step (`ApprovalService`), dokumen mau expired (`DocumentService`), scheduler
absensi & digest. Bukan service nganggur.

### Kesimpulan usability
- **7 dari 8 modul wajib SUDAH usable end-to-end** lewat UI. Ada tabel input,
  data ngalir ke konsumen, menu keliatan.
- **Cuma M5 (Pajak) yang setengah jalan**: semua UI & service ada, tapi angka pajak
  baru nempel ke slip kalau admin klik "Rekap Pajak → hitung ulang" manual. Recap
  gaji biasa TIDAK otomatis punya PPh21/BPJS/net. Ini gap MVP nyata (lihat M5-1).
- **Akuntansi (ACC)**: kekhawatiran "cuma pengaturan" itu wajar — memang integrasi
  ke Firefly III eksternal, datanya masuk otomatis saat gajian/kasbon. Bukan modul
  yang perlu UI input transaksi sendiri. Satu-satunya perbaikan: kasih indikator
  status ACC aktif/nonaktif biar admin nggak bingung (lihat ACC-1).
- **Blocker onboarding**: `DatabaseSeeder` kosong → fresh install kelihatan "mati"
  kalau lupa seed manual (lihat SETUP-1).



### ✅ M1 — Organization Structure — DONE
- Models: `Department`, `Position` + migration `..._create_departments_and_positions_tables`
- Controllers: `DepartmentCrudController`, `PositionCrudController`
- Routes: `admin/department`, `admin/position`, `admin/org-chart` (guard `permission:org.view`)
- Users extended: `department_id`, `position_id`, `employee_id`, `join_date`, `employment_status`, `phone`, `address`
- `head_user_id` di departments → dipakai approval engine
- **Tidak ada task tersisa.**

### ✅ M2 — Leave Management — DONE
- Models: `LeaveType`, `LeaveBalance`, `LeaveRequest`, `LeaveRequestDate` + migration
- Service: `LeaveService` (request, approve, reject, cancel, balance, carry-over, generateYearly)
- Integrasi gaji: `SalaryService::getAbstain()` & `deductibleAbsenceDays()` sudah kurangi approved leave; paid vs unpaid dibedakan
- Command: `leave:generate-balances` (scheduled `yearlyOn(1,1)`)
- Routes admin lengkap (leave-type, leave-balance + generate/carry-over, leave-request + approve/reject/cancel, leave-calendar, leave-report) + portal `/my/leave`
- **Tidak ada task tersisa.**

### ✅ M3 — Notification — DONE
- Models: `Notification`, `NotificationPreference`
- Service: `NotificationService` + gateway `FonnteWhatsAppGateway` / `LogWhatsAppGateway` (auto-pilih by token)
- Commands + scheduler: `notify:attendance` (checkin 08:15 / late 09:30 / checkout 17:00), `notify:approval-digest` (Senin 08:00)
- Routes: bell icon `admin/notification/*` + portal `/my/notifications`
- **Tidak ada task tersisa.**

### ✅ M4 — Employee Self-Service Portal — DONE
- Controller: `Portal\PortalController` + middleware `EnsurePortalAccess`
- Routes `/my/*`: dashboard, attendance, salary (index+show), leave (index/create/store/cancel), loan, profile (+update), password, notifications
- **Tidak ada task tersisa.**

### ⚠️ M5 — Tax & BPJS — DONE tapi ADA GAP
- Models: `EmployeeTaxProfile`, `PtkpRate`, `Pph21Bracket`, `BpjsRate` + kolom pajak di `salary_recaps`
- Service: `TaxService` lengkap (PPh21 TER, BPJS 5 komponen, THR prorata, PTKP, brackets, report tahunan & BPJS)
- Controllers/routes: `tax-profile`, `ptkp-rate`, `pph21-bracket`, `bpjs-rate`, `tax-report/annual|bpjs|recalculate`
- **GAP:** `TaxService::applyToRecap()` (yang isi `pph21`, `bpjs_*`, `net_income`) TIDAK dipanggil dari `SalaryService::calculateSalaryRecap()` maupun `SalaryRecapObserver`. Hanya dipanggil manual via tombol `tax-report/recalculate`, seeder, dan test.
- **Efek:** kolom pajak & net_income sebuah recap baru terisi hanya setelah admin klik "hitung ulang pajak". Recap baru/di-update TIDAK otomatis punya pajak.

> **⏭️ NEXT TASK M5-1 — Wire-in pajak ke flow gaji otomatis**
> Panggil `TaxService::applyToRecap($recap)` setelah `calculateSalaryRecap()` selesai (di dalam `SalaryService` atau di `SalaryRecapObserver::updated/created`), pakai `saveQuietly` supaya tidak recurse.
> - Keputusan desain dulu: pajak dihitung otomatis tiap recap, ATAU tetap manual (pisah dari gaji kotor)?
> - Kalau otomatis: hati-hati loop observer + urutan (pajak butuh gaji final dulu).
> - Tambah test: recap baru → cek `net_income` & `pph21` terisi tanpa klik manual.

### ✅ M6 — Employee Documents — DONE
- Models: `DocumentType`, `EmployeeDocument` + migration
- Service: `DocumentService`; Controller: `EmployeeDocumentController`, `DocumentTypeCrudController`
- Routes: document-type CRUD + employee-document (index/create/store/completeness/download/destroy), guard `permission:document.view`
- Command + scheduler: `documents:notify-expiring --days=30` (Senin 07:30)
- **Tidak ada task tersisa.**

### ✅ M7 — Multi-Branch — DONE
- Model: `Branch` + migration; `branch_id` di `users` & `presences`
- Geofencing per-branch: `PresenceService` pakai `branch->radius_meters` + koordinat branch, fallback ke `config('app.office_lat/lng')`
- Route: `admin/branch` (guard `permission:branch.view`)
- **Catatan:** cek apakah `BranchScope` global auto-filter sudah diterapkan sesuai plan (invasiveness HIGH). Kalau reporting/list belum ter-scope per cabang, ini kandidat polish — tapi core sudah ada.
- **Tidak ada task kritis tersisa.**

### ✅ M8 — Reporting & Dashboard — DONE
- Controller: `DashboardController` + `DashboardService`; override dashboard Backpack
- Routes report: `attendance`, `salary`, `loan`, `headcount` + `TaxReportController` (annual/bpjs)
- **Catatan (minor):** plan sebut report `leave`, `tax`, `bpjs` di bawah `/admin/report/*`. Report leave ada di `leave-report` (bukan di grup report), tax/bpjs di `tax-report`. Fungsional ada, cuma beda lokasi route. Bukan blocker.
- **Tidak ada task kritis tersisa.**

---

## Modul Belum Diimplementasi (Next Tasks — semua OPTIONAL / LOW priority)

### ❌ M9 — Recruitment — NEXT TASK (opsional)
Belum ada `JobPosting`, `Applicant`, `Interview` (model/migration/controller nihil).
> **⏭️ M9-1:** Bikin pipeline dasar: job posting → applicant → interview → hire → auto-create user.
> Prioritas LOW — biasanya pakai tools terpisah (LinkedIn/JobStreet). Kerjakan hanya kalau ada kebutuhan bisnis.

### ❌ M10 — Performance Management — NEXT TASK (opsional)
Belum ada `ReviewPeriod`, `Kpi`, `PerformanceReview`.
> **⏭️ M10-1:** KPI setting → periodic review → rating.
> Prioritas LOW.

### ❌ M11 — Training & Development — NEXT TASK (opsional)
Belum ada `Training`, `TrainingParticipant`, `Certification`.
> **⏭️ M11-1:** Track training history, sertifikasi, skills.
> Prioritas LOW.

---

## Urutan Kerja yang Disarankan

```
PRIORITAS 1 (gap MVP / usability nyata di modul yang sudah "done"):
  M5-1     Wire-in TaxService::applyToRecap() ke flow gaji otomatis   ← paling penting
  SETUP-1  Panggil HrisSeeder dari DatabaseSeeder (fresh install auto punya role/data)
  ACC-1    Indikator status ACC aktif/nonaktif di UI (hindari "silent no-op")

PRIORITAS 2 (polish, kalau sempat):
  M7    Verifikasi BranchScope global auto-filter per cabang
  M8    Rapikan lokasi route report (leave/tax/bpjs) ke /admin/report/*

PRIORITAS 3 (fitur baru, optional, sesuai kebutuhan bisnis):
  M9    Recruitment
  M10   Performance
  M11   Training
```

**Kesimpulan:** Semua modul WAJIB (M0–M8) sudah terimplementasi & wired, dan 7 dari 8
sudah **usable end-to-end** lewat UI. Gap MVP nyata cuma di **M5 (pajak tidak
auto-hitung)**. Dua isu setup/usability (`DatabaseSeeder` kosong + status ACC silent)
mudah diberesin dan bikin sistem terasa jauh lebih "hidup" untuk fresh install.
Sisanya (M9–M11) memang optional dan sengaja belum digarap.
