# Tunjangan Karyawan Inline di Form Gaji — Implementation Plan

> **For Hermes:** Eksekusi task-by-task dengan TDD + notif Telegram tiap fase (pola M20).

**Goal:** Pindahkan pengisian tunjangan karyawan dari menu terpisah (`/admin/employee-salary-allowance/create`) ke dalam form Gaji (`/admin/salary/create` & `/edit`), sehingga HR mengisi gaji pokok + semua tunjangan dalam **satu form**.

**Architecture:** Karena tunjangan sudah punya **master global** (`salary_allowance_types`), form Gaji cukup render **satu input nominal per jenis tunjangan aktif** (blank = 0/dilewati, sesuai keputusan M20). Saat store/update, loop upsert ke `employee_salary_allowances` → observer existing otomatis me-recalc `amount`. **Tak butuh Backpack PRO** (no repeatable/table field — pitfall M17/M19/M20).

**Tech Stack:** Laravel 10 + Backpack CRUD (free), MySQL, PHPUnit + Playwright.

---

## Konteks & Temuan (kode nyata)

- `SalaryCrudController` pakai `setFromDb()` + `fieldModification()` untuk create/update/show. `store()`/`update()` custom (baris ~195–213): `Salary::create($request->all())` / `$salary->update(...)` lalu redirect ke `salary.index`.
- `Salary` model punya boot `saving` hook + `EmployeeSalaryAllowanceObserver` yang jaga `amount = basic_salary + Σ tunjangan aktif`. **Ini kunci**: kalau kita cukup meng-upsert baris `employee_salary_allowances`, observer + hook otomatis benerin total → pajak/BPJS tetap as-is.
- `employee_salary_allowances`: `user_id`, `salary_allowance_type_id`, `amount`, `unique(user_id, salary_allowance_type_id)`.
- `SalaryAllowanceType`: `label`, `is_active`, `sort_order`, scope `active()`.
- `SalaryRequest` sudah `basic_salary required`, `amount nullable`.

**Keputusan desain (dari M20, tetap berlaku):**
- Tunjangan = master global, label bebas. Per-karyawan isi nominal. Tak diisi → 0/dilewati.
- Total = Gaji Pokok + Σ tunjangan aktif. Pajak/BPJS baca `amount` → identik (as-is).

**Assumsi:**
- Jumlah jenis tunjangan wajar (< ~30) → satu input per jenis oke, tak perlu paginasi.
- Menu "Tunjangan Karyawan" (`employee-salary-allowance`) **dipensiunkan dari sidebar** setelah inline jalan; route + CRUD boleh disisakan (read-only) atau dihapus — lihat Task 7 (opsional).
- Menu "Jenis Tunjangan" (`salary-allowance-type`, master) **TETAP ADA** — HR tetap butuh definisikan jenis.

---

## Proposed Approach (ringkas)

1. Render field tunjangan dinamis di form Gaji: satu `number` per `SalaryAllowanceType::active()`, prefill dari nilai existing karyawan (mode edit), nama field `allowance[<type_id>]`.
2. Di `store()`/`update()`: setelah simpan `Salary`, loop tiap type → `updateOrCreate`/`delete` (blank/0 → hapus baris) di `employee_salary_allowances`.
3. Observer existing me-recalc `amount`.
4. Tampilkan Total (read-only) + pensiunkan menu terpisah.

---

## Tantangan & Solusi

**Tantangan:** Backpack free tak punya field `repeatable`/`table` untuk multi-baris dinamis.
**Solusi:** Master types = daftar tetap → generate N field `number` (satu per type) via `CRUD::addField` dalam loop. Nama pakai array-syntax `allowance[<id>]` supaya `request('allowance')` = `[type_id => nominal]`. Ini native Backpack free (field type `number` + `name` custom), tak perlu Blade custom. Kalau mau grup rapi, bungkus dengan field `custom_html` sebagai judul seksi "Tunjangan".

**Tantangan:** Field array `allowance[3]` mungkin tak dikenali validasi/`$request->all()` untuk mass-assign Salary → aman karena kita ambil terpisah via `request('allowance', [])`, tak masuk `Salary::create`.

**Tantangan:** Nilai prefill di mode create (belum ada user terpilih) → create murni: semua kosong; setelah user dipilih tetap kosong (isi manual). Prefill hanya relevan di **edit** (user sudah fix).

---

## Step-by-Step Plan

### Task 1: Test — helper untuk sinkronisasi tunjangan dari request

**Objective:** Tambah method `syncAllowancesFromRequest(int $userId, array $allowance)` yang meng-upsert/hapus baris tunjangan, lalu buktikan lewat unit test.

