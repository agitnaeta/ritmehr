# Analisis Teknis — Modul User (Admin)

Verifikasi tiap poin laporan Capt terhadap **kode aktual** dan **UI nyata**
(bukan asumsi). Semua rujukan `file:line` sudah dicek langsung.

Recon dilakukan pada:
- `app/Http/Controllers/Admin/UserCrudController.php`
- `app/Models/User.php`, `app/Models/TrainingEnrollment.php`
- `app/Imports/UserImport.php`, `app/Exports/UserExport.php`, `app/Exports/UserTemplateExport.php`
- `resources/css/admin.css`, `resources/css/tokens.css`
- `database/migrations/2026_08_24_150001_add_locale_to_users.php`
- UI: `http://localhost:8000/admin/user` + `/admin/user/{id}/show` (login `siti@demo.test`)

---

## Poin 1 — Tampilan mobile berantakan  → UM-01

**Bukti visual (desktop 1280px sekalipun):** DataTable Backpack meng-collapse
SEMUA kolom kecuali "Nama" ke balik ikon ⋮ (responsive child-row). Di mobile makin
parah. Kolom Email/Bahasa/Karyawan/Tgl/Departemen/Aksi tak terlihat langsung.

**Akar masalah:**
- `UserCrudController::setupListOperation()` (`:112`) memakai `CRUD::setFromDb()`
  (`:114`) + menambah banyak kolom (Jadwal, org columns, name/email/locale/employee/
  join_date, Departemen). Terlalu banyak kolom → DataTables responsive menyembunyikan
  hampir semua di layar kecil/menengah.
- Tidak ada `priority` yang ditetapkan pada kolom penting → default responsive
  menyembunyikan dari kanan; ironisnya di sini malah menyisakan hanya Nama.
- `resources/css/admin.css` mengatur toolbar tapi tidak menangani skala mobil untuk tabel.

**Arah solusi:** kurangi kolom default (pilih 4-5 kolom inti), beri `priority` rendah
ke Nama/Aksi, pastikan kolom sekunder yang boleh disembunyikan diberi `priority` tinggi,
dan tambah aturan CSS responsif untuk toolbar + tabel. Detail di UM-01.

---

## Poin 2 — `.form-control`/`.form-select` abu = menyatu background → UM-02

**Akar masalah (terverifikasi):**
- `resources/css/admin.css:287-292`:
  ```css
  .form-control, .form-select {
      background-color: var(--field-bg);
      border: 1px solid var(--field-border);
  }
  ```
- `resources/css/tokens.css:57,60`:
  ```css
  --field-bg:      var(--n-100);   /* #f1f5f9 abu muda */
  --field-border:  transparent;    /* tanpa garis */
  ```
- Filter toolbar (`simple_filters.blade.php`) memakai latar bernada (`--bg-sunken`
  = `--n-100` juga, `tokens.css:102`). Field `--field-bg` = `--n-100` DI ATAS bar
  `--bg-sunken` = `--n-100` + border transparan → **field nyaris tak terlihat**
  (abu di atas abu, tanpa batas). Ini pola bug yang sudah pernah kena di portal
  (lihat `portal.css:737-755` yang MEMPERBAIKI hal sama dengan border tegas + latar surface).

**Arah solusi:** untuk field di dalam toolbar/kartu bernada, beri latar `--bg-surface`
(putih) + border tegas (`--border-strong`), scoped agar tak mengganggu field lain
yang memang di kartu putih. Verifikasi kontras via `getComputedStyle`. Detail UM-02.

---

## Poin 3 — Lokasi tombol/filter/tambah/export/import/search tidak presisi → UM-03

**Bukti visual:** toolbar terpecah 2 baris — baris 1: grup filter (Departemen/Cabang/
Status + Filter + Reset) di kiri, kotak **Cari** melayang sendirian jauh ke kanan;
baris 2: Tambah user / User Export / Import Excel / Cetak Semua ID. Tidak ada
grouping yang jelas antara "aksi utama" vs "aksi data" vs "pencarian".

**Akar masalah:**
- Tombol didaftarkan terpisah-pisah via `addButtonFromView('top', ...)`
  (`UserCrudController:143-145`, `:388`) tanpa penataan grid/flex yang konsisten.
- Search box adalah bawaan DataTables yang default-nya kanan-atas → terpisah dari grup lain.
- `admin.css` sudah punya niatan menata toolbar (komentar `:329-335`) tapi belum
  menyatukan pencarian + tombol aksi dalam satu baris rapi.

**Arah solusi:** satu bar aksi rapi — kiri: grup filter; kanan: search + tombol aksi
dikelompokkan (primary "Tambah" menonjol, sekunder Export/Import/Cetak sebagai grup
outline). Responsif turun ke bawah dengan rapi di mobile. Detail UM-03.

---

## Poin 4 — Import tak ada kolom `employee_id` → UM-04

**Akar masalah (terverifikasi):**
- `app/Exports/UserTemplateExport.php:16` header:
  `['nama','email','tgl_bergabung','departemen','cabang','jabatan','password','status']`
  → **tidak ada kolom NIK/employee_id**.
- `app/Imports/UserImport.php:35-48` `model()` tidak membaca `employee_id` sama sekali
  → user hasil import selalu tanpa NIK, padahal seed punya `EMP-004`, `EMP-005` dst.

**Keputusan Capt:** kolom NIK boleh diisi atau dikosongkan (opsional).

**Arah solusi:** tambah kolom `nik` (opsional) ke template + baca `employee_id` di
`UserImport::model()`. Bila kosong → biarkan null (jangan gagal). Detail UM-04.

