# Bug Lintas Modul

Empat bug yang tidak berakar pada satu modul. Perbaikannya terpusat, jadi satu
patch menutup banyak kebocoran sekaligus.

---

## BUG-003 — Manager punya akses tulis penuh tanpa permission

| | |
|---|---|
| **Severity** | 🔴 Kritis (eskalasi hak akses) |
| **Status** | ✅ **SUDAH DIPERBAIKI** — diverifikasi 29/29 pemeriksaan |
| **Test case** | `*/A-mgr-write` di seluruh modul |

### Reproduksi

Login `budi@demo.test` (role `manager`), buka `/admin/branch/create`, isi form,
klik Simpan.

### Hasil aktual

Cabang tersimpan. Form create juga terbuka (HTTP 200) untuk **19 entity**:

| Modul | Entity yang bocor |
|---|---|
| Users | `user` |
| Absensi | `schedule`, `national-holiday`, `presence` |
| Kasbon | `loan`, `loan-payment` |
| Penggajian | `salary` |
| Profil Perusahaan | `company-profile` |
| Akuntansi | `acc` |
| Organisasi | `branch`, `department`, `position` |
| Cuti | `leave-type`, `leave-balance` |
| Dokumen | `document-type` |
| Pajak & BPJS | `bpjs-rate`, `ptkp-rate`, `pph21-bracket`, `tax-profile` |

### Hasil diharapkan

HTTP 403. Role `manager` hanya punya **14 permission dan semuanya bersifat baca**
(`presence.view`, `salary.view`, `report.view`, `approval.act`, `org.view`, dst.)
— tidak ada satu pun `.create`, `.edit`, atau `.delete`.

### Akar masalah