**Files:**
- Modify: `app/Http/Controllers/Admin/SalaryCrudController.php`
- Test: `tests/Feature/SalaryInlineAllowanceTest.php` (Create)

**Step 1 — Tulis failing test:**
```php
<?php
namespace Tests\Feature;

use App\Models\EmployeeSalaryAllowance;
use App\Models\Salary;
use App\Models\SalaryAllowanceType;
use App\Models\User;
use App\Http\Controllers\Admin\SalaryCrudController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalaryInlineAllowanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_creates_updates_and_deletes_allowance_rows(): void
    {
        $user = User::factory()->create();
        Salary::create(['user_id' => $user->id, 'basic_salary' => 8_000_000, 'overtime_amount' => 0, 'overtime_type' => 'flat']);
        $jab = SalaryAllowanceType::create(['label' => 'Jabatan']);
        $trans = SalaryAllowanceType::create(['label' => 'Transport']);

        $ctrl = new SalaryCrudController();

        // create two
        $ctrl->syncAllowancesFromRequest($user->id, [$jab->id => 1_500_000, $trans->id => 500_000]);
        $this->assertSame(10_000_000, (int) Salary::where('user_id', $user->id)->value('amount'));

        // update one, blank the other → deleted
        $ctrl->syncAllowancesFromRequest($user->id, [$jab->id => 2_000_000, $trans->id => 0]);
        $this->assertSame(10_000_000, (int) Salary::where('user_id', $user->id)->value('amount'));
        $this->assertDatabaseMissing('employee_salary_allowances', ['user_id' => $user->id, 'salary_allowance_type_id' => $trans->id]);
        $this->assertSame(1, EmployeeSalaryAllowance::where('user_id', $user->id)->count());
    }
}
```

**Step 2 — Run (harus FAIL):**
`php -d xdebug.mode=off vendor/bin/phpunit tests/Feature/SalaryInlineAllowanceTest.php --no-coverage`
Expected: error "Call to undefined method syncAllowancesFromRequest".

**Step 3 — Implement di `SalaryCrudController`:**
```php
/**
 * Upsert per-employee allowances from the salary form. Blank/0 removes the row.
 * The EmployeeSalaryAllowance observer then recalcs salaries.amount.
 */
public function syncAllowancesFromRequest(int $userId, array $allowance): void
{
    foreach ($allowance as $typeId => $amount) {
        $typeId = (int) $typeId;
        $amount = (int) $amount;

        if ($amount > 0) {
            \App\Models\EmployeeSalaryAllowance::updateOrCreate(
                ['user_id' => $userId, 'salary_allowance_type_id' => $typeId],
                ['amount' => $amount],
            );
        } else {
            \App\Models\EmployeeSalaryAllowance::where('user_id', $userId)
                ->where('salary_allowance_type_id', $typeId)
                ->delete();
        }
    }
    // Make sure total is fresh even if nothing triggered the observer.
    optional(\App\Models\Salary::where('user_id', $userId)->first())->recalcTotal();
}
```

**Step 4 — Run (harus PASS).**

**Step 5 — Commit:** `git commit -m "feat(salary): sync allowances from salary form request"`

---

### Task 2: Render field tunjangan dinamis di form Gaji

**Objective:** Tampilkan satu input nominal per jenis tunjangan aktif di create & update, prefill nilai existing saat edit.

**Files:**
- Modify: `app/Http/Controllers/Admin/SalaryCrudController.php` (`fieldModification()`)

**Step 1 — Tambah di akhir `fieldModification()` (hanya untuk operasi create/update):**
```php
$op = $this->crud->getCurrentOperation();
if (in_array($op, ['create', 'update'], true)) {
    $types = \App\Models\SalaryAllowanceType::active()->orderBy('sort_order')->orderBy('label')->get();

    // prefill nilai existing saat edit
    $existing = [];
    if ($op === 'update') {
        $entry = $this->crud->getCurrentEntry();
        if ($entry) {
            $existing = \App\Models\EmployeeSalaryAllowance::where('user_id', $entry->user_id)
                ->pluck('amount', 'salary_allowance_type_id')->toArray();
        }
    }

    if ($types->count()) {
        $this->crud->addField([
            'name' => 'allowance_section',
            'type' => 'custom_html',
            'value' => '<h5 class="mt-3 mb-0">Tunjangan</h5><small class="text-muted">Kosongkan bila tidak ada. Total gaji otomatis = pokok + tunjangan.</small>',
        ]);

        $cur = app(\App\Services\CurrencyService::class)->symbol();
        foreach ($types as $t) {
            $this->crud->addField([
                'name'    => "allowance[{$t->id}]",
                'label'   => $t->label,
                'type'    => 'number',
                'prefix'  => $cur,
                'value'   => $existing[$t->id] ?? null,
                'wrapper' => ['class' => 'form-group col-md-6'],
                'attributes' => ['min' => 0, 'step' => 1000],
            ]);
        }
    }
}
```