---

## Poin 5 — Bahasa (locale) karyawan default `id` → UM-05

**Akar masalah (terverifikasi):**
- `database/migrations/2026_08_24_150001_add_locale_to_users.php:16`:
  `$table->string('locale', 5)->nullable()` → **tanpa default**.
- Akibatnya kolom "Bahasa" di list & Show tampil `-` (terlihat di UI).
- Import (`UserImport`) juga tak set locale.

**Arah solusi:** migration set default `'id'` + backfill baris existing `NULL → 'id'`,
model `User` beri `'locale' => 'id'` di `$attributes` default, import set `'id'` bila
kolom kosong. Detail UM-05. (Fondasi untuk UM-08 dropdown.)

---

## Poin 6 — Show: tambah foto + riwayat pelatihan, 2 tab → UM-06

**Akar masalah (terverifikasi):**
- `UserCrudController::setupShowOperation()` (`:64`) pakai `autoSetupShowOperation()`
  → tabel key-value mentah. Foto memang di-inject (`:69-78`) tapi bareng dump kolom lain.
- **Relasi pelatihan tersedia tapi TIDAK ditampilkan:** `TrainingEnrollment`
  (`app/Models/TrainingEnrollment.php`) punya `user()` & `training()`, tapi User
  belum punya relasi `trainingEnrollments()` dan Show tak menampilkannya.

**Arah solusi:** override `show($id)` penuh → custom view bertab (Bootstrap nav-tabs):
- Tab **Profil**: data karyawan rapi (kartu, badge status, label ID).
- Tab **Foto & QR**: foto besar + QR code.
- Tab **Riwayat Pelatihan**: daftar enrollment (training, status Lulus/Tidak,
  skor, sertifikat) via relasi baru `User::trainingEnrollments()`.
- Struktural → **mockup HTML dulu** sebelum koding. Detail UM-06.

---

## Poin 7 — Label Show masih campur Inggris → UM-07

**Bukti visual (Show `/admin/user/4/show`):** label `Name:`, `QR Code:`, `Locale:`,
`Employee:`, `Join date:`, `Employment status:` (nilai `active` mentah), `Phone:`,
`Address:`, `Image:`, `Created:`, `Updated:` — semuanya Inggris + status tak diterjemah.

**Akar masalah:** `autoSetupShowOperation()` menghumanize nama kolom DB → label Inggris.
List sudah di-relabel manual (`:135-139`) tapi Show belum.

**Arah solusi:** ditangani sekalian oleh UM-06 (custom Show view berbahasa ID). Untuk
form Create/Update, cek sisa label Inggris & samakan ke ID (level konsisten proyek).
Detail UM-07.

---

## Poin 8 — `locale` harusnya dropdown bahasa → UM-08

**Akar masalah:** field `locale` tidak didefinisikan eksplisit di `orgFields()` /
`fieldModification()` → `setFromDb()` merендер sebagai text input biasa. Seharusnya
`select_from_array` dengan opsi bahasa yang didukung (id, en).

**Arah solusi:** tambah field `locale` `select_from_array` (Indonesia/English),
default `id`. Butuh UM-05 lebih dulu. Detail UM-08.

---

## Poin 9 — Import/Export 1000+ user harus background → UM-09 (import), UM-10 (export)

**Akar masalah (terverifikasi):**
- `UserCrudController::importStore()` (`:461`) memanggil `Excel::import()` **sinkron**
  dalam request → 1000+ baris = request lama / timeout.
- `UserCrudController::export()` (`:422`) `Excel::download(new UserExport)` sinkron.
  `UserExport` sudah `WithChunkReading` (bagus untuk memori) tapi tetap **sinkron**
  di request → dataset besar tetap memblokir.

**Arah solusi:**
- UM-09: import via `ShouldQueue` (Maatwebsite queued import) + tabel job status +
  polling progress di UI; butuh worker (`queue:work`). Struktural.
- UM-10: export via `queued export` → simpan ke storage → tautan unduh saat siap
  (notifikasi/polling). Struktural.
- Prasyarat infra: konfigurasi queue (`QUEUE_CONNECTION=database` + `queue:work`).
  **Keputusan Terbuka** — lihat file task.

---

## Poin 10 — "Cetak Semua ID" bisa hang untuk 10rb user → UM-11

**Akar masalah (terverifikasi):**
- `UserCrudController::printAll()` (`:397`) → `User::all()` (muat SEMUA user ke RAM)
  → `_print()` (`:401`) me-loop, membaca file gambar tiap user + generate QR base64,
  lalu render **satu PDF dompdf** berisi semua. 10rb user = CPU/RAM hang + timeout.

**Arah solusi:** jangan render sinkron semua sekaligus. Opsi:
1. Batasi cetak ke hasil filter / seleksi baris (checkbox) — cetak yang dipilih saja.
2. Background job untuk batch besar → PDF disimpan ke storage → tautan unduh.
3. Guard jumlah maksimum per cetak sinkron (mis. > 200 → paksa mode background).
- Struktural → **mockup alur/HTML dulu**. Detail UM-11.

---

## Catatan lintas-task (infra)

- **Queue** (UM-09, UM-10, UM-11 mode background) butuh keputusan: `database` queue +
  `php artisan queue:work` (atau Horizon/Redis). Diangkat sebagai Keputusan Terbuka
  di tiap task terkait — TANYA Capt sebelum eksekusi.
- **Test UI**: gunakan `tests/browser/lib.mjs` yang sudah ada (helper `session/login/
  rowCount`), pola `pass(id)/fail(id)`. Jangan bikin framework baru.
