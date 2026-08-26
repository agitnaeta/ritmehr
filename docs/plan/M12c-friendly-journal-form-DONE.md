# M12c — Form "Catat Transaksi" Ramah Non-Akuntan ✅ DONE

> **Status:** ✅ IMPLEMENTED & TESTED (2026-08-24)
> **Konteks:** Form jurnal manual double-entry menakutkan untuk non-akuntan.

## Hasil Implementasi
- **Menu "Buat Jurnal Manual" → "Catat Transaksi"** dengan halaman pemilih 4 kartu jenis: Pengeluaran / Pemasukan / Transfer / Lanjutan.
- **Form ramah** (`transaction_form`): user cuma isi **1 nominal** + pilih akun by tujuan (Bayar dari / Untuk kategori). **Tanpa istilah debit/kredit.** Double-entry dibangun otomatis di `JournalService::createSimple/updateSimple` → mustahil pincang.
  - Pengeluaran: beban (debit) ← kas (kredit)
  - Pemasukan: kas (debit) ← pendapatan (kredit)
  - Transfer: tujuan (debit) ← asal (kredit)
- **Lampiran bukti** (nota/kwitansi, jpg/png/pdf ≤10MB) di disk privat + download ter-guard `accounting.view`. Ikon 📎 di daftar jurnal.
- **Mode Lanjutan** (form debit/kredit multi-baris) tetap ada untuk akuntan.
- **Edit** otomatis membuka mode yang sesuai (`journal_entries.kind`), prefill nominal & akun. Jurnal auto (gaji/kasbon) tetap terkunci → koreksi via pembalik.
- **Klasifikasi akun**: kolom `accounts.is_cash` (checkbox "Akun Kas/Bank?" di Daftar Akun) menyaring dropdown. Seed kategori umum: Kas, Bank, Beban Listrik/Air/Sewa/ATK/Lain-lain, Pendapatan Jasa/Lain-lain.
- Badge daftar Jurnal ramah: Pengeluaran/Pemasukan/Transfer/Manual/Pembalik/Auto.

## Automation Test
**PHPUnit** `tests/Feature/SimpleTransactionTest.php` — 7/7 PASS (mapping tiap mode benar & balanced, transfer akun-sama ditolak, nominal nol ditolak, lampiran tersimpan+hapus, edit update baris).
**Playwright** `tests/browser/m12c-catat-transaksi.mjs` — 5/5 PASS (4 kartu, form tanpa jargon debit/kredit, catat pengeluaran 1-nominal → muncul di Jurnal sbg "Pengeluaran", entri seimbang, Laba Rugi ikut).
**Regression:** `php artisan test` → **173 passed (368 assertions)**, nol regresi.

## Definition of Done — semua tercapai ✅
- Non-akuntan bisa catat pengeluaran/pemasukan/transfer tanpa tahu debit/kredit.
- Tiap catatan → jurnal seimbang benar, tampil di semua laporan.
- Bisa lampirkan & unduh bukti.
- Edit buka mode yang sama; jurnal otomatis tetap terkunci.
- Mode Lanjutan tetap tersedia.

---

## Rencana Awal (arsip)


---

## Masalah
Form jurnal manual saat ini menuntut user paham:
- konsep **debit vs kredit**,
- akun mana yang naik/turun,
- aturan "harus seimbang".

Untuk orang non-akuntansi yang cuma mau nyatat *"bayar listrik 500rb pakai kas"*,
ini ribet dan rawan salah.

## Prinsip Desain
1. **Bahasa manusia, bukan istilah akuntansi.** User memilih *apa yang terjadi*, bukan debit/kredit.
2. **Satu nominal, dua sisi otomatis.** User isi 1 angka; sistem yang bikin 2 baris jurnal seimbang.
3. **Selalu balance by construction** — mustahil bikin jurnal pincang di mode sederhana.
4. **Lampiran bukti** (nota/kwitansi/bukti transfer) — kebutuhan riil pencatatan harian.
5. **Mode Lanjutan tetap ada** untuk akuntan (form debit/kredit multi-baris yang sekarang).

---

## UX: 3 Mode Sederhana + 1 Lanjutan

Saat buka **"Catat Transaksi"**, user pertama pilih jenis (kartu besar, ikon jelas):

| Jenis | Pertanyaan ke user | Contoh |
|-------|--------------------|--------|
| 💸 **Pengeluaran** (Uang Keluar) | Bayar untuk apa, dari kas/bank mana | Bayar listrik, beli ATK, bayar sewa |
| 💰 **Pemasukan** (Uang Masuk) | Terima uang untuk apa, masuk ke kas/bank mana | Pendapatan jasa, bunga bank |
| 🔄 **Transfer** (Pindah Dana) | Pindah dari mana ke mana | Tarik tunai kas → bank |
| ⚙️ **Lanjutan** (Jurnal Umum) | Debit/kredit multi-baris (akuntan) | Penyesuaian, saldo awal kompleks |

### Field per mode

**💸 Pengeluaran**
```
Tanggal            [24/08/2026]          (default hari ini)
Jumlah             [Rp 500.000]          (input angka besar, ada pemisah ribuan)
Bayar dari         [▼ Kas / Bank]        (hanya akun Kas/Bank)
Untuk (kategori)   [▼ Beban Listrik]     (hanya akun kategori Beban)
Catatan            [Listrik kantor Agustus]
Lampiran           [ Pilih file… ]       (foto nota/kwitansi, jpg/png/pdf)
```
→ di belakang layar: **Debit** Beban Listrik, **Kredit** Kas/Bank.

