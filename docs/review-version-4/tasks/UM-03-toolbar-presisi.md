# UM-03 — Tata letak toolbar tidak presisi

**Poin Capt #3** · Tipe: CSS / layout · Urgensi: Tinggi · Risiko: Rendah
**Status:** [ ] TODO

---

## Konteks
Toolbar terpecah 2 baris: grup filter di kiri, **Cari** melayang jauh ke kanan, lalu
baris kedua berisi Tambah user / User Export / Import Excel / Cetak Semua ID. Tidak
ada grouping jelas antara aksi utama, aksi data, dan pencarian → membingungkan.

## Akar masalah (terverifikasi)
- Tombol didaftarkan terpisah via `addButtonFromView('top', ...)`
  (`UserCrudController:143-145`, `:388-390`) tanpa wadah flex/grid konsisten.
- Search box bawaan DataTables default kanan-atas → terpisah dari grup lain.
- `admin.css:329+` sudah berniat menata toolbar tapi belum menyatukan pencarian +
  tombol aksi dalam satu baris rapi.

## Rencana solusi
File yang disentuh:
1. `resources/css/admin.css`
   - Bungkus area atas jadi satu bar flex: **kiri** = grup filter (kotak bergaris),
     **kanan** = search + tombol aksi. Kelompokkan tombol:
     - Primary menonjol: **Tambah user** (`btn-primary`).
     - Grup data (outline): Export / Import / Cetak — jadi satu cluster.
   - `flex-wrap` di mobile: turun bertumpuk rapi, search full-width.
2. (opsional) `resources/views/vendor/backpack/...` jika perlu wrapper markup —
   utamakan solusi CSS murni dulu agar minim risiko.

## Rencana test UI
`tests/browser/um-03-toolbar-layout.mjs`:
- TC1: desktop → search box & tombol aksi berada di baris yang sama /
  cluster kanan (bukan melayang sendiri). Cek posisi via `getBoundingClientRect`.
- TC2: mobile 390 → toolbar wrap tanpa overflow, tombol tetap terjangkau.
- TC3: screenshot → `vision_analyze` konfirmasi grouping rapi.
- TC4: semua tombol tetap berfungsi (link href tak berubah).

## Definition of Done
Toolbar tampil rapi & terkelompok di desktop + mobile; screenshot before/after;
test PASS; update Status + centang README.
