# M05 — Tax & BPJS (PPh 21) ✅ DONE

> **Status:** ✅ IMPLEMENTED & TESTED (2026-08-24) · **Prioritas:** 🔴 (urutan #2)
> **Keputusan desain (Capt):** Opsi #1 — pajak dihitung OTOMATIS tiap rekap
> dihitung/diupdate; tombol recalc manual tetap ada sebagai cadangan.

## Hasil Implementasi
- `SalaryService` sekarang inject `TaxService`; `calculateSalaryRecap()` memanggil `TaxService::applyToRecap()` di dalam DB transaction setelah gaji kotor final (saveQuietly → aman dari loop observer).
- DI di `AppServiceProvider` diperbarui (3 argumen).
- Karena `SalaryRecapObserver::created/updated` memanggil `calculateSalaryRecap()`, PPh21/BPJS/net_income kini terisi otomatis untuk rekap baru maupun yang diupdate — tanpa klik manual.
- Tombol "Rekap Pajak → hitung ulang" (`TaxReportController::recalculate`) tetap ada sebagai cadangan.
- Slip gaji portal (`portal/salary_show`) menampilkan breakdown: PPh21, BPJS Kesehatan/JHT/JP (karyawan), total potongan termasuk pajak, dan "Diterima (Net)" dari `net_income`.

## Automation Test
**PHPUnit** `tests/Feature/SalaryTaxAutoCalcTest.php` — 3/3 PASS:
- auto-calc mengisi pajak/BPJS/net TANPA langkah manual (hanya `calculateSalaryRecap`)
- net_income = gross − semua potongan karyawan
- hitung ulang idempotent

**Playwright** `tests/browser/m05-tax-payslip.mjs` — 3/3 PASS:
- TC-TAX-20 slip menampilkan baris PPh 21 & BPJS
- TC-TAX-21 slip menampilkan Diterima (Net) non-nol
- TC-TAX-22 nilai PPh 21 sesuai data tersimpan (Rp 122.300)

**Regression:** seluruh suite `php artisan test` → **153 passed (320 assertions)**, nol regresi.

## Catatan lanjutan (bukan blocker M05)
- E6 (i18n label) → ditangani M13. E7 ("Rp" hardcode di slip) → ditangani M14.

---

## Rencana Awal (arsip)

## Ringkasan
Modul pajak (PPh21 TER, BPJS 5 komponen, THR) + report tahunan/BPJS. Service &
UI lengkap, tapi `TaxService::applyToRecap()` cuma dipanggil manual.

## Evaluasi Bisnis (7 Poin)

- **E1. Kelengkapan proses bisnis** — ⚠️ CRUD rate (PTKP, PPh21 bracket, BPJS), profil pajak karyawan, report annual & BPJS SUDAH ada. TAPI angka pajak/BPJS/net baru terisi ke recap kalau admin klik "Rekap Pajak → hitung ulang" manual. Recap gaji normal tidak otomatis punya pajak → **proses bisnis payroll belum tuntas**.
- **E2. Integrasi keluar** — ➖ Perhitungan internal (`TaxService`), tak ada dependensi luar. Baik.
- **E3. Best-practice tampilan** — ⚠️ Report pajak/BPJS pakai tabel (sudah tepat untuk data numerik). Bisa ditambah ekспор Excel untuk SPT (sebagian sudah). Slip gaji karyawan (portal) perlu tampilkan breakdown pajak.
- **E4. Third-party config** — ➖ Tidak pakai third-party. Rate diatur via CRUD (bagus).
- **E5. Keterkaitan antar fitur** — 🔴 SANGAT terkait: `SalaryService` (gaji kotor) → `TaxService` (potong pajak) → `net_income` → slip (M04) → akuntansi (M12). Saat ini rantai putus di sambungan SalaryService↔TaxService.
- **E6. Bahasa** — ⚠️ Label hardcode ID. Ikut M13.
- **E7. Currency** — ⚠️ "Rp" hardcode. Ikut M14.

## Gap & Temuan
- `SalaryService::calculateSalaryRecap()` berhenti di gaji kotor; tak panggil `applyToRecap()`.
- `applyToRecap()` hanya dipanggil: `TaxReportController::recalculate()`, seeder, test.

## Task Breakdown
1. **Keputusan desain (butuh Capt):** pajak auto tiap recap ATAU tetap manual terpisah?
2. Jika auto: panggil `TaxService::applyToRecap($recap)` setelah `calculateSalaryRecap()` selesai (dalam DB transaction, `saveQuietly`, hati-hati loop observer).
3. Pastikan urutan: pajak dihitung setelah gaji final (butuh gross final dulu).
4. Tampilkan breakdown pajak/BPJS di slip gaji portal (M04) & PDF.
5. Test: recap baru → `net_income`, `pph21`, `bpjs_*` terisi tanpa klik manual.

## Definition of Done
- Recap gaji baru/di-update otomatis punya pajak & net_income benar.
- Slip gaji karyawan menampilkan potongan pajak/BPJS transparan.
- Ada test yang membuktikan auto-calc jalan.