**Step 2 — Verifikasi manual (browser) di Task 5.** (Tidak ada unit test render field; dicek via Playwright.)

**Step 3 — Commit:** `git commit -m "feat(salary): render per-type allowance inputs on salary form"`

---

### Task 3: Panggil sync di store() dan update()

**Objective:** Saat simpan gaji, upsert tunjangan dari `request('allowance')`.

**Files:**
- Modify: `app/Http/Controllers/Admin/SalaryCrudController.php` (`store()`, `update()`)

**Step 1 — Update `store()`:**
```php
public function store()
{
    $request = $this->crud->validateRequest();
    $salary = Salary::create($request->except('allowance'));
    $this->syncAllowancesFromRequest($salary->user_id, (array) $request->input('allowance', []));
    Alert::success('Berhasil input Gaji')->flash();
    return redirect(route('salary.index'));
}
```

**Step 2 — Update `update()`:**
```php
public function update()
{
    $request = $this->crud->validateRequest();
    $salary = Salary::find($this->crud->getCurrentEntryId());
    $salary->update($request->except('allowance'));
    $this->syncAllowancesFromRequest($salary->user_id, (array) $request->input('allowance', []));
    Alert::success('Berhasil input Gaji')->flash();
    return redirect(route('salary.index'));
}
```

**Step 3 — Test (Feature, HTTP) di `SalaryInlineAllowanceTest`:**
```php
public function test_salary_form_post_creates_allowances_and_total(): void
{
    $admin = $this->adminWithSalaryEdit(); // helper: user + permission salary.edit (pola RecruitmentTest)
    $emp = User::factory()->create();
    $jab = SalaryAllowanceType::create(['label' => 'Jabatan']);

    $this->actingAs($admin, backpack_guard_name())
        ->post(backpack_url('salary'), [
            'user_id' => $emp->id,
            'basic_salary' => 8_000_000,
            'overtime_amount' => 0,
            'overtime_type' => 'flat',
            'fine_type' => 'flat',
            'allowance' => [$jab->id => 2_000_000],
        ])->assertRedirect();

    $this->assertSame(10_000_000, (int) Salary::where('user_id', $emp->id)->value('amount'));
    $this->assertDatabaseHas('employee_salary_allowances', [
        'user_id' => $emp->id, 'salary_allowance_type_id' => $jab->id, 'amount' => 2_000_000,
    ]);
}
```
> Catatan: pakai pola auth direct-permission tanpa role (pitfall `CheckIfAdmin` redirect 302), lihat RecruitmentTest helper.

**Step 4 — Run test create+post (harus PASS).**

**Step 5 — Commit:** `git commit -m "feat(salary): persist inline allowances on store/update"`

---

### Task 4: Tampilkan rincian tunjangan di Show operation

**Objective:** Halaman detail Gaji (`/admin/salary/{id}/show`) nampilin rincian tunjangan (read-only), konsisten dengan slip.

**Files:**
- Modify: `app/Http/Controllers/Admin/SalaryCrudController.php` (`setupShowOperation`/`fieldModification` untuk show)

**Step 1 — Tambah kolom/summary tunjangan saat `op === 'show'`** (custom_html list dari `employee_salary_allowances` milik user), tampilkan label + nominal + Total.

**Step 2 — Verifikasi manual di Task 5.**

**Step 3 — Commit:** `git commit -m "feat(salary): show allowance breakdown on salary detail"`

---

### Task 5: Browser test (Playwright) — alur form nyata

**Objective:** Buktikan lewat form asli: buat jenis tunjangan → buka form gaji → isi pokok + tunjangan → simpan → total benar + baris tunjangan tersimpan.

**Files:**
- Create: `tests/browser/m20b-inline-allowance.mjs`
- Create (helper): `tests/browser/_m20b_helper.php` (pola `_m20_helper.php`: seed type, baca amount, cleanup)

**Skenario:**
1. Login admin `siti@demo.test`/`password`.
2. Seed 1 jenis tunjangan aktif (via helper).
3. Buka `/admin/salary/create` (atau edit gaji karyawan yang belum punya salary) → field tunjangan tampil.
4. Isi `basic_salary` + nominal tunjangan → submit.
5. Assert redirect ke list + `Alert` sukses.
6. Assert via helper: `salaries.amount == basic + tunjangan`, baris `employee_salary_allowances` ada.
7. Edit lagi → ubah nominal → total ter-update; kosongkan → baris terhapus.
8. Cleanup.

