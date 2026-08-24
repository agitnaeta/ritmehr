# Absensi — Aplikasi Absensi & HRIS

Sistem absensi berbasis pemindaian QR yang berkembang menjadi HRIS: penggajian,
cuti, kasbon, dokumen karyawan, pajak & BPJS, serta portal layanan mandiri.

Dibangun dengan Laravel 10 dan Backpack CRUD 6 (edisi gratis).

---

## Fitur

| Modul | Cakupan |
|---|---|
| **Absensi** | Pemindaian QR di pintu masuk, geofence per cabang, jadwal kerja, hari libur nasional |
| **Penggajian** | Komponen gaji, lembur, denda keterlambatan, rekap bulanan, slip gaji PDF |
| **Kasbon** | Penerbitan kasbon, pencatatan cicilan, potongan otomatis dari gaji |
| **Cuti & Izin** | Pengajuan, kuota tahunan dengan carry-over, kalender, rekap |
| **Persetujuan** | Alur bertingkat per modul, approver berdasarkan role / atasan / user tertentu |
| **Organisasi** | Cabang, departemen bersarang, jabatan, struktur organisasi |
| **Dokumen** | Dokumen karyawan di penyimpanan privat, checklist kelengkapan, peringatan kedaluwarsa |
| **Pajak & BPJS** | PPh 21 progresif, PTKP, BPJS Kesehatan/JHT/JP/JKK/JKM, THR |
| **Portal Karyawan** | `/my` — riwayat kehadiran, slip gaji, cuti, kasbon, profil, notifikasi |
| **Dashboard & Laporan** | Ringkasan harian, tren 12 bulan, laporan kehadiran/gaji/kasbon/headcount |
| **Audit & Notifikasi** | Jejak audit seluruh perubahan; notifikasi database, email, dan WhatsApp |

---

## Menjalankan secara lokal

Prasyarat: PHP 8.1+, Composer, Docker, Node 18+ (hanya untuk pengujian browser).

```bash
git clone git@github.com:agitnaeta/absensi.git
cd absensi

composer install
cp .env.example .env
php artisan key:generate

docker compose up -d          # MySQL 8 di port host 3307
php artisan migrate
php artisan db:seed --class=HrisSeeder      # data referensi, idempoten
php artisan serve
```