Permission tersimpan rapi di database tetapi **tidak pernah diperiksa**.
[routes/backpack/custom.php](../../routes/backpack/custom.php#L24-L31) hanya
memasang middleware `web` + `admin`, tanpa `permission:` sama sekali:

```php
Route::group([
    'prefix'     => config('backpack.base.route_prefix', 'admin'),
    'middleware' => array_merge(
        (array) config('backpack.base.web_middleware', 'web'),
        (array) config('backpack.base.middleware_key', 'admin')   // ← hanya cek "admin"
    ),
    'namespace'  => 'App\Http\Controllers\Admin',
], function () { … });
```

Hanya **4 dari 29** controller yang memasang guard sendiri —
[RoleCrudController](../../app/Http/Controllers/Admin/RoleCrudController.php#L23),
[PermissionCrudController](../../app/Http/Controllers/Admin/PermissionCrudController.php#L20),
[ApprovalFlowCrudController](../../app/Http/Controllers/Admin/ApprovalFlowCrudController.php#L28),
[ApprovalFlowStepCrudController](../../app/Http/Controllers/Admin/ApprovalFlowStepCrudController.php#L29).
Keempatnya memang menolak manager dengan benar (403), yang membuktikan
mekanismenya jalan — hanya belum dipasang di tempat lain.

Menu sidebar pun menampilkan **44 tautan admin** kepada manager.

### Perbaikan yang disarankan

Pasang middleware `permission:` per kelompok route. Pendekatan ini terpusat,
tidak menyentuh 29 controller satu per satu:

```php
// routes/backpack/custom.php
Route::group([...], function () {

    // Baca untuk semua admin, tulis hanya bagi yang berhak.
    Route::crud('user', 'UserCrudController')->middleware('permission:user.view');
    Route::group(['middleware' => 'permission:user.create'], function () { … });

    // Atau, lebih ringkas — kelompokkan per domain:
    Route::group(['middleware' => 'permission:salary.edit'], function () {
        Route::crud('salary', 'SalaryCrudController');
    });
    Route::group(['middleware' => 'permission:leave.configure'], function () {
        Route::crud('leave-type', 'LeaveTypeCrudController');
        Route::crud('leave-balance', 'LeaveBalanceCrudController');
    });
    // …dst untuk pajak, organisasi, dokumen, akuntansi
});
```

Untuk memisahkan baca vs tulis dalam satu CRUD, gunakan `denyAccess` di
`setupListOperation()` masing-masing controller:

```php
if (! backpack_user()->can('user.create')) CRUD::denyAccess(['create']);
if (! backpack_user()->can('user.edit'))   CRUD::denyAccess(['update']);
if (! backpack_user()->can('user.delete')) CRUD::denyAccess(['delete']);
```

Sembunyikan pula item sidebar yang tidak berhak di
[menu_items.blade.php](../../resources/views/vendor/backpack/ui/inc/menu_items.blade.php)
memakai `backpack_user()->can(...)` — ingat, `@can` bawaan Spatie membaca guard
`web` sehingga selalu false untuk admin.

### Verifikasi setelah perbaikan

`node tests/browser/crud-suite.mjs` — seluruh `*/A-mgr-write` harus PASS
(manager mendapat 403/404 pada form create).

---

## BUG-005 — Delapan entity tanpa validasi server → HTTP 500

| | |
|---|---|
| **Severity** | 🔴 Kritis (setiap pengguna bisa memicunya) |
| **Status** | ✅ **SUDAH DIPERBAIKI** — 8 entity, diverifikasi 12/12 |
| **Test case** | `*/V-empty` |

### Reproduksi

Buka form create salah satu entity di bawah, **langsung klik Simpan** tanpa
mengisi apa pun.

### Hasil aktual

HTTP 500 dengan galat SQL mentah, bukan pesan validasi:

| Modul | Entity | Kolom yang meledak |
|---|---|---|
| Absensi | `national-holiday` | `date` |
| Cuti | `leave-balance` | `user_id` |
| Pajak | `tax-profile` | `user_id` |
| Pajak | `ptkp-rate` | `year` |
| Pajak | `pph21-bracket` | `year` |
| Pajak | `bpjs-rate` | `year` |
| Pengaturan | `approval-flow` | `name` |
| Pengaturan | `approval-flow-step` | `approval_flow_id` |

```
SQLSTATE[HY000]: General error: 1364 Field 'date' doesn't have a default value
```

### Hasil diharapkan

Kembali ke form dengan pesan validasi di bawah field yang kosong — persis
seperti 15 entity lain yang sudah benar (`user`, `schedule`, `salary`, `loan`,
`loan-payment`, `presence`, `acc`, `company-profile`, `branch`, `department`,
`position`, `leave-type`, `document-type`, `day`, `schedule-day-off`).

### Akar masalah

Entity yang bocor hampir semuanya modul HRIS yang **baru ditambahkan** (M0 dan
M5) — modul lama punya Form Request lengkap, modul baru melewatkannya.

Khusus `national-holiday` penyebabnya paling kentara: seluruh aturan di
[NationalHolidayRequest.php](../../app/Http/Requests/NationalHolidayRequest.php)
**dikomentari**, sehingga `rules()` mengembalikan array kosong.

```php
public function rules()
{
    return [
        // 'name' => 'required|min:5|max:255'
    ];
}
```

Aturan yang dikomentari itu pun menyebut `name`, padahal form memakai `date` dan
`info` — jadi seandainya diaktifkan apa adanya, tetap salah sasaran.

### Perbaikan yang disarankan

Tambahkan validasi di `setupCreateOperation()` masing-masing controller,
mengikuti pola yang sudah dipakai `BranchCrudController` dan
`LeaveTypeCrudController`:

```php
// NationalHolidayCrudController
CRUD::setValidation([
    'date' => 'required|date|unique:national_holidays,date',
    'info' => 'required|string|max:255',
]);

// LeaveBalanceCrudController
CRUD::setValidation([
    'user_id'       => 'required|exists:users,id',
    'leave_type_id' => 'required|exists:leave_types,id',
    'year'          => 'required|integer|min:2000|max:2100',
    'quota'         => 'required|integer|min:0|max:365',
    'carry_over'    => 'nullable|integer|min:0|max:365',
    'used'          => 'nullable|integer|min:0',
]);

// PtkpRateCrudController
CRUD::setValidation([
    'year'   => 'required|integer|min:2000|max:2100',
    'status' => 'required|string|max:10',
    'amount' => 'required|integer|min:0',
]);

// Pph21BracketCrudController
CRUD::setValidation([
    'year'        => 'required|integer|min:2000|max:2100',
    'lower_bound' => 'required|integer|min:0',
    'upper_bound' => 'nullable|integer|gt:lower_bound',
    'rate'        => 'required|numeric|min:0|max:100',
]);

// BpjsRateCrudController
CRUD::setValidation([
    'year'          => 'required|integer|min:2000|max:2100',
    'type'          => 'required|string|max:30',
    'employee_rate' => 'required|numeric|min:0|max:100',
    'employer_rate' => 'required|numeric|min:0|max:100',
    'max_salary'    => 'nullable|integer|min:0',
]);

// EmployeeTaxProfileCrudController
CRUD::setValidation([
    'user_id'    => 'required|exists:users,id',
    'tax_status' => 'required|string|max:10',
    'tax_method' => 'required|string|max:20',
    'npwp'       => 'nullable|string|max:25',
]);

// ApprovalFlowCrudController
CRUD::setValidation([
    'name'   => 'required|string|max:100',
    'module' => 'required|in:leave,loan,overtime',
]);

// ApprovalFlowStepCrudController
CRUD::setValidation([
    'approval_flow_id' => 'required|exists:approval_flows,id',
    'step_order'       => 'required|integer|min:1',
    'approver_type'    => 'required|in:role,manager,user',
    'approver_role_id' => 'required_if:approver_type,role|nullable|exists:roles,id',
    'approver_user_id' => 'required_if:approver_type,user|nullable|exists:users,id',
]);
```

Ingat memasang validasi di `setupUpdateOperation()` juga — Backpack tidak
mewarisi validasi create secara otomatis.

### Verifikasi setelah perbaikan

Seluruh `*/V-empty` harus PASS.

---

## BUG-006 — Pelanggaran unique constraint → HTTP 500

| | |
|---|---|
| **Severity** | 🟠 Tinggi |
| **Status** | ✅ **SUDAH DIPERBAIKI** — 4 entity |
| **Akar** | Sama dengan BUG-005 |

### Reproduksi & hasil aktual

Simpan data yang melanggar unique index yang sudah ada:

| Entity | Data | Galat |
|---|---|---|
| `tax-profile` | `user_id=4` (sudah punya profil) | `1062 Duplicate entry '4'` |
| `leave-balance` | user 4 + jenis 1 + tahun 2026 | `1062 Duplicate entry '4-1-2026'` |
| `bpjs-rate` | tahun 2026 + tipe `kesehatan` | `1062 Duplicate entry '2026-kesehatan'` |
| `ptkp-rate` | tahun 2026 + status `TK/0` | `1062 Duplicate entry '2026-TK/0'` |

### Hasil diharapkan

Pesan validasi seperti "Profil pajak untuk karyawan ini sudah ada."

### Perbaikan

Tercakup oleh BUG-005 — tambahkan aturan `unique` pada validasi:

```php
// tax-profile
'user_id' => 'required|exists:users,id|unique:employee_tax_profiles,user_id',

// leave-balance — unique gabungan
'user_id' => [
    'required',
    Rule::unique('leave_balances')
        ->where(fn ($q) => $q->where('leave_type_id', request('leave_type_id'))
                             ->where('year', request('year'))),
],

// bpjs-rate
'year' => [
    'required',
    Rule::unique('bpjs_rates')->where(fn ($q) => $q->where('type', request('type'))),
],

// ptkp-rate
'year' => [
    'required',
    Rule::unique('ptkp_rates')->where(fn ($q) => $q->where('status', request('status'))),
],
```

Pada `setupUpdateOperation()`, tambahkan `->ignore($id)` agar menyimpan tanpa
mengubah nilai unique tidak ditolak — pola yang sudah dipakai benar di
[UserRequest::updateRules()](../../app/Http/Requests/UserRequest.php).

---

## BUG-007 — Test suite gagal setiap akhir pekan

| | |
|---|---|
| **Severity** | 🟡 Sedang — CI merah dua hari sekali seminggu |
| **Status** | ✅ **SUDAH DIPERBAIKI** — 150/150 lulus di hari Sabtu |
| **Berkas** | [tests/Feature/DashboardServiceTest.php:147](../../tests/Feature/DashboardServiceTest.php#L147) |

### Gejala

`./vendor/bin/phpunit` lulus 150/150 pada Senin–Jumat, tetapi gagal pada
Sabtu–Minggu:

```
1) Tests\Feature\DashboardServiceTest::test_approved_leave_is_not_counted_as_absent
only the manager is unaccounted for — the person on leave is not absent
Failed asserting that 2 is identical to 1.
```

Diverifikasi langsung: suite lulus penuh pada Jumat 2026-08-07, gagal pada
Sabtu 2026-08-08 tanpa ada perubahan kode di antaranya.

### Akar masalah

Presensi dicatat untuk **hari ini**, tetapi snapshot diambil untuk **hari kerja
berikutnya**:

```php
$present = $this->user('Present');
$this->presence($present, now());                 // ← dicatat SABTU

// Use a weekday so the day is chargeable leave.
$day = now()->isWeekend() ? now()->next(Carbon::MONDAY) : now();   // ← SENIN
…
$today = $this->dashboard->todaySnapshot($day, false);             // ← snapshot SENIN
$this->assertSame(1, $today['absent']);
```

Pada hari kerja `$day === now()`, sehingga keduanya sejalan dan test lulus.
Pada akhir pekan `$day` melompat ke Senin, sementara baris presensi tetap di
Sabtu — akibatnya `Present` ikut terhitung absen dan `absent` menjadi 2, bukan 1.

Jadi yang gagal adalah **testnya**, bukan `DashboardService`. Perilaku aplikasi
yang diuji (cuti disetujui tidak dihitung absen) tetap benar — `on_leave` tetap
bernilai 1 seperti yang diharapkan.

### Perbaikan

Hitung `$day` lebih dulu, lalu pakai untuk mencatat presensi:

```php
// Use a weekday so the day is chargeable leave.
$day = now()->isWeekend() ? now()->next(Carbon::MONDAY) : now();

$manager = $this->user('Manager');
$onLeave = $this->user('On Leave', ['manager_id' => $manager->id]);
$present = $this->user('Present');
$this->presence($present, $day);        // ← catat pada hari yang sama dengan snapshot
```

Alternatif yang lebih tahan lama: bekukan waktu di awal test sehingga tidak
bergantung pada kapan CI berjalan.

```php
Carbon::setTestNow(Carbon::parse('2026-08-10 09:00:00'));   // Senin tetap
// …
Carbon::setTestNow();                                        // di tearDown
```

Pendekatan kedua lebih disarankan. Telusuri juga test lain yang memanggil
`now()` tanpa membekukannya — pola yang sama bisa mengintai di berkas lain.

### Verifikasi

Jalankan pada hari Sabtu, atau bekukan waktu ke akhir pekan:

```bash
./vendor/bin/phpunit --filter test_approved_leave_is_not_counted_as_absent
```

---

## BUG-004 — Scoping tim manager tidak diterapkan

| | |
|---|---|
| **Severity** | 🟠 Tinggi (kerahasiaan data) |
| **Status** | ✅ **SUDAH DIPERBAIKI** — manager kini melihat 3/5 karyawan, 64/110 presensi |
| **Test case** | `user/A-mgr-scope`, `presence/A-mgr-scope` |

### Reproduksi

Login `budi@demo.test`, buka `/admin/user` lalu `/admin/presence`.

### Hasil aktual

| Halaman | Manager melihat | Seharusnya |
|---|---|---|
| `/admin/user` | **5 dari 5** karyawan | hanya timnya |
| `/admin/presence` | **110 dari 110** presensi | hanya timnya |
| `/admin/approval` | **1 dari 2** ✅ | benar |

Sama persis dengan yang dilihat super_admin.

### Hasil diharapkan

[HRIS_SETUP.md](../HRIS_SETUP.md) menyebut cakupan manager adalah
"Team visibility + acting on approvals". Modul approval sudah menerapkannya
dengan benar; Users dan Kehadiran belum.

### Perbaikan yang disarankan

Tambahkan penyempitan query di `setupListOperation()`, meniru pola yang sudah
dipakai [ApprovalCrudController](../../app/Http/Controllers/Admin/ApprovalCrudController.php#L30):

```php
// UserCrudController::setupListOperation()
$me = backpack_user();
if (! $me->can('user.view_all')) {          // permission baru
    CRUD::addClause('where', function ($q) use ($me) {
        $q->where('manager_id', $me->id)->orWhere('id', $me->id);
    });
}

// PresenceCrudController::setupListOperation()
if (! $me->can('presence.view_all')) {
    CRUD::addClause('whereHas', 'user', function ($q) use ($me) {
        $q->where('manager_id', $me->id)->orWhere('id', $me->id);
    });
}
```

**Keputusan produk yang perlu diambil dulu:** apakah "tim" berarti bawahan
langsung (`manager_id`) saja, atau seluruh sub-pohon departemen yang dipimpin?
Bila yang kedua, gunakan `Department::descendants()` yang sudah ada dan sudah
tahan terhadap data bersiklus.

Angka di dashboard dan laporan juga perlu mengikuti scope yang sama — lihat
[15-dashboard-laporan.md](15-dashboard-laporan.md).

### Verifikasi setelah perbaikan

`user/A-mgr-scope` dan `presence/A-mgr-scope` harus PASS.
