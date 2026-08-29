# UM-08 — `locale` jadi dropdown pilihan bahasa

**Poin Capt #8** · Tipe: Field CRUD · Urgensi: Sedang · Risiko: Rendah
**Status:** [ ] TODO · Prasyarat: **UM-05**

---

## Konteks
Field `locale` di form Create/Update dirender sebagai text input biasa (dari
`setFromDb()`). Seharusnya dropdown pilihan bahasa yang didukung sistem.

## Akar masalah (terverifikasi)
- `locale` tidak didefinisikan eksplisit di `orgFields()`/`fieldModification()`
  (`UserCrudController:183-349`) → `setFromDb()` merender text input.

## Rencana solusi
File yang disentuh:
1. `app/Http/Controllers/Admin/UserCrudController.php` — di `fieldModification()`:
   ```php
   CRUD::field([
       'name'    => 'locale',
       'label'   => 'Bahasa',
       'type'    => 'select_from_array',
       'options' => ['id' => 'Indonesia', 'en' => 'English'],
       'allows_null' => false,
       'default' => 'id',
   ]);
   ```
   - Sumber opsi: sebaiknya konsisten dengan bahasa yang benar-benar didukung i18n
     proyek (`lang/id`, `lang/en`). Jika ada helper daftar locale, pakai itu.
2. Pastikan kolom list "Bahasa" menampilkan nama bahasa (map id→Indonesia, en→English),
   bukan kode mentah — relabel di `setupListOperation()` bila perlu.

## Rencana test UI
`tests/browser/um-08-locale-dropdown.mjs`:
- TC1: buka Create → field Bahasa adalah `<select>` dengan opsi Indonesia & English.
- TC2: pilih English → simpan → user `locale='en'`, list menampilkan "English".
- TC3: default Create = Indonesia (`id`).

## Definition of Done
Field locale = dropdown bahasa dengan default Indonesia; list tampil nama bahasa;
test PASS; update Status + centang README.
