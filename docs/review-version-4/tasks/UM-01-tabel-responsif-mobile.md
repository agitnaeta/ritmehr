# UM-01 — Tabel responsif & layout mobile

**Poin Capt #1** · Tipe: CSS + konfigurasi kolom · Urgensi: Tinggi · Risiko: Rendah
**Status:** [x] DONE — kolom diringkas ke 5 inti + `responsiveTable(false)`.
Test `tests/browser/um-01-user-responsive.mjs` (4 PASS) + regresi 68 PHPUnit PASS.

---

## Konteks
Di `/admin/user`, DataTable meng-collapse hampir semua kolom kecuali "Nama" ke balik
ikon ⋮ — bahkan pada layar desktop 1280px. Di mobile makin berantakan. Penyebab:
terlalu banyak kolom tanpa `priority`, plus tidak ada aturan CSS responsif untuk toolbar.

## Akar masalah (terverifikasi)
- `app/Http/Controllers/Admin/UserCrudController.php:112-148` `setupListOperation()`:
  `CRUD::setFromDb()` + tambahan banyak kolom (Jadwal, org columns, name/email/locale/
  employee/join_date, Departemen) → DataTables responsive menyembunyikan hampir semua.
- Tidak ada `priority` pada kolom penting (Nama, Aksi) → responsive-hidden salah target.

## Rencana solusi
File yang disentuh:
1. `app/Http/Controllers/Admin/UserCrudController.php`
   - Kurangi kolom list ke inti: **Nama, Email, Karyawan(NIK), Departemen, Status**
     (buang/priority-turunkan Bahasa, Jadwal, Tgl Bergabung, Cabang dari tampilan default).
   - Beri `->makeFirstColumn()` alternatif via `priority`: Nama `['priority'=>0]`,
     Aksi otomatis. Kolom sekunder diberi `priority` tinggi (mudah disembunyikan).
     > Pitfall: `makeFirstColumn()` bisa 500 di sebagian versi Backpack — pakai
     > `priority(0)` + urutan kolom, JANGAN andalkan `makeFirstColumn`.
2. `resources/css/admin.css`
   - Tambah `@media (max-width: 768px)`: toolbar `flex-wrap`, tabel `font-size`
     tabel + padding lebih rapat, sidebar collapse tak menabrak konten.

## Rencana test UI
`tests/browser/um-01-user-responsive.mjs`:
- TC1: buka `/admin/user` viewport 1280 → header tabel menampilkan ≥4 kolom (Nama,
  Email, Karyawan, Departemen, Status) — bukan cuma Nama.
- TC2: viewport 390 (mobile) → tak ada horizontal-scroll body; toolbar wrap rapi.
- TC3: nol 5xx, nol `pageerror`.
- Verifikasi VISUAL: screenshot desktop + mobile → `vision_analyze`.

## Definition of Done
Header tabel default menampilkan kolom inti di desktop; mobile rapi tanpa overflow;
screenshot before/after; test PASS; update Status + centang README.
