# M20 — Breakdown Gaji: Gaji Pokok + Tunjangan (label bebas)

> **Status:** ✅ DONE (implemented & tested) · **Dibuat:** 2026-08-26 · **Selesai:** 2026-08-26
> **Pemicu:** Capt — "instead masukan gaji aja, jadi ada gaji_pokok + tunjangan; tunjangan tipenya banyak, labelnya bisa beda-beda. BPJS & pajak biarkan as-is."

## 0. Ringkasan Implementasi (SELESAI)

| Fase | Isi | Test |
|---|---|---|
| M20-1 | Migration: `+basic_salary` (salaries+recaps), `salary_allowance_types`, `employee_salary_allowances`, `salary_recap_allowances` + backfill | — |
| M20-2 | Model + observer: `amount` auto = basic + Σ tunjangan aktif (backward-compat legacy amount) | 5 PHPUnit |
| M20-3/4 | CRUD Jenis Tunjangan (master global) + Tunjangan Karyawan + gaji pokok di form Gaji + menu | 6 Playwright |
| M20-5 | Snapshot rincian ke recap saat `calculateSalaryRecap` + slip nampilin breakdown | (browser) |
| M20-6 | `SalaryBreakdownTaxUnchangedTest` — bukti pajak/BPJS as-is | 1 PHPUnit |

**Total M20: 6 PHPUnit + 6 Playwright. Full suite: 365 tests / 934 assertions HIJAU, nol regresi.**

Kunci "as-is": `salaries.amount` tetap = total (basic + Σ tunjangan), di-maintain observer + boot hook. Pajak/BPJS baca `amount` → **identik**. Test membuktikan karyawan basic 8jt + tunjangan 2jt = pajak/BPJS SAMA PERSIS dengan gaji flat 10jt.

Pitfall: legacy `Salary::create(['amount'=>...])` tanpa `basic_salary` → boot hook seed basic dari amount (backward-compat). Test browser: bersihkan orphan allowance antar-run.

### Update M20b (2026-08-26) — Tunjangan inline di form Gaji
- Pengisian tunjangan **dipindah dari menu terpisah ke form Gaji** (`/admin/salary/create` & `/edit`): satu input `number` per jenis tunjangan aktif (`allowance[<type_id>]`).
- `SalaryCrudController::syncAllowancesFromRequest()` upsert/hapus baris saat store/update (kosong/0 = hapus) → observer recalc total.
- Halaman **Show Gaji** nampilin rincian via custom view `resources/views/admin/salary/allowance_breakdown.blade.php`.
- Menu sidebar **"Tunjangan Karyawan" disembunyikan** (route + CRUD tetap ada sebagai fallback). Menu "Jenis Tunjangan" (master) tetap.
- Pitfall: Backpack free tanpa `repeatable` → satu field per type (bukan multi-row dinamis). `user_id` validasi `string` → cast di test. Permission guard = `web` (bukan `backpack`).
- Test: `SalaryInlineAllowanceTest` (3 PHPUnit) + `m20b-inline-allowance.mjs` (8 skenario). Full suite **368 tests HIJAU**.



---

## 1. Tujuan (scope FINAL — dipersempit)

Ganti input gaji dari **satu angka gelondongan** (`salaries.amount`) jadi **rincian**:
- **Gaji Pokok** (`basic_salary`)
- **Tunjangan** — banyak baris, **label bebas** (Tunjangan Jabatan, Transport, Makan, dll), nominal tetap bulanan.

**Total = Gaji Pokok + Σ Tunjangan.** Total ini **sama persis** dengan `amount` sekarang, dan **dipakai pajak & BPJS tanpa perubahan apa pun** — cuma sekarang keliatan rinciannya di form & slip.

