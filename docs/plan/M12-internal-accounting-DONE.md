# M12 — Akuntansi Internal ✅ DONE

> **Status:** ✅ IMPLEMENTED & TESTED (2026-08-24) · **Prioritas:** 🔴 (urutan #3)
> **Arahan terpenuhi:** E2 — akuntansi kini bisa dikelola sendiri (buku besar
> internal double-entry), tidak lagi wajib nyantol ke Firefly III eksternal.

## Hasil Implementasi
- **Skema double-entry:** `accounts` (chart of accounts, punya kolom `role`), `journal_entries`, `journal_lines` + model `Account`/`JournalEntry`/`JournalLine` (saldo normal-balance-aware, cek balanced).
- **Satu sumber kelola akun (refactor 2026-08-24):** modul lama `acc` (mapping "Pemetaan Transaksi"/"Konfigurasi Akuntansi") DIHAPUS. Semua pengelolaan akun kini di `/admin/account`. Tiap akun punya *peran posting* (Kas, Beban Gaji, Piutang Kasbon); aturan debit/kredit per kode (GAJIAN/KASBON/BAYARKASBON) baku di `TransactionService::resolveAccounts()` berbasis peran — tak ada lagi tabel mapping terpisah maupun bug "source_id null". Route `/admin/acc` → 404, `AccCrudController` dihapus.
- **Toggle `acc_mode`** (internal/firefly) di M15 Settings. Mode internal SELALU mencatat (bukan silent no-op); Firefly tetap di belakang toggle `acc_active`.
- **Aturan posting:** DEBIT destination, CREDIT source → selalu balanced. Idempoten pada `reference` (mis. `ABSEN-GAJIAN-12`) jadi posting ulang meng-update, bukan menduplikasi.
- **Auto-posting** dari payroll/kasbon/bayar-kasbon lewat `TransactionService` (tanpa API luar).
- **UI menu "Akuntansi":** Daftar Akun (CRUD), Jurnal (filter tanggal+akun), Buku Besar (mutasi + saldo berjalan per akun), Neraca Saldo (debit vs kredit + badge seimbang). Permission `accounting.view/edit` (super_admin + hr_admin).
- **Seeder** `ChartOfAccountsSeeder` (COA default + mapping GAJIAN/KASBON/BAYARKASBON), masuk `HrisSeeder`.

## Automation Test
**PHPUnit** `tests/Feature/InternalLedgerTest.php` — 6/6 PASS:
- binding aktif = InternalLedger
- bayar gaji → jurnal balanced, acc_id tersimpan
- saldo beban naik & kas turun dengan benar
- posting ulang idempoten (tak duplikasi)
- hapus transaksi → entry + lines terhapus
- neraca saldo seimbang; **`Http::preventStrayRequests()` membuktikan NOL panggilan keluar**

**Playwright** `tests/browser/m12-accounting.mjs` — 5/5 PASS:
- TC-ACC-30 menu + 3 akun; TC-ACC-31 jurnal ada entri GAJIAN; TC-ACC-32 buku besar mutasi+saldo; TC-ACC-33 neraca saldo SEIMBANG; TC-ACC-34 manager diblokir (403)

**Regression:** `php artisan test` → **159 passed (332 assertions)**, nol regresi.

## Catatan lanjutan (bukan blocker)
- E6 (i18n) → M13. E7 (currency-aware ledger) → M14. Firefly legacy tetap didukung via toggle.

## Penyempurnaan lanjutan (2026-08-24) — jurnal manual, penguncian & laporan standar
Menjawab 3 pertanyaan Capt (siapa boleh bikin jurnal / kelola / laporan standar):

**1. Siapa yang bisa membuat jurnal**
- Otomatis: sistem (gaji/kasbon) via `TransactionService` — tetap.
- Manual: user ber-permission `accounting.edit` bisa **Buat Jurnal Manual** untuk keperluan di luar gaji/potongan/kasbon (listrik, sewa, saldo awal, koreksi). Form multi-baris dinamis + validasi realtime "SEIMBANG" (tombol simpan terkunci sampai debit=kredit).

**2. Cara mengelolanya (update/delete)** — keputusan Capt: audit-safe
- Jurnal **manual** → boleh **edit & hapus** bebas (`JournalService::updateManual/deleteManual`).
- Jurnal **otomatis** (gaji/kasbon) → **DIKUNCI** dari edit/hapus. Koreksi hanya lewat **Jurnal Pembalik** (`reverse()`) yang membuat entri cermin (debit↔kredit), jejak audit utuh. Tidak bisa dibalik dua kali.
- Penegakan ganda: di service (`assertManual` lempar ValidationException) + UI (tombol edit/hapus hanya untuk manual; auto cuma dapat tombol "Balik").

**3. Laporan standar** — set lengkap 5 laporan:
- Jurnal (+ filter tanggal/akun & badge Manual/Auto/Pembalik), Buku Besar (mutasi + saldo berjalan), Neraca Saldo, **Laba Rugi** (pendapatan − beban = laba/rugi, filter periode), **Neraca** (Aset = Kewajiban + Ekuitas + laba berjalan, badge seimbang).

**Test tambahan:**
- PHPUnit `ManualJournalTest` — 7/7 (manual balanced, unbalanced/1-baris ditolak, edit+hapus manual, auto terkunci, reversal cermin, tak bisa balik 2×).
- Playwright `m12b-journal-mgmt.mjs` — 6/6 (buat jurnal manual via form, badge Manual, auto terkunci+tombol Balik, reversal, Laba Rugi, Neraca seimbang).
- Regression penuh: **166 passed (349 assertions)**.
- Pitfall dicatat: `@can()` di blade pakai guard `web`; admin di guard `backpack` → wajib `backpack_user()?->can()`.

---

## Rencana Awal (arsip)

## Ringkasan
Ganti ketergantungan ke **Firefly III eksternal** dengan **buku besar internal**
(chart of accounts + jurnal + transaksi) sehingga pencatatan keuangan gaji, kasbon,
dan pembayaran bisa dikelola sepenuhnya di dalam sistem tanpa API luar.

## Evaluasi Bisnis (7 Poin)

- **E1. Kelengkapan proses bisnis** — ❌ Saat ini di UI **cuma ada "Konfigurasi Akuntansi"** (mapping kode→akun). Tidak ada modul untuk melihat/menginput transaksi; semua dilempar ke Firefly. Secara MVP seharusnya bisa: lihat daftar akun, lihat jurnal transaksi (gaji/kasbon), saldo per akun, laporan sederhana. **Ini gap persis yang Capt curigai.**
- **E2. Integrasi keluar** — 🔴 GAGAL. `Acc.php` POST ke `ACC_HOST/api/v1/transactions` (Firefly). Arahan: **ubah jadi internal**. Buku besar sendiri, tak ada panggilan keluar.
- **E3. Best-practice tampilan** — Daftar transaksi (tabel + filter tanggal/akun), ringkasan saldo (cards), buku besar per akun. Laporan bisa chart.
- **E4. Third-party config** — Setelah internal, tak perlu kredensial Firefly. Toggle "mode akuntansi" (internal/eksternal-legacy) di M15 kalau mau tetap dukung Firefly opsional.
- **E5. Keterkaitan antar fitur** — 🔴 Tinggi: payroll (M05 net gaji), kasbon, pembayaran → semua auto-posting ke jurnal internal. Menu "Keuangan/Akuntansi" mengelompokkan: Akun, Transaksi, Buku Besar, Laporan.
- **E6. Bahasa** — Ikut M13.
- **E7. Currency** — 🔴 Kritis: buku besar wajib sadar currency (ikut M14). Setup awal: mata uang dasar perusahaan.

## Gap & Temuan
- `TransactionService` + `Acc\Acc` = adaptor Firefly. `AccTransaction` = payload API.
- Kode transaksi: `GAJIAN` (withdrawal), `KASBON` (withdrawal), `BAYARKASBON` (deposit).
- `env('ACC_ACTIVE')` false → semua no-op SILENT (admin tak sadar tak tercatat).

## Task Breakdown
1. Skema internal: `accounts` (chart of accounts), `journal_entries`, `journal_lines` (double-entry).
2. `LedgerService` internal menggantikan `Acc\Acc` (interface sama biar `TransactionService` minim ubah).
3. Auto-posting: gajian/kasbon/bayar-kasbon → jurnal internal (jaga idempotensi via `internal_reference`).
4. UI menu "Akuntansi": Daftar Akun, Jurnal/Transaksi (filter tanggal+akun), Buku Besar per akun, Laporan (neraca saldo sederhana).
5. Migrasi mapping ACC lama → chart of accounts internal.
6. (Opsional) Mode legacy Firefly tetap didukung via toggle M15.

## Definition of Done
- Gaji dibayar → muncul jurnal di buku besar internal, saldo akun berubah, tanpa API luar.
- Super admin bisa CRUD akun & lihat semua transaksi/laporan dari UI.
- Tidak ada panggilan ke Firefly untuk operasi inti.
