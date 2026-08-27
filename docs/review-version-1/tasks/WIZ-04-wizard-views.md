# WIZ-04 — View Setup Wizard (baru)

**Status:** [x] DONE — commit: `(uncommitted)` · 4 step render sesuai mockup (stepper ✓, prefilled), finish→dashboard
**Referensi desain:** [`../mockup/setup-wizard.html`](../mockup/setup-wizard.html) · bukti: `../mockup/preview-wizard-step1.png`, `../mockup/preview-wizard-step4.png`
**Bagian dari:** Setup Wizard (menutup RV1-001, Lensa 2)
**Depends:** WIZ-02 (controller)

## Desain yang disepakati (dari mockup)
Kartu tengah (max ~820px) dengan **stepper 4-dot** horizontal:
`1 Perusahaan · 2 Struktur · 3 Admin · 4 Import`. Dot aktif biru (`#206bc4`), dot selesai hijau (`#2fb344`), garis penghubung mengikuti progres. Footer: tombol **← Kembali** (kiri, sembunyi di step 1), **Lanjut →** (biru), pada step terakhir muncul **Lewati** (link) + **Selesai & Masuk Dashboard** (hijau).

## File yang dibuat (per file — masing-masing bisa di-flag terpisah di catatan bawah)
| File | Isi |
|---|---|
| `resources/views/admin/setup/_layout.blade.php` | extend `backpack_view('blank')` (theme Tabler); render stepper + slot konten + footer tombol. Terima `$step`, `$steps`, `$stepIndex`. |
| `resources/views/admin/setup/company.blade.php` | Form: Nama Perusahaan (required), Alamat, Telepon, Logo (file, opsional). POST → `setup.save/company`. |
| `resources/views/admin/setup/orgunit.blade.php` | Dua kolom: Departemen (multi-input + "＋ Baris") & Cabang (multi-input). Minimal 1 masing-masing. |
| `resources/views/admin/setup/admin.blade.php` | Nama, Email (prefilled admin login), Departemen (select), checkbox "Buat juga akun HR terpisah". |
| `resources/views/admin/setup/import.blade.php` | Kartu "Belum punya file? → Unduh Template Karyawan", input file `.xlsx/.xls/.csv`, catatan kolom. Tombol **Lewati** + **Selesai**. Reuse endpoint IMP (template & store) bila sudah ada. |

## Ketentuan
- Semua teks **Bahasa Indonesia**, ikuti label persis di mockup.
- Stepper = komponen di `_layout`, bukan diulang di tiap view.
- Tema Tabler sudah aktif (`config/backpack/ui.php` → `theme-tabler`), jadi cukup pakai kelas Bootstrap 5 / Tabler (`card`, `form-control`, `btn btn-primary`, `required`).
- Progres bar/step ditandai server-side dari `$stepIndex` (jangan andalkan JS untuk state utama; JS mockup hanya utk demo).

## Verifikasi
1. `/admin/setup` → step Perusahaan tampil identik arah mockup (stepper, tombol Lanjut).
2. Tombol Lanjut/Kembali pindah step; step 4 memunculkan Lewati + Selesai.
3. Tidak ada teks Inggris bocor; render tanpa error di `browser_console`.
4. Screenshot after disimpan ke `../screenshots/wiz-*.png`.
