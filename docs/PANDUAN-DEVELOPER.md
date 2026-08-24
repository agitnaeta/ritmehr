# Panduan Developer

Cara kerja aplikasi ini dari sisi kode: arsitektur, konvensi yang dipakai,
jebakan yang sudah pernah menjatuhkan orang, dan cara menambah modul baru.

Untuk cara memakai aplikasinya, lihat [PANDUAN-PENGGUNA.md](PANDUAN-PENGGUNA.md).

---

## Daftar isi

- [Menyiapkan lingkungan](#menyiapkan-lingkungan)
- [Tumpukan teknologi](#tumpukan-teknologi)
- [Peta kode](#peta-kode)
- [Empat jebakan yang wajib diketahui](#empat-jebakan-yang-wajib-diketahui)
- [Konvensi: hak akses](#konvensi-hak-akses)
- [Konvensi: validasi](#konvensi-validasi)
- [Konvensi: filter daftar](#konvensi-filter-daftar)
- [Menambah modul CRUD baru](#menambah-modul-crud-baru)
- [Engine persetujuan](#engine-persetujuan)
- [Jejak audit](#jejak-audit)
- [Notifikasi](#notifikasi)
- [Perhitungan gaji dan pajak](#perhitungan-gaji-dan-pajak)
- [Pengujian](#pengujian)
- [Perintah artisan](#perintah-artisan)
- [Sebelum rilis](#sebelum-rilis)

---

## Menyiapkan lingkungan

```bash
composer install
cp .env.example .env
php artisan key:generate

docker compose up -d          # MySQL 8 di port host 3307
php artisan migrate
php artisan db:seed --class=HrisSeeder      # data referensi, idempoten
php artisan db:seed --class=DemoDataSeeder  # opsional, 5 karyawan + 1 bulan data
php artisan serve
```

> `.env.example` mengirim `DB_PORT=3306`, sedangkan `docker-compose.yml`
> memetakan MySQL ke **3307** agar tidak bentrok dengan MySQL lokal. Ubah
> manual setelah menyalin.

`HrisSeeder` menjalankan seluruh seeder data referensi dan **idempoten** — aman
dijalankan ulang setelah upgrade. Ia memanggil `RolesAndPermissionsSeeder`,
`ApprovalFlowSeeder`, `LeaveTypeSeeder`, `DocumentTypeSeeder`, `TaxRateSeeder`.

---

## Tumpukan teknologi

| Komponen | Versi | Catatan |
|---|---|---|
| PHP | ^8.1 | |
| Laravel | ^10.10 | |
| `backpack/crud` | ^6.5 | **Edisi gratis** — beberapa fitur di dokumentasi resmi tidak tersedia |
| `backpack/theme-tabler` | ^1.2 | |
| `spatie/laravel-permission` | ^6.25 | Role & permission |
| `maatwebsite/excel` | ^3.1 | Export Excel |
| `barryvdh/laravel-dompdf` | ^2.0 | Slip gaji & kartu ID |
| `simplesoftwareio/simple-qrcode` | ^4.2 | QR karyawan |
| MySQL | 8.0 | Lewat Docker |
| Playwright | ^1.62 | Pengujian browser (devDependency) |

---

## Peta kode

```
app/
├── Http/Controllers/Admin/     29 CRUD controller Backpack
├── Http/Controllers/Portal/    PortalController — seluruh /my/*
├── Http/Middleware/            CheckIfAdmin, CheckPermission, CheckRole, EnsurePortalAccess
├── Http/Requests/              Form Request untuk modul lama
├── Models/                     termasuk Role & Permission lokal (lihat jebakan #2)
├── Observers/                  PresenceObserver, SalaryRecapObserver, UserObserver
├── Services/                   logika bisnis — di sinilah aturan sesungguhnya
│   ├── ApprovalService         submit, approve, reject, cancel
│   ├── LeaveService            kuota, tumpang tindih, hari kerja
│   ├── PresenceService         geofence, keterlambatan, lembur
│   ├── SalaryService           perhitungan rekap gaji
│   ├── TaxService              PPh 21, BPJS, THR
│   ├── DashboardService        agregat + cache 5 menit
│   └── Notifications/          WhatsAppGateway + Fonnte & Log
└── Traits/
    ├── Auditable               catat perubahan ke audit_logs
    ├── HasApproval             jadikan model bisa disetujui
    ├── HasSimpleFilters        pengganti addFilter() yang berbayar
    └── ResolvesAuthenticatedUser  jembatan dua guard

routes/backpack/custom.php      seluruh route admin + 2 route publik di akhir
routes/web.php                  root redirect + portal /my
```

Aturan tak tertulis: **logika bisnis di Service, bukan di controller.** Controller
Backpack hanya mengatur field, kolom, dan hak akses.

---

## Empat jebakan yang wajib diketahui

### 1. Dua guard — `@can` selalu false untuk admin

Backpack mengautentikasi admin pada guard **`backpack`**. Spatie menyimpan role
pada guard **`web`** (default Laravel). Akibatnya:

```blade
{{-- ❌ SELALU false untuk admin yang sedang login --}}
@can('user.view') ... @endcan
@role('hr_admin') ... @endrole

{{-- ✅ --}}
@if(backpack_user()->can('user.view')) ... @endif
```

Di controller, `$request->user()` juga **null** untuk admin. Gunakan
`backpack_user()`, atau trait `ResolvesAuthenticatedUser` yang sudah dipakai
middleware `role` dan `permission`.

### 2. Model CRUD wajib memakai `CrudTrait`

Model `Role` dan `Permission` milik Spatie tidak memakainya. Karena itu ada
`App\Models\Role` dan `App\Models\Permission` yang hanya membungkus milik Spatie
untuk menambahkan trait tersebut. **Arahkan CRUD controller dan relasi ke kelas
lokal ini**, bukan ke kelas Spatie.

### 3. `addFilter()` berbayar

`backpack/crud` edisi gratis melempar `BackpackProRequiredException` untuk
`addFilter()`. Penggantinya trait `HasSimpleFilters`:

```php
$this->applySimpleFilters([...]);   // ✅
CRUD::addFilter([...]);             // ❌ meledak di edisi gratis
```

Trait itu membaca parameter GET biasa, menerapkannya dengan `addClause()`, dan
merender bilah filter sendiri.

### 4. Baris tabel dimuat lewat AJAX

Daftar Backpack memuat barisnya lewat `POST /admin/<entity>/search`. Mengambil
URL daftar dengan `curl` **hanya menghasilkan kerangka tabel kosong**. Untuk
memverifikasi isi data, pakai browser atau tembak endpoint `search` langsung.

Perhatikan responsnya: `recordsTotal` adalah jumlah **sebelum** filter,
`recordsFiltered` yang benar-benar ditampilkan. Salah membaca ini pernah
menghasilkan laporan bug palsu.

---

## Konvensi: hak akses

Ditegakkan di **dua lapis**.

**Lapis 1 — middleware pada route group.** Untuk modul yang seluruhnya tertutup
bagi sebuah peran:

```php
// routes/backpack/custom.php
Route::group(['middleware' => 'permission:tax.view'], function () {
    Route::crud('ptkp-rate', 'PtkpRateCrudController');
    ...
});
```

**Lapis 2 — `denyAccess` di controller.** Untuk modul yang boleh **dibaca**
tetapi tidak **ditulis** oleh sebuah peran:

```php
public function setup()
{
    // ...
    if (! backpack_user()->can('salary.edit')) {
        CRUD::denyAccess(['create', 'update', 'delete']);
    }
}
```

**Sidebar juga harus disaring**, kalau tidak pengguna melihat menu yang berujung
403:

```blade
@php($me = backpack_auth()->check() ? backpack_user() : null)
@if($me?->can('tax.view'))
    <x-backpack::menu-dropdown title="Pajak & BPJS" ...>
@endif
```

### Peran dan izin

| Peran | Jumlah izin | Sifat |
|---|---|---|
| `super_admin` | 61 | semuanya |
| `hr_admin` | 57 | semua operasi HR; tanpa `role.*`, `permission.*`, `approval.configure` |
| `manager` | 15 | **hanya baca** + `approval.act` |
| `employee` | 9 | portal saja |

Penamaan izin: `<modul>.<aksi>` — `user.view`, `leave.approve`, `tax.edit`.

Dua izin yang perlu dipahami bedanya:

- `user.view` — boleh membuka menu Users
- `user.view_all` — boleh melihat **seluruh** karyawan, bukan hanya timnya

### Scoping tim

Satu definisi, dipakai di semua tempat:

```php
// app/Models/User.php
public function scopeVisibleTo($query, ?self $viewer)
{
    if (! $viewer || $viewer->can('user.view_all')) {
        return $query;
    }
    return $query->where(fn ($q) => $q
        ->where('manager_id', $viewer->id)->orWhere('id', $viewer->id));
}
```

Dipakai langsung di daftar karyawan, dan lewat relasi di daftar presensi:

```php
$this->crud->addClause('whereHas', 'user', fn ($q) => $q->visibleTo($me));
```

> **Jangan membuat definisi tim kedua.** Kalau daftar, laporan, dan angka
> dashboard menyempit dengan cara berbeda, salah satunya akan bocor.

---

## Konvensi: validasi

Pola yang dipakai proyek ini — override `store()` dan `update()`, panggil satu
metode validasi:

```php
class BranchCrudController extends CrudController
{
    use CreateOperation { store as traitStore; }
    use UpdateOperation { update as traitUpdate; }

    public function store()  { $this->validatePayload(); return $this->traitStore(); }
    public function update() { $this->validatePayload(); return $this->traitUpdate(); }

    private function validatePayload(): void
    {
        $id = request()->input('id');   // Backpack mengirim id saat update

        request()->validate([
            'name' => 'required|string|max:100',
            'code' => 'nullable|string|max:20|unique:branches,code' . ($id ? ",{$id}" : ''),
        ], [
            'name.required' => 'Nama cabang wajib diisi.',
        ]);
    }
}
```

Modul lama memakai Form Request (`app/Http/Requests/`) — keduanya berlaku.
Yang penting:

- **Pesan validasi berbahasa Indonesia.** Ini yang dilihat pengguna.
- **Unique wajib mengabaikan baris sendiri saat update**, kalau tidak menyimpan
  tanpa mengubah nilai unique akan ditolak.
- **Backpack tidak mewarisi validasi create ke update.** Pasang di keduanya.
- **Jangan lupa `min:1` pada kolom nominal.** `integer` saja menerima nol dan
  bilangan negatif — pernah menjadi bug yang membuat kasbon negatif menambah
  gaji.
- **Kolom generated jangan divalidasi.** `leave_balances.remaining` dihitung
  database (`quota + carry_over - used`) dan tidak bisa ditulis.

Contoh unique gabungan:

```php
'year' => [
    'required', 'integer',
    Rule::unique('bpjs_rates', 'year')
        ->where(fn ($q) => $q->where('type', request('type')))
        ->ignore($id),
],
```

---

## Konvensi: filter daftar

```php
protected function setupListOperation()
{
    $this->applySimpleFilters([
        'user_id' => ['type' => 'select', 'model' => User::class],
        'month'   => ['type' => 'month'],
    ]);
}
```

Trait `HasSimpleFilters` membaca parameter GET, menerapkan `addClause()`, dan
merender bilah filter dari
`resources/views/vendor/backpack/crud/buttons/simple_filters.blade.php`.

---

## Menambah modul CRUD baru

Urutan yang sudah terbukti:

1. **Migrasi** — buat tabel
2. **Model** — pakai `CrudTrait`; tambahkan `Auditable` bila perubahannya perlu
   dicatat
3. **Izin** — tambahkan `<modul>.view` dan `<modul>.edit` di
   [RolesAndPermissionsSeeder](../database/seeders/RolesAndPermissionsSeeder.php),
   lalu berikan ke peran yang berhak. **Jangan lupa langkah ini** — tanpa izin,
   hak aksesnya tidak bisa ditegakkan sama sekali
4. **Controller** — `setup()`, `setupListOperation()`, `setupCreateOperation()`,
   `setupUpdateOperation()`; pasang validasi dan `denyAccess`
5. **Route** — bungkus dengan `middleware permission:<modul>.view`
6. **Sidebar** — tambahkan item, disaring dengan `$me?->can(...)`
7. **Jalankan seeder** — `php artisan db:seed --class=RolesAndPermissionsSeeder`
8. **Test** — tambahkan entity ke `ENTITIES` di
   [tests/browser/crud-suite.mjs](../tests/browser/crud-suite.mjs)

Catatan langkah 8: entity dengan field checkbox atau multi-select tidak bisa
dibuat lewat POST datar. Kirim sebagai array (`'day_off[]' => '2'`) atau pakai
interaksi form.

---

## Engine persetujuan

Tabel: `approval_flows`, `approval_flow_steps`, `approvals`, `approval_actions`.

Satu alur aktif per modul (`leave`, `loan`, `overtime`). Setiap langkah berurutan
menunjuk approver berdasarkan **role**, **atasan pemohon**, atau **user
tertentu**.

Membuat model bisa disetujui:

```php
class LeaveRequest extends Model
{
    use \App\Traits\HasApproval;

    public function approvalModule(): string { return 'leave'; }

    public function onApprovalApproved($approval)  { /* ... */ }
    public function onApprovalRejected($approval)  { /* ... */ }
    public function onApprovalCancelled($approval) { /* ... */ }
}
```

`ApprovalService`: `submitForApproval`, `approve`, `reject`, `cancel`,
`getNextApprovers`, `getPendingForUser`.

**Jaminan konkurensi.** Setiap perubahan status berjalan dalam transaksi dengan
`SELECT … FOR UPDATE` pada baris approval, dan otorisasi diperiksa ulang terhadap
langkah yang berlaku **saat lock diambil**. Dua approver yang berlomba tidak bisa
dua-duanya berhasil. Satu approval hidup per record dijaga **unique index**,
bukan hanya kode aplikasi.

**Jenis galat:**

- `\DomainException` — kesalahan pemanggil: approver salah, status bukan pending,
  alasan kosong, submit ganda
- `\RuntimeException` — salah konfigurasi: tidak ada alur aktif, alur tanpa
  langkah

**Regresi yang pernah terjadi.** Relasi `actions()` membawa
`orderBy('step_order')`. Menambahkan `->latest('acted_at')` menghasilkan
`ORDER BY step_order ASC, acted_at DESC`, sehingga langkah 1 tetap menang dan
pada rantai manager→HR **manager keliru dicatat sebagai penyetuju akhir**.
Diperbaiki dengan `->reorder()`. Kalau menyentuh relasi ini, jalankan
regression test-nya.

---

## Jejak audit

```php
class User extends Authenticatable
{
    use \App\Traits\Auditable;
}
```

Trait mencatat create, update, dan delete ke `audit_logs`, **menyaring kolom
`$hidden` model lebih dulu**:

```php
public function auditableValues(array $values): array
{
    $rahasia = array_merge(
        $this->getHidden(),
        property_exists($this, 'auditExclude') ? $this->auditExclude : [],
    );
    return array_diff_key($values, array_flip($rahasia));
}
```

Penyaringan itu penting: tanpa itu hash password ikut tersalin ke `audit_logs` —
tabel kedua yang dibaca lebih banyak orang. Kalau sebuah model punya kolom
rahasia yang tidak ada di `$hidden`, deklarasikan `$auditExclude`.

Pemangkasan: `php artisan audit:prune --days=90`, terjadwal bulanan.

---

## Notifikasi

Kanal: **database** (selalu ditulis, inilah jejaknya), **email**, **WhatsApp**.

Aturan penting: **pengiriman tidak boleh melempar exception.** Kegagalan mail
atau gateway tidak boleh membatalkan aksi bisnis yang memicunya.

WhatsApp jatuh ke `LogWhatsAppGateway` (hanya mencatat) sampai `FONNTE_TOKEN`
diisi — supaya tidak ada notifikasi yang berpura-pura terkirim.

---

## Perhitungan gaji dan pajak

`SalaryService` membedakan tiga keadaan ketidakhadiran:

| Keadaan | Dihitung absen | Dipotong |
|---|---|---|
| Cuti **berbayar** disetujui | tidak | tidak |
| Cuti **tidak berbayar** disetujui | tidak | ya |
| Tidak hadir tanpa keterangan | ya | ya |

Pengajuan yang masih pending tidak memaafkan apa pun. Rujukan:
`tests/Feature/SalaryLeaveIntegrationTest.php`.

`TaxService`:

- `calculateBPJS` — Kesehatan 1%/4% (plafon 12.000.000), JHT 2%/3,7% (tanpa
  plafon), JP 1%/2% (plafon 10.042.300), JKK & JKM **pemberi kerja saja**
- `calculatePPh21` — dianualisasi: bruto × 12, kurangi biaya jabatan (5%, plafon
  6.000.000/tahun), kurangi JHT+JP karyawan, kurangi PTKP, lapisan progresif,
  ÷ 12. Tanpa NPWP kena tambahan 20%
- `calculateTHR` — satu bulan penuh setelah 12 bulan kerja, prorata di bawahnya,
  nihil di bawah satu bulan

Tarif disimpan **per tahun** — pemerintah merevisinya, dan perhitungan historis
harus memakai tarif periodenya sendiri. Bila tahunnya belum ada, dipakai tahun
terbit terakhir, bukan nol.

> ⚠️ `TaxRateSeeder` mengacu PMK 101/2016 dan UU HPP No. 7/2021 saat ditulis, JKK
> kelas risiko terendah (0,24%). **Verifikasi terhadap regulasi terbaru sebelum
> payroll sungguhan.**

Geofence: urutan resolusi adalah **cabang pada baris presensi → cabang user →
config global**. Tanpa titik referensi mana pun, scan dianggap **di dalam
kantor** — bukan menandai semua orang di luar. Presensi menyimpan cabangnya
sendiri, sehingga menghitung ulang riwayat setelah karyawan pindah kantor tidak
mengubah atribusinya.

---

## Pengujian

### PHPUnit

```bash
./vendor/bin/phpunit          # 150 test
```

Berjalan pada skema `absensi_testing` terpisah (lihat `phpunit.xml`), jadi tidak
menyentuh data pengembangan.

> **Bekukan waktu** di test yang bergantung tanggal. Satu test pernah gagal
> setiap akhir pekan karena mencatat presensi di hari ini sementara snapshot
> diukur pada hari kerja berikutnya. Pakai `Carbon::setTestNow()`.

### Pengujian browser

```bash
npm install && npx playwright install chromium
php artisan serve
node tests/browser/crud-suite.mjs     # 146 pemeriksaan
node tests/browser/ui-test.mjs
```

Menutup hal yang tidak terjangkau PHPUnit: tabel AJAX, rendering Chart.js,
visibilitas menu per peran, dan **route publik tanpa login**.

> `crud-suite.mjs` **mengubah data**. Cadangkan lebih dulu:
> ```bash
> docker exec absensi-mysql mysqldump -uroot -psecret --single-transaction absensi \
>   > storage/app/backups/pre-crud-test.sql
> ```

Dua prinsip harness yang jangan diubah:

1. **Kebenaran ditentukan dengan membaca balik keadaan** — jumlah baris atau
   nilai field — bukan menebak dari kode redirect. Backpack mengarahkan ke
   `/edit` setelah simpan **berhasil**; membacanya sebagai kegagalan pernah
   menghasilkan delapan laporan bug palsu.
2. **Update diuji lewat interaksi form**, bukan PUT mentah. Payload parsial akan
   ditolak validasi dan menghasilkan kegagalan palsu.

Selalu tambahkan pemeriksaan **tanpa login** untuk route publik. Kedua suite
selalu login lebih dulu, dan celah itu pernah membuat `/scan` mati tanpa
terdeteksi.

---

## Perintah artisan

| Perintah | Fungsi | Jadwal |
|---|---|---|
| `notify:attendance --type=checkin` | Belum absen masuk | hari kerja 08:15 |
| `notify:attendance --type=late` | Terlambat | hari kerja 09:30 |
| `notify:attendance --type=checkout` | Belum absen keluar | hari kerja 17:00 |
| `documents:notify-expiring --days=30` | Dokumen mendekati kedaluwarsa | Senin 07:30 |
| `notify:approval-digest` | Ringkasan approval tertunda | Senin 08:00 |
| `backup:run --only-db` | Cadangan database | harian 23:00 |
| `db:seed RecalculatePresence` | Hitung ulang presensi | harian 23:30 |
| `audit:prune --days=90` | Pangkas audit log | bulanan tgl 1, 02:00 |
| `leave:generate-balances --carry-over --max-carry=6` | Saldo cuti tahunan | tahunan |
| `calculate:salary` | Hitung rekap gaji | manual |
| `salary:recalculate` | Hitung ulang gaji per user/bulan | manual |
| `import:presence-command` | Impor presensi | manual |

Log viewer tersedia di `/log-viewer`.

---

## Sebelum rilis

- [ ] Verifikasi tarif pajak dan BPJS terhadap regulasi terbaru
- [ ] Unggah logo perusahaan dan latar ID card di **Profile Perusahaan** — tanpa
      logo, slip gaji pernah gagal terbit
- [ ] Atur koordinat dan radius setiap cabang
- [ ] Isi `manager_id` seluruh karyawan — menentukan rantai persetujuan **dan**
      visibilitas data manager
- [ ] Pastikan setiap modul punya satu alur persetujuan aktif dengan minimal satu
      langkah
- [ ] Set `FONNTE_TOKEN` bila WhatsApp dipakai
- [ ] Set `ACC_ACTIVE=true` bila integrasi akuntansi dipakai
- [ ] Pastikan scheduler berjalan (`php artisan schedule:work` atau cron)
- [ ] Jalankan kedua suite pengujian
- [ ] Periksa `audit_logs` tidak memuat kolom rahasia

---

## Rujukan lain

| Berkas | Isi |
|---|---|
| [HRIS_SETUP.md](HRIS_SETUP.md) | Referensi modul M0–M8 dan keputusan arsitekturnya |
| [BUSINESS_FLOW.md](BUSINESS_FLOW.md) | Alur bisnis |
| [test-cases/](test-cases/README.md) | 725 test case CRUD per modul |
| [bug-list/](bug-list/README.md) | 12 bug beserta akar masalah dan perbaikannya |
| [UI_TEST_CASES.md](UI_TEST_CASES.md) | Test case UI + matriks akses per peran |
| [MODULE_PLANS.md](MODULE_PLANS.md) | Rencana modul |
