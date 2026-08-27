# Masukan Teknis — RitmeHR Versi 1

Metode bukti: browser-first (Chromium native handlers) + PHPUnit + inspeksi kode.
Semua 6 temuan di bawah **lolos test hijau** (390 PHPUnit + 146 browser) → artinya **tidak tertutup test**: test menutupi logika/regresi, bukan pengalaman & kelengkapan onboarding. Setiap temuan menandai gap yang perlu test/behavior baru.

| ID | Judul | Lensa/Modul | Severity | Bukti | Lokasi kode |
|---|---|---|---|---|---|
| RV1-001 | Tidak ada Setup Wizard / onboarding first-run | L2 Identitas / global | 🟠 Tinggi | tak ada route `wizard/onboard/welcome` di `routes/*.php` | `routes/web.php`, `routes/backpack/custom.php` |
| RV1-002 | Tidak ada import UI Karyawan & Gaji (Excel/API) | L4 Migrasi / Users+Gajian | 🟠 Tinggi | hanya `import:presence-command` (CLI, presensi saja) | `app/Console/Commands/ImportPresenceCommand.php`, `app/Imports/PresenceImport.php` |
| RV1-003 | Tabel Gaji sembunyikan seluruh kolom nilai di viewport sedang | L6 UI / Gajian | 🟡 Sedang | `screenshots/03-salary-list.png` — hanya "Nama Karyawan" tampil | `app/Http/Controllers/Admin/SalaryCrudController.php:185-202` (priority/responsive) |
| RV1-004 | Currency tanpa pemisah ribuan ("Rp12031000") | L6 UI / Gajian | 🟡 Sedang | `screenshots/03-salary-list.png` | `app/Http/Controllers/Admin/SalaryCrudController.php:197-202` (`->prefix($cur)` tanpa `number_format`) |
| RV1-005 | Header kolom campur Inggris/Indonesia | L6 UI / Users | 🟡 Sedang | `screenshots/04-users-list.png` — "Name/Email/Locale/Employee/Join date" vs "Departemen" | `app/Http/Controllers/Admin/UserCrudController.php` (label kolom) |
| RV1-006 | Typo "Pofile Perusahaan" (heading, title, breadcrumb) | L6 UI / Company Profile | 🟢 Rendah | `screenshots/05-company-profile-typo.png` | `app/Http/Controllers/Admin/CompanyProfileCrudController.php:34` |

---

## Detail temuan

### RV1-001 — Tidak ada Setup Wizard / onboarding first-run 🟠
- **Gejala:** admin baru login langsung ke `/admin/dashboard` lalu ke panel 17 modul tanpa alur terpandu untuk mengatur perusahaan → cabang → departemen → user.
- **Reproduksi (UI):** login `siti@demo.test` → tidak ada CTA/checklist "mulai di sini"; identitas perusahaan tersembunyi di menu "Profile Perusahaan".
- **Dampak bisnis:** *time-to-value* pelanggan baru bergantung pendampingan manual; menghambat self-serve adoption.
- **Test coverage:** tidak ada — ini gap fitur, bukan regresi. Perlu test alur onboarding baru.

### RV1-002 — Tidak ada import UI Karyawan & Gaji 🟠
- **Gejala:** satu-satunya jalur import adalah presensi via artisan command; tidak ada import Excel/API berbasis UI untuk data master karyawan atau struktur gaji.
- **Reproduksi (UI):** buka `/admin/user` → hanya ada "Tambah user" (manual) & "User Export"; tidak ada tombol Import. Grep route: tidak ada `Import` di `routes/`.
- **Dampak bisnis:** pelanggan yang migrasi dari Excel/HRIS lain harus re-entry manual → penghalang go-live terbesar; export tak simetris dengan import.
- **Test coverage:** tidak ada import UI untuk ditest. Rekomendasi: bangun fitur + test import (template → preview → validasi → merge langsung ke DB, tanpa tabel perantara).