**💰 Pemasukan**
```
Tanggal · Jumlah
Masuk ke           [▼ Kas / Bank]
Sumber (kategori)  [▼ Pendapatan Jasa]   (hanya akun kategori Pendapatan)
Catatan · Lampiran
```
→ **Debit** Kas/Bank, **Kredit** Pendapatan.

**🔄 Transfer**
```
Tanggal · Jumlah
Dari               [▼ Kas]
Ke                 [▼ Bank]              (wajib beda dari "Dari")
Catatan · Lampiran
```
→ **Debit** akun tujuan, **Kredit** akun asal.

**⚙️ Lanjutan** = form multi-baris debit/kredit yang sekarang (tak berubah).

---

## Pemetaan ke Double-Entry (di belakang layar)

| Mode | Debit | Kredit | Selalu balance? |
|------|-------|--------|-----------------|
| Pengeluaran | Kategori Beban | Kas/Bank | ✅ (1 nominal) |
| Pemasukan | Kas/Bank | Kategori Pendapatan | ✅ |
| Transfer | Kas/Bank tujuan | Kas/Bank asal | ✅ |

Karena cuma ada **satu nominal** yang dipasang ke dua sisi, hasilnya **mustahil tidak seimbang**.
Hasil akhirnya tetap `JournalEntry` + 2 `JournalLine` yang sama seperti sekarang — jadi
Buku Besar, Neraca Saldo, Laba Rugi, Neraca semuanya langsung konsisten tanpa perubahan.

---

## Klasifikasi Akun (biar dropdown pintar)

Dropdown harus nyaring akun yang relevan saja:
- **Kas/Bank** → butuh penanda akun mana yang "uang riil". Tidak semua aset itu kas (mis. Piutang Kasbon).
  → **Tambah kolom `is_cash` (boolean)** di `accounts`. Checkbox "Akun Kas/Bank?" di form Daftar Akun.
- **Kategori Beban** → `type = expense`.
- **Kategori Pendapatan** → `type = income`.

### Seed kategori umum (biar langsung kepakai)
Supaya user non-akuntan nggak perlu bikin akun dulu, seed contoh:
- Beban: `5100 Beban Listrik`, `5200 Beban Air`, `5300 Beban Sewa`, `5400 Beban ATK`, `5900 Beban Lain-lain`
- Pendapatan: `4900 Pendapatan Lain-lain`
- Kas: `1000 Kas`, `1100 Bank` (is_cash = true)

---

## Lampiran File (bukti transaksi)
- Kolom baru di `journal_entries`: `attachment_path`, `attachment_name`.
- Simpan di disk privat (pola sama seperti M6 Dokumen) — **bukan** public.
- Download di-stream lewat controller + cek permission `accounting.view`.
- Batas: jpg/png/pdf, maks ~10 MB.
- Di daftar Jurnal & detail: ikon 📎 kalau ada lampiran (klik → download).

---

## Edit
- Reuse form yang sama, **mode dikenali otomatis**.
  → **Tambah kolom `kind`** di `journal_entries`: `expense` | `income` | `transfer` | `general`.
  - Entri dibuat via mode sederhana → simpan `kind` sesuai; edit membuka form sederhana yang sama, ter-prefill.
  - Entri `general` (lanjutan / auto gaji-kasbon) → buka mode Lanjutan.
- Aturan kunci tetap: hanya jurnal **manual** yang bisa diedit/hapus; jurnal **otomatis** (gaji/kasbon) tetap terkunci → koreksi via pembalik. (tak berubah)
- Ganti lampiran: upload baru mengganti lama; opsi hapus lampiran.

---

## Penamaan (kurangi kesan teknis)
- Menu **"Buat Jurnal Manual"** → **"Catat Transaksi"**.
- Halaman daftar tetap "Jurnal" (untuk yg butuh lihat debit/kredit), tapi kolom
  "Ref/Jenis" menampilkan label ramah: *Pengeluaran / Pemasukan / Transfer / Manual / Auto*.

---

## Perubahan Teknis yang Dibutuhkan
1. Migrasi: `accounts.is_cash` (bool); `journal_entries.kind`, `attachment_path`, `attachment_name`.
2. `JournalService`: method baru `createSimple($kind, $data, $file)` + `updateSimple(...)` yang menerjemahkan mode → 2 baris. `createManual/updateManual` (advanced) tetap ada.
3. `JournalController`: langkah pilih jenis → form per jenis; handle upload file (validasi + simpan privat) + download route.
4. View: halaman pemilih jenis (kartu) + 3 form sederhana + form lanjutan (existing). JS ringan untuk format ribuan & validasi nominal>0.
5. Seeder: `is_cash` untuk Kas/Bank + kategori umum beban/pendapatan.
6. Account CRUD: checkbox "Akun Kas/Bank?".

## Definition of Done
- User non-akuntan bisa mencatat pengeluaran/pemasukan/transfer **tanpa** tahu debit/kredit.
- Setiap catatan menghasilkan jurnal seimbang yang benar & tampil di semua laporan.
- Bisa lampirkan & unduh bukti (nota/kwitansi).
- Edit membuka mode yang sama; jurnal otomatis tetap terkunci.
- Mode Lanjutan tetap tersedia untuk akuntan.
- Test PHPUnit (mapping tiap mode balanced, lampiran tersimpan) + Playwright (catat pengeluaran via UI, muncul di jurnal + laporan, lampiran ke-download).
