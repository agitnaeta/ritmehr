# UM-07 — Label Show/form masih campur Inggris

**Poin Capt #7** · Tipe: i18n / label · Urgensi: Sedang · Risiko: Rendah
**Status:** [ ] TODO

---

## Konteks
Halaman Show menampilkan label Inggris (`Name:`, `Locale:`, `Employee:`, `Join date:`,
`Employment status:` dengan nilai `active` mentah, `Phone:`, `Address:`, `Image:`,
`Created:`, `Updated:`). Campur bahasa, tidak konsisten dengan sisa admin (ID).

## Akar masalah (terverifikasi)
- `autoSetupShowOperation()` menghumanize nama kolom DB → label Inggris.
- Nilai `employment_status` ditampilkan mentah (`active`) tanpa `employmentStatusLabel()`.
- List sudah di-relabel manual (`UserCrudController:135-139`), Show belum.

## Rencana solusi
> Sebagian besar diselesaikan oleh **UM-06** (custom Show view berbahasa ID). Task ini
> memastikan cakupan label penuh + form Create/Update.

File yang disentuh:
1. (via UM-06) `resources/views/admin/user/show.blade.php` — semua label ID, status
   pakai `employmentStatusLabel()`, tanggal format ID, locale tampil nama bahasa.
2. `app/Http/Controllers/Admin/UserCrudController.php`
   - Cek field Create/Update: pastikan semua `->label()` berbahasa ID (mayoritas sudah
     di `orgFields()`), tambal yang tersisa (mis. `name`, `email`, `password`, `image`).
   - Samakan LEVEL i18n ke pola proyek (hardcode ID untuk label CRUD — konsisten,
     bukan full `__()` yang malah tak konsisten dengan modul lain).

## Rencana test UI
`tests/browser/um-07-labels-id.mjs`:
- TC1: Show → tak ada teks "Employment status", "Join date", "Employee", "Locale",
  "Created", "Updated" (regex leak Inggris = 0).
- TC2: status tampil "Aktif"/"Masa Percobaan" dsb, bukan "active".
- TC3: form Create/Update → label field berbahasa ID.

## Definition of Done
Nol label Inggris bocor di Show + form; status ter-lokalisasi; test PASS;
update Status + centang README. (Dikerjakan berbarengan / setelah UM-06.)