### RV1-003 — Tabel Gaji menyembunyikan kolom nilai 🟡
- **Gejala:** pada viewport ~1280–1440px, tabel Gaji hanya menampilkan kolom "Nama Karyawan"; kolom Gaji/Lembur/Denda ter-collapse di balik toggle responsif tanpa nilai terlihat.
- **Reproduksi (UI):** login admin → `/admin/salary` → lihat `screenshots/03-salary-list.png`.
- **Dampak bisnis:** angka gaji (data paling dicari) tidak langsung terbaca; user harus expand baris satu per satu.
- **Catatan kode:** komentar di `SalaryCrudController.php:181-183` menandakan tim sadar isu "kolom Nama ketendang responsive collapse" dan membuang `basic_salary`; namun total "Gaji" pun ikut tersembunyi. Setel `->priority()` agar kolom Gaji tak pernah di-collapse.

### RV1-004 — Currency tanpa pemisah ribuan 🟡
- **Gejala:** nilai gaji tampil "Rp12031000", "Rp7500000" — sulit dibaca; tidak konsisten dengan dashboard/portal yang memakai "Rp 2.000.000".
- **Reproduksi (UI):** `/admin/salary` → kolom Gaji.
- **Lokasi:** `SalaryCrudController.php:197-202` — kolom hanya diberi `->prefix($cur)` tanpa `number_format`/tipe `number` dengan `decimals`/`thousands_separator`.
- **Dampak bisnis:** kesan tidak rapi pada modul paling sensitif; menurunkan kepercayaan.
- **Fix:** gunakan `->type('number')->decimals(0)->thousands_sep('.')` atau closure `number_format($v,0,',','.')`.

### RV1-005 — Header kolom campur bahasa 🟡
- **Gejala:** di `/admin/user` header "Name, Email, Locale, Employee, Join date" (Inggris) berdampingan dengan "Departemen" & "Aksi" (Indonesia). App default locale ID.
- **Reproduksi (UI):** `/admin/user` → lihat `screenshots/04-users-list.png`.
- **Dampak bisnis:** inkonsistensi bahasa menurunkan kesan profesional untuk pasar lokal.
- **Fix:** set `->label()` Indonesia untuk semua kolom Users (Nama, Email, Bahasa, Karyawan, Tgl Bergabung).

### RV1-006 — Typo "Pofile Perusahaan" 🟢
- **Gejala:** heading H1, `<title>`, dan breadcrumb menampilkan "Pofile Perusahaan" (kurang huruf "r").
- **Reproduksi (UI):** `/admin/company-profile` → lihat `screenshots/05-company-profile-typo.png`.
- **Lokasi:** `CompanyProfileCrudController.php:34` — `CRUD::setEntityNameStrings('Profile Perusahaan', 'Pofile Perusahaan');` (argumen plural typo). Idealnya "Profil Perusahaan" (PUEBI: "profil", bukan "profile").
- **Fix 1 baris:** `setEntityNameStrings('Profil Perusahaan', 'Profil Perusahaan')`.

---

## Hal yang sudah KUAT (bukti hijau, tak perlu diubah)

- **Keamanan peran & scoping:** manager ditolak create pada 20+ entity (403), employee dialihkan ke `/my`, manager hanya melihat data ter-scope (3 user, 64 presensi, 1 dari 2 approval). — `crud-suite.mjs` 146/146.
- **Rantai payroll & pajak:** slip gaji PDF, export rekap XLSX, PPh21 TER, kasbon PDF/XLSX — semua 200 dengan content-type benar.
- **Portal publik absensi:** `/scan` & `/` dapat diakses tanpa login, elemen scanner lengkap (kamera, GPS, jam real-time).
- **Portal karyawan `/my`:** UI bersih & jelas (stat, saldo cuti berprogres, gaji terakhir + status).
- **Logika inti:** 390 PHPUnit / 999 assertions lulus 100%.