### Yang TIDAK termasuk (sengaja, sesuai arahan Capt)
- ❌ Tidak ada flag taxable/non-taxable per komponen (BPJS & pajak **as-is**).
- ❌ Tidak ada tipe hitung variabel (per-hari/persentase) — **nominal tetap saja**.
- ❌ Tidak menyentuh `TaxService`/`M19 TER`/BPJS sama sekali.
- ❌ Tidak mengubah lembur, potongan telat/absen/kasbon (tetap seperti sekarang).

> Ini versi ringan dari usulan awal (yang punya flag pajak per komponen). Karena BPJS & pajak dibiarkan as-is, kompleksitas itu dibuang. **YAGNI.**

---

## 2. Kondisi Sekarang

`salaries.amount` = 1 angka (dipakai `SalaryService` & `TaxService` via `$salary->amount` dan `salary_amount` di recap). Mau tampilkan "gaji pokok 8jt + tunjangan jabatan 2jt" → tidak bisa, cuma keliatan "10jt".

---

## 3. Desain (additive, nol perubahan destruktif)

**Prinsip kunci:** `salaries.amount` **tetap ada** dan **tetap jadi angka yang dibaca semua kode existing** (SalaryService, TaxService, BPJS, slip lama). Kita hanya:
1. Tambah `basic_salary` (gaji pokok) di `salaries`.
2. Tambah tabel `salary_allowances` (tunjangan per karyawan, label bebas).
3. **`amount` di-maintain otomatis = `basic_salary` + Σ tunjangan aktif** (via observer/service saat gaji atau tunjangan disimpan).

Hasilnya: seluruh mesin gaji/pajak/BPJS **tak perlu diubah** — mereka tetap baca `amount`, yang nilainya identik. Test 359 existing tetap hijau.

### 3.1 Migration `salaries` — tambah gaji pokok
```php
Schema::table('salaries', function (Blueprint $t) {
    $t->bigInteger('basic_salary')->default(0)->after('user_id');
});
// Backfill: basic_salary = amount (anggap seluruh gaji lama = gaji pokok, tunjangan kosong)
DB::table('salaries')->update(['basic_salary' => DB::raw('amount')]);
```

### 3.2 Tabel `salary_allowances` — tunjangan (label bebas)
```php
Schema::create('salary_allowances', function (Blueprint $t) {
    $t->id();
    $t->foreignId('user_id')->constrained()->cascadeOnDelete();
    $t->string('label');            // bebas: "Tunjangan Jabatan", "Transport", dll
    $t->bigInteger('amount');
    $t->integer('sort_order')->default(0);
    $t->boolean('is_active')->default(true);
    $t->timestamps();
});
```

### 3.3 Snapshot di slip (opsional tapi disarankan)
Agar slip bulan lalu tak berubah kalau tunjangan diedit, snapshot rincian ke recap saat dihitung:
```php
Schema::create('salary_recap_allowances', function (Blueprint $t) {
    $t->id();
    $t->foreignId('salary_recap_id')->constrained()->cascadeOnDelete();
    $t->string('label');
    $t->bigInteger('amount');
    $t->timestamps();
});
```
Recap juga simpan `basic_salary` snapshot (tambah kolom di `salary_recaps`). `salary_amount` yang lama tetap = total (tak berubah).

---

## 4. Cara `amount` di-maintain (kunci "as-is")

`Salary` model / observer:
```php
public function recalcTotal(): void {
    $this->amount = $this->basic_salary
        + $this->allowances()->where('is_active', true)->sum('amount');
    $this->saveQuietly();
}
```
Dipanggil saat `basic_salary` diubah atau tunjangan ditambah/edit/hapus. Karena `amount` = total, **BPJS (`cappedBase(amount)`) & pajak (bruto pakai `salary_amount`) tetap identik.**

---

## 5. Perubahan UI

