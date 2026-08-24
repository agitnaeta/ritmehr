# Absensi

Sistem absensi berbasis pemindaian QR yang berkembang menjadi HRIS: penggajian,
cuti, kasbon, dokumen karyawan, pajak & BPJS, serta portal layanan mandiri
karyawan.

Dibangun dengan Laravel 10 dan Backpack CRUD 6 (edisi gratis) di atas MySQL 8.

**Dokumentasi:** [Panduan Pengguna](docs/PANDUAN-PENGGUNA.md) ·
[Panduan Developer](docs/PANDUAN-DEVELOPER.md)

---

## Daftar isi

- [Fitur](#fitur)
- [Prasyarat](#prasyarat)
- [Pemasangan](#pemasangan)
- [Cara pakai](#cara-pakai)
- [Pengembangan](#pengembangan)
- [Pengujian](#pengujian)
- [Dokumentasi](#dokumentasi)
- [Catatan penerapan](#catatan-penerapan)
- [Rencana pengembangan](#rencana-pengembangan)
- [Kontribusi](#kontribusi)
- [Lisensi](#lisensi)

---

## Fitur

| Modul | Cakupan |
|---|---|
| **Absensi** | Pemindaian QR di pintu masuk, geofence per cabang, jadwal kerja, hari libur nasional |
| **Penggajian** | Komponen gaji, lembur, denda keterlambatan, rekap bulanan, slip gaji PDF |
| **Kasbon** | Penerbitan kasbon, pencatatan cicilan, potongan otomatis dari gaji |
| **Cuti & Izin** | Pengajuan, kuota tahunan dengan carry-over, kalender, rekap |
| **Persetujuan** | Alur bertingkat per modul; approver berdasarkan peran, atasan, atau user tertentu |
| **Organisasi** | Cabang, departemen bersarang, jabatan, bagan struktur |
| **Dokumen** | Dokumen karyawan di penyimpanan privat, checklist kelengkapan, peringatan kedaluwarsa |
| **Pajak & BPJS** | PPh 21 progresif, PTKP, BPJS Kesehatan/JHT/JP/JKK/JKM, THR |
| **Portal Karyawan** | Riwayat kehadiran, slip gaji, cuti, kasbon, profil, notifikasi |
| **Dashboard & Laporan** | Ringkasan harian, tren 12 bulan, laporan kehadiran/gaji/kasbon/headcount |
| **Audit & Notifikasi** | Jejak audit seluruh perubahan; kanal database, email, dan WhatsApp |

Hak akses dibagi empat peran — `super_admin`, `hr_admin`, `manager`, `employee`
— dan ditegakkan di dua lapis: middleware pada route group, serta pembatasan
operasi di controller. Manager hanya melihat bawahan langsungnya.

---

## Prasyarat

| Kebutuhan | Versi |
|---|---|
| PHP | 8.1 atau lebih baru |
| Composer | 2.x |
| Docker | untuk MySQL 8 |
| Node.js | 18+ — hanya bila menjalankan pengujian browser |

Ekstensi PHP yang dibutuhkan mengikuti kebutuhan standar Laravel 10, ditambah
`gd` untuk pembuatan QR dan kartu karyawan.

---

## Pemasangan

```bash
git clone git@github.com:agitnaeta/absensi.git
cd absensi

composer install
cp .env.example .env
php artisan key:generate
```

Jalankan basis data, lalu sesuaikan `.env`:

```bash
docker compose up -d          # MySQL 8 pada port host 3307
```

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3307
DB_DATABASE=absensi
DB_USERNAME=root
DB_PASSWORD=secret
```

> `.env.example` mengirim `DB_PORT=3306`, sedangkan `docker-compose.yml`
> memetakan MySQL ke **3307** supaya tidak bentrok dengan MySQL lokal. Ubah
> manual setelah menyalin.

Siapkan skema dan data referensi:

```bash
php artisan migrate
php artisan db:seed --class=HrisSeeder      # peran, izin, alur persetujuan, tarif pajak
php artisan serve
```

`HrisSeeder` bersifat idempoten — aman dijalankan ulang setelah pembaruan.

Berikan peran tertinggi ke akun pertama:

```bash
php artisan tinker
>>> \App\Models\User::find(1)->assignRole('super_admin');
```

---

## Cara pakai

Aplikasi punya tiga pintu masuk:

| Alamat | Untuk siapa | Perlu login |
|---|---|---|
| `/scan` | Pemindai QR di pintu masuk kantor | tidak |
| `/admin/login` | HR, manager, super admin | ya |
| `/my` | Karyawan — portal layanan mandiri | ya |

Membuka `/` akan mengarahkan ke `/scan`.

Alur harian paling umum: karyawan menunjukkan QR di kartunya ke kamera pada
halaman `/scan`. Pemindaian pertama tercatat sebagai jam masuk, berikutnya
sebagai jam keluar. Keterlambatan, lembur, dan pemeriksaan lokasi dihitung
otomatis.

Untuk tugas selengkapnya — menambah karyawan, menjalankan penggajian,
menyetujui cuti, mengelola kuota — lihat
**[Panduan Pengguna](docs/PANDUAN-PENGGUNA.md)** yang disusun per peran.

### Data demo

```bash
php artisan db:seed --class=DemoDataSeeder   # menolak berjalan di production
```

Membuat perusahaan lima orang dengan satu bulan kehadiran penuh, cuti yang sudah
disetujui dan yang masih menunggu, satu kasbon, serta satu siklus penggajian.

| Email | Password | Peran |
|---|---|---|
| `siti@demo.test` | `password` | super_admin |
| `rina@demo.test` | `password` | hr_admin |
| `budi@demo.test` | `password` | manager |
| `ahmad@demo.test`, `dewi@demo.test` | `password` | employee |

Data sengaja ditempatkan di **bulan sebelumnya**: rekap gaji mengukur satu bulan
penuh, sehingga bulan berjalan yang baru separuh akan terbaca seperti absen
massal.

---

## Pengembangan

Struktur kode mengikuti satu aturan utama: **logika bisnis berada di Service,
bukan di controller.** Controller Backpack hanya mengatur field, kolom, dan hak
akses.

```
app/
├── Http/Controllers/Admin/     29 CRUD controller
├── Http/Controllers/Portal/    seluruh /my/*
├── Services/                   aturan bisnis sesungguhnya
├── Observers/                  perhitungan turunan presensi & gaji
└── Traits/                     Auditable, HasApproval, HasSimpleFilters
```

Sebelum menyentuh kode, baca bagian **empat jebakan** di
**[Panduan Developer](docs/PANDUAN-DEVELOPER.md)**. Ringkasnya:

1. Backpack memakai guard `backpack`, sedangkan peran Spatie tersimpan di guard
   `web` — sehingga `@can` dan `@role` bawaan **selalu false** untuk admin
2. Model CRUD wajib memakai `CrudTrait`, karena itu `Role` dan `Permission`
   punya pembungkus lokal
3. `addFilter()` berbayar di edisi gratis; penggantinya trait `HasSimpleFilters`
4. Baris tabel dimuat lewat AJAX, dan `recordsTotal` bukan angka yang
   ditampilkan — `recordsFiltered` yang benar

### Perintah artisan

| Perintah | Fungsi | Jadwal |
|---|---|---|
| `notify:attendance --type=checkin` | Belum absen masuk | hari kerja 08:15 |
| `notify:attendance --type=late` | Terlambat | hari kerja 09:30 |
| `notify:attendance --type=checkout` | Belum absen keluar | hari kerja 17:00 |
| `documents:notify-expiring --days=30` | Dokumen mendekati kedaluwarsa | Senin 07:30 |
| `notify:approval-digest` | Ringkasan persetujuan tertunda | Senin 08:00 |
| `backup:run --only-db` | Cadangan basis data | harian 23:00 |
| `db:seed RecalculatePresence` | Hitung ulang presensi | harian 23:30 |
| `audit:prune --days=90` | Pangkas jejak audit | bulanan tgl 1, 02:00 |
| `leave:generate-balances --carry-over --max-carry=6` | Saldo cuti tahunan | tahunan |
| `calculate:salary` | Hitung rekap gaji | manual |
| `salary:recalculate` | Hitung ulang gaji per user/bulan | manual |
| `import:presence-command` | Impor presensi | manual |

Penjadwalan berjalan lewat `php artisan schedule:work` atau cron. Log aplikasi
bisa dibaca di `/log-viewer`.

---

## Pengujian

Dua lapis, keduanya perlu dijalankan sebelum rilis.

```bash
./vendor/bin/phpunit
```

150 test pada skema `absensi_testing` yang terpisah, jadi tidak menyentuh data
pengembangan.

```bash
npm install && npx playwright install chromium
php artisan serve                       # aplikasi harus berjalan lebih dulu
node tests/browser/crud-suite.mjs       # 146 pemeriksaan CRUD & hak akses
node tests/browser/ui-test.mjs          # rendering dashboard, menu, portal
```

Pengujian browser menutup hal yang tidak terjangkau PHPUnit: tabel yang dimuat
lewat AJAX, rendering grafik, visibilitas menu per peran, dan route publik yang
diakses tanpa login.

> `crud-suite.mjs` **mengubah data**. Cadangkan lebih dulu:
> ```bash
> docker exec absensi-mysql mysqldump -uroot -psecret --single-transaction absensi \
>   > storage/app/backups/pre-crud-test.sql
> ```
> Pulihkan dengan `mysql` memakai berkas yang sama.

---

## Dokumentasi

Mulai dari salah satu ini, sesuai kebutuhanmu:

| Dokumen | Untuk siapa |
|---|---|
| **[Panduan Pengguna](docs/PANDUAN-PENGGUNA.md)** | HR, manager, karyawan. Berorientasi tugas, disusun per peran, dilengkapi tanya-jawab |
| **[Panduan Developer](docs/PANDUAN-DEVELOPER.md)** | Arsitektur, konvensi, jebakan yang perlu diketahui, cara menambah modul |

Rujukan lebih dalam:

| Dokumen | Isi |
|---|---|
| [HRIS_SETUP.md](docs/HRIS_SETUP.md) | Referensi modul M0–M8 dan keputusan arsitekturnya |
| [BUSINESS_FLOW.md](docs/BUSINESS_FLOW.md) | Alur bisnis |
| [test-cases/](docs/test-cases/README.md) | 725 test case CRUD operasional per modul |
| [bug-list/](docs/bug-list/README.md) | 12 bug beserta akar masalah dan perbaikannya |
| [UI_TEST_CASES.md](docs/UI_TEST_CASES.md) | Test case UI lintas modul dan matriks akses per peran |
| [MODULE_PLANS.md](docs/MODULE_PLANS.md) | Rencana modul |

---

## Catatan penerapan

**Tarif pajak wajib diverifikasi.** `TaxRateSeeder` mengacu PMK 101/2016 dan
UU HPP No. 7/2021 pada saat ditulis, dengan JKK kelas risiko terendah (0,24%).
PTKP, lapisan PPh 21, dan persentase BPJS disimpan **per tahun** supaya
perhitungan historis memakai tarif periodenya sendiri. Periksa nilainya terhadap
regulasi terbaru sebelum menjalankan penggajian sungguhan.

**Aplikasi ini single-tenant.** Satu instalasi untuk satu perusahaan. Tabel
`branches` dan `departments` adalah struktur di dalam satu perusahaan, bukan
pemisah antar pelanggan; tidak ada kolom tenant di tabel inti.

**Dokumen karyawan** disimpan di disk `local` yang privat, bukan `public`.
Unduhan mengalir lewat aplikasi setelah pemeriksaan hak akses.

**WhatsApp** memakai `LogWhatsAppGateway` yang hanya mencatat, sampai
`FONNTE_TOKEN` diisi — supaya tidak ada notifikasi yang berpura-pura terkirim.

**Integrasi akuntansi** nonaktif kecuali `ACC_ACTIVE=true` diset di `.env`.

Daftar periksa lengkap sebelum rilis ada di
[Panduan Developer](docs/PANDUAN-DEVELOPER.md#sebelum-rilis).

---

## Rencana pengembangan

Modul M0–M8 sudah dibangun. Tiga modul berikut direncanakan namun belum
dikerjakan, dan ditandai prioritas rendah di
[MODULE_PLANS.md](docs/MODULE_PLANS.md):

- Rekrutmen
- Manajemen kinerja
- Pelatihan

Satu celah fungsional yang diketahui: karyawan belum bisa mengunduh dokumennya
sendiri dari portal, karena route dokumen di `/my` belum ada.

---

## Kontribusi

1. Buat branch dari `master`
2. Kalau menambah modul, ikuti urutan di
   [Panduan Developer](docs/PANDUAN-DEVELOPER.md#menambah-modul-crud-baru) —
   termasuk menambahkan izin di `RolesAndPermissionsSeeder`, langkah yang paling
   sering terlewat
3. Jalankan kedua lapis pengujian sampai hijau
4. Tulis pesan commit yang menjelaskan **mengapa**, bukan hanya apa

Temuan bug sebaiknya dicatat di [docs/bug-list/](docs/bug-list/README.md) dengan
langkah reproduksi dan akar masalahnya, mengikuti format berkas yang sudah ada.

---

## Lisensi

`composer.json` menyatakan **MIT**, warisan dari kerangka Laravel. Namun
repositori ini **belum memiliki berkas `LICENSE`**.

TODO: tambahkan berkas `LICENSE` yang sesuai, atau perbarui `composer.json` bila
proyek ini bersifat tertutup.
