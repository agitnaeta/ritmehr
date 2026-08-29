# Review Version 5 — Backlog

Kumpulan temuan/keputusan yang muncul saat mengerjakan `review-version-4`,
tapi **cakupannya global** (bukan khusus modul User) sehingga ditunda ke sini
supaya `review-version-4` tetap fokus & selesai dulu.

---

## RV5-01 — Header list 2-baris berlaku GLOBAL (semua CRUD admin)

**Status:** [ ] TODO (belum direview menyeluruh)

**Konteks:**
Saat UM-03 (toolbar presisi modul User), solusi final = override
`resources/views/vendor/backpack/crud/list.blade.php` menjadi header 2-baris:

- Baris 1: breadcrumb (kiri) + tombol aksi stack `top` non-filter (kanan)
- Baris 2: heading + subjudul (kiri) + Pencarian/search & filter (kanan)

Karena `list.blade.php` adalah template **bersama semua CRUD**, perubahan ini
otomatis berlaku ke SELURUH halaman daftar admin (User, Leave, Gajian,
Rekrutmen, Kinerja, dll), bukan hanya `/admin/user`.

**Sudah diverifikasi saat UM-03:**
- `/admin/user` → rapi, sesuai design
- `/admin/leave-request` → rapi, konsisten (5 filter + tanpa tombol tambah, wrap oke)
- Regresi 106 PHPUnit PASS (User + Leave + Report + Ranking + Recruitment)

**Yang MASIH perlu direview di RV5 (belum dicek satu-satu):**
- [ ] Audit SEMUA halaman list admin lain (Gajian, Kasbon, Kinerja, Pelatihan,
      Dokumen, Organisasi, Pajak & BPJS, Pengaturan, Rekrutmen, Akuntansi,
      Audit Log, dll) — pastikan header 2-baris tidak pecah/aneh di layout
      dengan jumlah filter berbeda / tanpa filter / tanpa tombol.
- [ ] Halaman yang punya banyak tombol `top` (bukan cuma Tambah+dropdown) —
      cek apakah masih rapi di kanan baris 1.
- [ ] Halaman TANPA breadcrumb (kalau ada) — pastikan baris 1 tidak kosong aneh.
- [ ] Dark mode + mobile (<992px) di beberapa halaman berbeda.
- [ ] Pertimbangkan: apakah "Pencarian" label & flat-filter cocok untuk semua
      modul, atau ada modul yang lebih baik pakai layout filter card lama.

**File terdampak (sudah ada dari UM-03):**
- `resources/views/vendor/backpack/crud/list.blade.php` (override global)
- `resources/css/admin.css` (blok `.um-page-header`, `.um-header-*`, `.um-search`)

**Rencana test RV5:**
- Browser test lintas-modul: loop beberapa route list admin, assert struktur
  header (breadcrumb kiri, tools kanan, tidak overlap, tidak ada elemen ketimpa).

---

## Catatan
Item lain yang bersifat global & muncul selama RV4 ditambahkan di bawah ini
seiring berjalannya pekerjaan.