- **Form Gaji** (`SalaryCrudController`): field `basic_salary` (Gaji Pokok) menggantikan input tunggal; `amount` jadi read-only/auto (atau disembunyikan, ditampilkan sebagai "Total").
- **Tunjangan**: karena Backpack free tak punya repeatable inline (pitfall M17/M19), pakai **CRUD terpisah `salary-allowance`** (pilih karyawan + label + nominal), atau relasi sederhana. Praktis & aman.
- **Slip gaji**: blok "Pendapatan" dirinci → Gaji Pokok + tiap Tunjangan (label + nominal) → Subtotal, lalu lanjut ke lembur/potongan/pajak seperti biasa.

---

## 6. Backward Compatibility

- Migrasi backfill: `basic_salary = amount`, tunjangan kosong → **total tetap sama** → semua angka gaji/pajak/BPJS **identik** hari pertama.
- Nol kolom dihapus. `amount` tetap sumber angka untuk kode lama.
- 359 test existing tetap hijau tanpa diubah.

---

## 7. Rencana Testing (kalau di-ACC)

| Test | Cakupan |
|---|---|
| `SalaryBreakdownTest` | `amount` = basic + Σ tunjangan; tambah/edit/hapus tunjangan → total ter-update |
| `AllowanceInactiveTest` | tunjangan non-aktif tak masuk total |
| `TaxUnchangedTest` | karyawan (basic 8jt + tunjangan 2jt) → PPh21 & BPJS **sama persis** dengan karyawan gaji 10jt gelondongan (bukti "as-is") |
| `RecapSnapshotTest` | edit tunjangan setelah recap → slip lama tak berubah |
| `Regresi` | 359 test tetap hijau |
| Browser `m20-breakdown.mjs` | form gaji rinci + slip render breakdown |
| Acceptance | HR input gaji pokok 8jt + Tunjangan Jabatan 1,5jt + Transport 500rb → total 10jt, slip nampilin 3 baris, PPh/BPJS = seperti gaji 10jt |

---

## 8. Keputusan Terkunci (Capt, 2026-08-26)

- **P1 — Tunjangan dikelola GLOBAL (master list).** Ada `salary_allowance_types` (label bebas, didefinisikan sekali, reusable). Per-karyawan isi nominal via pivot; **kalau tak diisi → 0 / dilewati**.
- **P2 — Snapshot: YA.** Rincian dibekukan ke slip saat recap dihitung.
- **P3 — Label bebas ketik** (di master type). **Total = Gaji Pokok + Σ Tunjangan aktif.** Total = `amount` sekarang → pajak/BPJS as-is.

### Skema final (revisi dari §3-4)
```php
// master global — dikelola sekali
Schema::create('salary_allowance_types', function (Blueprint $t) {
    $t->id();
    $t->string('label');                 // bebas: "Tunjangan Jabatan", "Transport"
    $t->integer('sort_order')->default(0);
    $t->boolean('is_active')->default(true);
    $t->timestamps();
});
// nilai per karyawan — tak diisi = tak ada baris = 0
Schema::create('employee_salary_allowances', function (Blueprint $t) {
    $t->id();
    $t->foreignId('user_id')->constrained()->cascadeOnDelete();
    $t->foreignId('salary_allowance_type_id')->constrained()->cascadeOnDelete();
    $t->bigInteger('amount');
    $t->timestamps();
    $t->unique(['user_id', 'salary_allowance_type_id']);
});
// snapshot ke slip
Schema::create('salary_recap_allowances', function (Blueprint $t) {
    $t->id();
    $t->foreignId('salary_recap_id')->constrained()->cascadeOnDelete();
    $t->string('label');                 // snapshot label saat itu
    $t->bigInteger('amount');
    $t->timestamps();
});
// salaries: +basic_salary ; salary_recaps: +basic_salary (snapshot)
```
`amount` = `basic_salary` + Σ `employee_salary_allowances.amount` (tipe aktif), di-maintain otomatis. Pajak/BPJS baca `amount` → **identik**.

## 9. Pertanyaan Terbuka
(tak ada — semua terkunci; eksekusi additive, TDD, notif tiap fase)