**Pitfall diingat:** list Backpack = AJAX DataTable (`waitForTimeout`); select2 untuk `user_id` set via native `select.value + change`.

Run: `node tests/browser/m20b-inline-allowance.mjs` → semua PASS.

**Commit:** `git commit -m "test(salary): browser test for inline allowance on salary form"`

---

### Task 6: Pensiunkan menu "Tunjangan Karyawan" terpisah

**Objective:** Hilangkan menu sidebar `employee-salary-allowance` supaya HR pakai form gaji. Route + CRUD disimpan (fallback) atau dihapus.

**Files:**
- Modify: `resources/views/vendor/backpack/ui/inc/menu_items.blade.php` (hapus baris menu "Tunjangan Karyawan")
- (Opsional) Modify: `routes/backpack/custom.php` — hapus `Route::crud('employee-salary-allowance', ...)` bila tak dipakai lagi.

**Keputusan:** Rekomendasi **hapus menu sidebar-nya saja**, biarkan route+CRUD tetap ada (aman untuk data lama / akses langsung). Menu "Jenis Tunjangan" (master) TETAP.

**Verifikasi:** browser — menu "Tunjangan Karyawan" hilang, "Jenis Tunjangan" & form gaji tetap jalan.

**Commit:** `git commit -m "chore(menu): retire standalone employee allowance menu (moved into salary form)"`

---

### Task 7: Regresi penuh + docs

**Objective:** Pastikan nol regresi + dokumentasi.

**Steps:**
1. `php -d memory_limit=2G -d xdebug.mode=off vendor/bin/phpunit --no-coverage` → **365+ tests HIJAU** (baseline saat ini 365).
2. Jalankan `tests/browser/m20-breakdown.mjs` (existing) + `m20b-inline-allowance.mjs` → hijau.
3. Update `docs/plan/M20-komponen-gaji-dinamis-DONE.md`: catat perubahan UX (tunjangan pindah inline ke form gaji), pitfall (Backpack free → satu field per type, bukan repeatable).
4. Notif Telegram ringkas (chat_id 389088588) via file + `--data-urlencode`.
5. Commit final.

---

## Files Likely to Change

- `app/Http/Controllers/Admin/SalaryCrudController.php` (field render, store/update, sync, show)
- `resources/views/vendor/backpack/ui/inc/menu_items.blade.php` (hapus menu terpisah)
- `routes/backpack/custom.php` (opsional, hapus route CRUD terpisah)
- `tests/Feature/SalaryInlineAllowanceTest.php` (baru)
- `tests/browser/m20b-inline-allowance.mjs` + `_m20b_helper.php` (baru)
- `docs/plan/M20-komponen-gaji-dinamis-DONE.md` (update)

## Tests / Validation

- Unit/Feature: `SalaryInlineAllowanceTest` (sync create/update/delete, POST form).
- Browser: `m20b-inline-allowance.mjs` (alur form asli).
- Regresi: full PHPUnit hijau + `m20-breakdown.mjs` hijau.

## Risks & Tradeoffs

- **Banyak jenis tunjangan → form panjang.** Mitigasi: field 2 kolom (`col-md-6`), urut `sort_order`. Kalau nanti > ~30 jenis, pertimbangkan grouping/collapse (YAGNI sekarang).
- **`allowance[<id>]` di request** harus di-`except('allowance')` sebelum mass-assign Salary agar tak error kolom tak dikenal. (Sudah ditangani Task 3.)
- **Mode create tanpa memilih user** tetap butuh `user_id` terisi sebelum tunjangan bermakna — alur normal Backpack (user_id field wajib) sudah menjamin.
- **Konsistensi dengan CRUD lama:** bila route CRUD terpisah dibiarkan, ada dua jalur edit. Rekomendasi: sembunyikan menu, dokumentasikan bahwa sumber utama = form gaji.

## Open Questions

1. **Menu terpisah**: hapus total (route+CRUD) atau cukup sembunyikan dari sidebar? (default plan: sembunyikan menu, simpan route.)
2. **Show detail**: perlu rincian tunjangan di halaman show Gaji, atau cukup di slip saja? (default: tampilkan ringkas di show.)
3. **Field kosong vs 0**: kosong dan 0 sama-sama = "tidak ada" (hapus baris) — sudah diasumsikan. Konfirmasi bila 0 harus tetap tersimpan sebagai baris.
