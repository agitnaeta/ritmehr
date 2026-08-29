# UM-05 — Default locale karyawan `id`

**Poin Capt #5** · Tipe: Migration + model + import · Urgensi: Sedang · Risiko: Rendah
**Status:** [ ] TODO

---

## Konteks
Bahasa (locale) karyawan tampil `-` di list & Show karena kolom `locale` nullable
tanpa default. Bahasa default seharusnya **Indonesia (`id`)**. Fondasi untuk UM-08
(dropdown bahasa).

## Akar masalah (terverifikasi)
- `database/migrations/2026_08_24_150001_add_locale_to_users.php:16`:
  `$table->string('locale', 5)->nullable()` → tanpa default.
- `app/Models/User.php` — tak ada `$attributes` default untuk `locale`.
- `app/Imports/UserImport.php` — tak set `locale`.

## Rencana solusi
File yang disentuh:
1. Migration baru `database/migrations/xxxx_set_default_locale_id_on_users.php`
   - **Laravel 12 punya native schema change — TIDAK butuh `doctrine/dbal`**
     (repo memang tak punya dbal). `$table->string('locale',5)->default('id')->nullable(false)->change();`
     jalan langsung. Alternatif paling aman lintas-DB: raw
     `DB::statement("ALTER TABLE users ALTER COLUMN locale SET DEFAULT 'id'")` (MySQL).
   - Backfill lebih dulu: `DB::table('users')->whereNull('locale')->update(['locale' => 'id'])`.
   > JANGAN edit migration lama (sudah dijalankan) — buat migration baru.
   > Catatan: `config('app.locale')` SUDAH `'id'` (config/app.php) — ini menyelaraskan
   > default kolom user ke nilai app.
2. `app/Models/User.php`
   - `protected $attributes = ['locale' => 'id'];` agar record baru via kode default `id`.
3. `app/Imports/UserImport.php`
   - Set `'locale' => 'id'` (atau baca kolom bila kelak ditambah), fallback `id`.

## Rencana test UI
PHPUnit `tests/Feature/UserLocaleDefaultTest.php`:
- TC1: `User::create([...tanpa locale])` → `locale === 'id'`.
- TC2: setelah migrate, tak ada user dengan `locale` null.
- Browser: list `/admin/user` → kolom Bahasa menampilkan "Indonesia"/"ID" bukan "-".

## Definition of Done
Semua user existing & baru punya `locale='id'`; kolom Bahasa tak lagi "-";
test PASS; migration reversible; update Status + centang README.
