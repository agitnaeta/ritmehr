# Rencana Perbaikan — RitmeHR Versi 1 (Breakdown per File)

Setiap item perbaikan dipecah **per file** agar bisa di-flag DONE satu per satu.
Detail tiap task ada di folder [`tasks/`](tasks/). **Index checklist di sini = sumber kebenaran progres.**

Cara flag DONE:
1. Kerjakan file sesuai task-nya di `tasks/<ID>.md`.
2. Ubah `Status:` di dalam file task jadi `[x] DONE` + isi commit SHA.
3. Centang baris di tabel bawah ini.

---

## Progres

**Quick wins** (jam–hari, tanpa desain baru) — ✅ **SELESAI & terverifikasi** (392 phpunit + 146 browser hijau)

| ✔ | ID | File yang disentuh | Menutup | Prioritas |
|---|---|---|---|---|
| [x] | [QW-01](tasks/QW-01-CompanyProfileCrudController.md) | `app/Http/Controllers/Admin/CompanyProfileCrudController.php` | RV1-006 typo | P2 |
| [x] | [QW-02](tasks/QW-02-SalaryCrudController-list.md) | `app/Http/Controllers/Admin/SalaryCrudController.php` | RV1-003, RV1-004 | P1 |
| [x] | [QW-03](tasks/QW-03-UserCrudController-labels.md) | `app/Http/Controllers/Admin/UserCrudController.php` | RV1-005 label EN | P2 |
| [x] | [QW-04](tasks/QW-04-dashboard-emptystate.md) | `resources/views/admin/dashboard.blade.php` + `DashboardController.php` (+2 test) | Lensa 1 empty-state | P1 |

**Struktural — Setup Wizard** (menutup RV1-001, lensa 2) — 🎨 desain: [`mockup/setup-wizard.html`](mockup/setup-wizard.html)

| ✔ | ID | File yang disentuh | Jenis |
|---|---|---|---|
| [ ] | [WIZ-01](tasks/WIZ-01-routes.md) | `routes/backpack/custom.php` | ubah |
| [ ] | [WIZ-02](tasks/WIZ-02-SetupWizardController.md) | `app/Http/Controllers/Admin/SetupWizardController.php` | baru |
| [ ] | [WIZ-03](tasks/WIZ-03-OnboardingService.md) | `app/Services/OnboardingService.php` | baru |
| [ ] | [WIZ-04](tasks/WIZ-04-wizard-views.md) | `resources/views/admin/setup/*.blade.php` (5 file) | baru |

**Struktural — Import Excel Karyawan & Gaji** (menutup RV1-002, lensa 4) — 🎨 desain: [`mockup/import.html`](mockup/import.html)

| ✔ | ID | File yang disentuh | Jenis |
|---|---|---|---|
| [ ] | [IMP-01](tasks/IMP-01-UserImport.md) | `app/Imports/UserImport.php` | baru |
| [ ] | [IMP-02](tasks/IMP-02-SalaryImport.md) | `app/Imports/SalaryImport.php` | baru |
| [ ] | [IMP-03](tasks/IMP-03-UserCrud-import-op.md) | `app/Http/Controllers/Admin/UserCrudController.php` + route | ubah |
| [ ] | [IMP-04](tasks/IMP-04-SalaryCrud-import-op.md) | `app/Http/Controllers/Admin/SalaryCrudController.php` + route | ubah |
| [ ] | [IMP-05](tasks/IMP-05-templates.md) | `resources/views/admin/import/*` + `app/Exports/*TemplateExport.php` | baru |

> **Desain UI sudah dibuat & di-review** (mockup HTML Tabler, screenshot bukti di `mockup/preview-*.png`). Tiap task WIZ/IMP kini merujuk mockup + memakai field DB aktual, dengan checklist "Cek per file" di masing-masing task.

---

## Urutan eksekusi (impact-first)

| Prioritas | Kerjakan | Alasan |
|---|---|---|
| **P0** | IMP-01…05 (Import Excel) | Buka go-live tanpa re-entry manual — ROI tercepat |
| **P0** | WIZ-01…04 (Setup Wizard) | Adopsi mandiri, kurangi biaya onboarding |
| **P1** | QW-02, QW-04 | Keterbacaan modul gaji + orientasi user baru |
| **P2** | QW-03, QW-01 | Konsistensi bahasa + kredibilitas |

## Catatan

- File yang sama bisa muncul di dua task berbeda (mis. `UserCrudController.php` di QW-03 & IMP-03) karena **konsen berbeda**; flag tiap task terpisah.
- Setiap task punya bagian **Verifikasi** — jalankan sebelum flag DONE.
- Baseline regresi yang HARUS tetap hijau: `crud-suite.mjs` (146), `phpunit` (390). Jangan sampai perbaikan merusak ini.
