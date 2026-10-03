# Review Version 5 — Backlog

Kumpulan temuan/keputusan yang muncul saat mengerjakan `review-version-4`,
tapi **cakupannya global** (bukan khusus modul User) sehingga ditunda ke sini
supaya `review-version-4` tetap fokus & selesai dulu.

---

## RV5-01 — Header list 2-baris berlaku GLOBAL (semua CRUD admin)

**Status:** [x] DONE (terverifikasi, lihat `tasks/RV5-01-header-audit.md`)

**Konteks:**
Saat UM-03 (toolbar presisi modul User), solusi final = override
`resources/views/vendor/backpack/crud/list.blade.php` menjadi header 2-baris:

- Baris 1: breadcrumb (kiri) + tombol aksi stack `top` non-filter (kanan)
- Baris 2: heading + subjudul (kiri) + Pencarian/search & filter (kanan)

Karena `list.blade.php` adalah template **bersama semua CRUD**, perubahan ini
otomatis berlaku ke SELURUH halaman daftar admin (User, Leave, Gajian,
Rekrutmen, Kinerja, dll), bukan hanya `/admin/user`.

**Hasil audit RV5 (terverifikasi terukur — `tests/browser/rv5-01-header-audit.mjs` +
`rv5-01-mobile-dark.mjs`, bukti di `screenshots/` & `rv5-01-report.json`):**
- [x] Audit SEMUA halaman list admin CRUD → **37/37 PASS** (desktop 1280px). Tak ada
      overflow horizontal, tak ada overlap search×tombol, `#datatable_info_stack`
      konsisten `inline-grid`.
- [x] Halaman banyak tombol `top` (Gajian/Salary) → rapi.
- [x] Halaman TANPA tombol create (Audit Log) → rapi.
- [x] Dark mode + mobile (<992px) → sampel 6 halaman (user/salary/audit-log/presence/
      leave-request/department) PASS, 0 issue.
- [x] Guard permanen: `rv5-01-header-audit.mjs` exit non-zero kalau ada CRUD pecah.

**3 halaman `/admin/*` yang BUKAN CRUD Backpack** (Controller biasa + Blade custom,
tak ikut override `list.blade.php`): `training`, `notification`, `employee-document`.
Diseragamkan ke pola 2-baris via komponen `<x-admin.page-header>` (RV5-01-C) —
lihat `tasks/RV5-01-header-audit.md` + mockup `mockups/rv5-01c-header-noncrud.html`.
Dijaga terpisah oleh `tests/browser/rv5-01c-noncrud-header.mjs`.

**File terdampak:**
- `resources/views/vendor/backpack/crud/list.blade.php` (override global — dari UM-03)
- `resources/views/components/admin/page-header.blade.php` (BARU — komponen non-CRUD)
- `resources/views/admin/{training,notification,document}/index.blade.php` (pakai komponen)
- `resources/css/admin.css` (blok `.um-*` — reused, tak berubah)

---

## RV5-02 — Deprecate varian ukuran `-sm` pada komponen UI (GLOBAL)

**Status:** [ ] TODO

**Keputusan (Capt):**
Sistem **tidak lagi menggunakan varian ukuran _small_ (`-sm`)** pada komponen
kontrol form & tombol. Semua kontrol memakai ukuran **default (medium)** agar
target sentuh (tap target), tinggi baris, dan konsistensi visual seragam di
seluruh aplikasi.

**Class Bootstrap 5 yang harus DIHAPUS / tidak dipakai lagi:**
- Tombol: `.btn-sm` (dan `.btn-group-sm`)
- Input teks: `.form-control-sm`
- Dropdown/select: `.form-select-sm`
- Input group: `.input-group-sm`
- Catatan istilah lama Bootstrap 3/4 kalau masih tersisa: `input-sm`,
  `select-sm` → migrasikan ke ukuran default (bukan diganti ke `-sm` v5).

**Definisi selesai (Definition of Done):**
- [ ] Audit seluruh Blade (`resources/views/**`, termasuk override
      `resources/views/vendor/backpack/**`) — cari & hapus semua kelas di atas.
- [ ] Audit komponen yang dirender via controller/PHP (mis. `->size('sm')`,
      atribut `class` pada field/column Backpack, `addButtonFromView` custom).
- [ ] Audit render dinamis via JS (mis. DataTables buttons, tombol yang
      di-inject skrip — bandingkan pola `crudTable_reset_button` di RV4 yang
      punya `ml-1 ms-1`).
- [ ] Audit `resources/css/admin.css` & CSS lain — pastikan tak ada rule yang
      mengandalkan selector `.btn-sm`/`.form-control-sm`/dst; kalau ada styling
      khusus ukuran kecil, konversikan ke ukuran default atau hapus.
- [ ] Verifikasi visual: form Create/Update, filter bar, toolbar, tabel aksi
      (Lihat/Ubah/Hapus/Print), modal — tinggi kontrol konsisten (default).
- [ ] Regresi: pastikan tak ada layout pecah akibat kenaikan ukuran (baris
      tabel, toolbar wrap, tombol aksi per-baris yang tadinya `btn-sm`).

**Cara audit cepat (referensi, jalankan saat eksekusi RV5):**
- Cari pemakaian: `search_files` pattern
  `btn-sm|btn-group-sm|form-control-sm|form-select-sm|input-group-sm|input-sm|select-sm`
  di `resources/`, dan `->size\(('|\")sm` / `'sm'` di `app/`.

**Risiko / catatan:**
- Menghapus `.btn-sm` pada **tombol aksi per-baris tabel** (Lihat/Ubah/Hapus/
  Print) akan menaikkan tinggi baris — cek agar tabel tidak jadi terlalu tinggi
  / toolbar tidak wrap tak diinginkan. Sesuaikan spacing bila perlu (bukan
  dengan mengembalikan `-sm`, tapi via padding/gap).
- Perubahan ini **global** (menyentuh template & CSS bersama), jadi butuh
  sweep menyeluruh + verifikasi lintas modul, bukan per-halaman.

**File berpotensi terdampak:**
- `resources/views/**` (semua Blade app + override vendor Backpack)
- `resources/css/admin.css` (dan sumber CSS lain)
- Controller yang menyusun field/column/button Backpack dengan opsi ukuran.

---

## Catatan
Item lain yang bersifat global & muncul selama RV4 ditambahkan di bawah ini
seiring berjalannya pekerjaan.
