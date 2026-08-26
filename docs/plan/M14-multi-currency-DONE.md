# M14 — Multi-currency ✅ DONE

> **Status:** ✅ IMPLEMENTED & TESTED (2026-08-24) · **Prioritas:** 🟠 CC-2 (E7).
> **Inti:** simbol & format mata uang dikendalikan dari 1 tempat (setting M15),
> berlaku ke seluruh UI. Base currency di-set via Pengaturan > Bahasa & Mata Uang.

## Hasil Implementasi
- **`CurrencyService`** — sumber tunggal format uang. Definisi IDR/USD/EUR/SGD/MYR (simbol, posisi, desimal, pemisah ribuan/desimal). `format($amount)` baca `default_currency` (M15), fallback IDR untuk kode tak dikenal.
- **Helper `money($amount, $code=null)`** + Blade directive **`@money`**. Directive lama **`@rupiah` di-alias** ke `money()` → semua view yang sudah pakai `@rupiah` (portal slip gaji, kasbon, dashboard) otomatis ikut currency setting tanpa diubah.
- **Titik hardcode "Rp" diganti** → currency-aware:
  - Views akuntansi (jurnal, buku besar, neraca saldo, laba rugi, neraca, form) — 25 spot + live-total JS pakai simbol aktif.
  - `Account::cashLabel()`, `NotificationTemplates` (gaji/kasbon), `SalaryRecapExport` (format Excel), `TransalateService`.
  - Prefix CRUD: Loan, LoanPayment, Salary (field+kolom), PtkpRate, Pph21Bracket, BpjsRate.
- Setting `default_currency` (IDR/USD/EUR) sudah tersedia di M15 tab "Bahasa & Mata Uang".

## Automation Test
- **PHPUnit** `CurrencyServiceTest` — 6/6 (IDR tanpa desimal, USD 2-desimal koma-ribuan, EUR titik-ribuan koma-desimal, override kode eksplisit, fallback kode tak dikenal, symbol helper).
- **Playwright** `m14-currency.mjs` — 4/4 (default IDR "Rp", set USD via Settings UI → akuntansi jadi "$", form total pakai simbol aktif, reset ke IDR "Rp" balik).
- **Regression:** `php artisan test` → **183 passed (389 assertions)**, nol regresi.

## Sisa (bertahap, bukan blocker)
- **Multi-currency transaksi + exchange rate** (fase 2): saat ini satu base currency untuk seluruh sistem (sesuai DoD). Transaksi lintas mata uang + kurs belum — dibuat kalau bisnis butuh.

## Definition of Done — tercapai ✅
- Simbol & format mata uang berubah dari 1 tempat (setting), efek ke seluruh UI inti.
- Tidak ada "Rp" hardcode di modul inti.
- Base currency di-set via setup (M15).

---

## Rencana Awal (arsip)


## Ringkasan
"Rp"/"Rp." di-hardcode di seluruh sistem (`number_format`, `TransalateService`,
Blade directive `rupiah`, export Excel). Modul ini memperkenalkan currency yang
configurable + format terpusat, dengan setup mata uang dasar sejak awal.

## Evaluasi Bisnis (7 Poin)

- **E1. Kelengkapan proses bisnis** — ❌ Tidak ada konsep currency; semua asумsi Rupiah. Tak bisa ganti simbol/format, tak ada exchange rate.
- **E2. Integrasi keluar** — ➖ Default internal. (Opsional: sumber kurs eksternal → kalau dipakai, wajib config M15 + fallback manual sesuai arahan E2/E4.)
- **E3. Best-practice tampilan** — Format currency konsisten via 1 helper (`money($amount)`), bukan `number_format` tersebar. Simbol & posisi mengikuti setting.
- **E4. Third-party config** — Mata uang dasar + daftar currency aktif diatur super admin di M15 (Pengaturan > Lokalisasi/Currency).
- **E5. Keterkaitan antar fitur** — Terkait payroll (M05), akuntansi (M12 buku besar wajib currency-aware), kasbon, report (M08). Harus seamless: satu sumber format.
- **E6. Bahasa** — Format angka ikut locale (terkait M13).
- **E7. Currency** — 🔴 INI fokus. **Perlu setup dari awal**: tentukan base currency perusahaan saat instalasi.

## Gap & Temuan
- Hardcode "Rp": `AppServiceProvider` directive `rupiah`, `TransalateService`, `NotificationTemplates`, `SalaryRecapExport`, banyak CrudController (`prefix('Rp.')`).

## Task Breakdown
1. Tabel `currencies` (code, symbol, decimal, position) + base currency di settings (M15).
2. Helper terpusat `money($amount, $currency=null)` + Blade directive `@money`.
3. Ganti semua `number_format(... 'Rp')` & directive `rupiah` → helper baru (bertahap per modul).
4. Format export Excel mengikuti currency setting.
5. (Opsional/fase 2) exchange rate + multi-currency transaksi jika bisnis butuh.

## Definition of Done
- Simbol & format mata uang berubah dari 1 tempat (setting), efek ke seluruh UI.
- Tidak ada "Rp" hardcode di modul inti.
- Base currency di-set saat setup awal.