Sesuaikan `.env` agar menunjuk ke container:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3307
DB_DATABASE=absensi
DB_USERNAME=root
DB_PASSWORD=secret
```

Buka http://127.0.0.1:8000 — halaman muka adalah pemindai QR (`/scan`),
panel admin di `/admin/login`.

Berikan role tertinggi ke akun pertama:

```bash
php artisan tinker
>>> \App\Models\User::find(1)->assignRole('super_admin');
```

### Data demo

```bash
php artisan db:seed --class=DemoDataSeeder   # menolak berjalan di production
```

Membuat perusahaan lima orang dengan satu bulan kehadiran penuh, cuti yang
disetujui dan yang pending, satu kasbon, serta satu siklus penggajian.

| Email | Password | Role |
|---|---|---|
| `siti@demo.test` | `password` | super_admin |
| `rina@demo.test` | `password` | hr_admin |
| `budi@demo.test` | `password` | manager |
| `ahmad@demo.test` · `dewi@demo.test` | `password` | employee |

Data sengaja ditempatkan di **bulan sebelumnya**: rekap gaji mengukur satu bulan
penuh, sehingga bulan berjalan yang baru separuh akan terbaca seperti absen
massal.

---

## Role

| Role | Cakupan |
|---|---|
| `super_admin` | Seluruh akses, termasuk role, permission, dan alur persetujuan |
| `hr_admin` | Seluruh operasi HR. Tidak boleh mengubah role, permission, atau alur persetujuan |
| `manager` | Melihat timnya (bawahan langsung) dan bertindak atas persetujuan. Hanya izin baca |
| `employee` | Portal layanan mandiri saja |

Hak akses ditegakkan di dua lapis: middleware `permission:` pada route group, dan
`denyAccess` di controller untuk modul yang boleh dibaca tetapi tidak ditulis.
Daftar karyawan dan presensi disempitkan lewat `User::scopeVisibleTo()`.

> **Dua guard.** Backpack mengautentikasi admin pada guard `backpack`, sedangkan
> role Spatie tersimpan pada guard `web`. Akibatnya `@can` dan `@role` bawaan
> **selalu false** untuk admin yang login. Di view admin gunakan
> `backpack_user()->can(...)`.

---

## Perintah terjadwal

| Jadwal | Perintah |
|---|---|
| Hari kerja 08:15 | `notify:attendance --type=checkin` |
| Hari kerja 09:30 | `notify:attendance --type=late` |
| Hari kerja 17:00 | `notify:attendance --type=checkout` |
| Senin 07:30 | `documents:notify-expiring --days=30` |
| Senin 08:00 | `notify:approval-digest` |
| Harian 23:00 | `backup:run --only-db` |
| Harian 23:30 | `db:seed RecalculatePresence` |
| Bulanan tgl 1, 02:00 | `audit:prune --days=90` |
| Tahunan | `leave:generate-balances --carry-over --max-carry=6` |

Perintah manual: `calculate:salary`, `salary:recalculate`, `import:presence-command`.

---

## Pengujian

```bash
./vendor/bin/phpunit                    # 150 test, skema absensi_testing terpisah
```

Pengujian berbasis browser (Chromium sungguhan) untuk hal yang tidak terjangkau
PHPUnit — tabel Backpack dimuat lewat AJAX, sehingga mengambil URL daftar saja
hanya menghasilkan kerangka tabel kosong:

```bash
npm install && npx playwright install chromium
php artisan serve                       # aplikasi harus berjalan
node tests/browser/crud-suite.mjs       # 146 pemeriksaan siklus CRUD & hak akses
node tests/browser/ui-test.mjs          # rendering dashboard, menu, portal
```

> `crud-suite.mjs` **mengubah data**. Cadangkan lebih dulu:
> ```bash
> docker exec absensi-mysql mysqldump -uroot -psecret --single-transaction absensi \
>   > storage/app/backups/pre-crud-test.sql
> ```
> Pulihkan dengan `mysql` dan berkas yang sama.

---

## Dokumentasi

**Mulai dari salah satu ini:**

| Berkas | Untuk siapa |
|---|---|
| [docs/PANDUAN-PENGGUNA.md](docs/PANDUAN-PENGGUNA.md) | **Pengguna** — HR, manager, karyawan. Berorientasi tugas, per peran |
| [docs/PANDUAN-DEVELOPER.md](docs/PANDUAN-DEVELOPER.md) | **Developer** — arsitektur, konvensi, jebakan, cara menambah modul |

Rujukan lebih dalam:

| Berkas | Isi |
|---|---|
| [docs/HRIS_SETUP.md](docs/HRIS_SETUP.md) | Referensi modul M0–M8, keputusan arsitektur, batasan Backpack edisi gratis |
| [docs/BUSINESS_FLOW.md](docs/BUSINESS_FLOW.md) | Alur bisnis |
| [docs/test-cases/](docs/test-cases/README.md) | 725 test case CRUD operasional per modul |
| [docs/bug-list/](docs/bug-list/README.md) | 12 bug beserta akar masalah dan perbaikannya |
| [docs/UI_TEST_CASES.md](docs/UI_TEST_CASES.md) | Test case UI lintas modul + matriks akses per role |
| [docs/MODULE_PLANS.md](docs/MODULE_PLANS.md) | Rencana modul |

---

## Catatan penerapan

**Tarif pajak wajib diverifikasi.** `TaxRateSeeder` mengacu PMK 101/2016 dan
UU HPP No. 7/2021 saat ditulis, dan JKK diisi kelas risiko terendah (0,24%).
PTKP, lapisan PPh 21, dan persentase BPJS disimpan **per tahun** agar
perhitungan historis memakai tarif periodenya sendiri — periksa nilainya
terhadap regulasi terbaru sebelum menjalankan payroll sungguhan.

**Single-tenant.** Satu instalasi untuk satu perusahaan. Tabel `branches` dan
`departments` adalah struktur di dalam satu perusahaan, bukan pemisah antar
pelanggan; tidak ada kolom tenant di tabel inti.

**Dokumen karyawan** disimpan di disk `local` (privat), bukan `public`. Unduhan
mengalir lewat aplikasi setelah pemeriksaan hak akses.

**WhatsApp** memakai `LogWhatsAppGateway` (hanya mencatat) sampai `FONNTE_TOKEN`
diisi, sehingga tidak ada notifikasi yang berpura-pura terkirim.

**Integrasi akuntansi** nonaktif kecuali `ACC_ACTIVE=true` diset di `.env`.
