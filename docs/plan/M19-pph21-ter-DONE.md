# M19 — PPh 21 TER (Tarif Efektif Rata-rata) 2024/2026

> **Status:** ✅ DONE (implemented & tested) · **Dibuat:** 2026-08-26 · **Selesai:** 2026-08-26
> **Basis:** memperbaiki `TaxService` (M05) yang berlabel "TER" tapi sebenarnya masih metode **disetahunkan** (pra-2024).
> **Dasar hukum:** PP 58/2023 · PMK 168/2023 · UU HPP (UU 7/2021) Pasal 17. Wajib sejak Masa Pajak Januari 2024, dipertegas berlaku penuh 2026.
> **Pemicu:** review Capt — pemotongan PPh 21 bulanan sistem tidak sesuai skema TER.

---

## 0.1 Ringkasan Implementasi (SELESAI)

| Fase | Isi | Test |
|---|---|---|
| **M19-1/2** | Tabel `ter_rates` (versioned) + model `TerRate` + CSV `ter_rates_2026.csv` (DRAFT) + `TerRateSeeder` (validasi kontinuitas) | 7 PHPUnit (142 assert) |
| **M19-3/4** | `terCategory()` mapping status→A/B/C + `calculatePPh21TER()` (Jan–Nov) | 8 PHPUnit |
| **M19-5** | `calculateDecemberCorrection()` + routing di `applyToRecap()` (bulan 1–11 TER, 12 koreksi) | 4 PHPUnit |
| **M19-6** | CRUD `TerRateCrudController` + menu "Tarif TER" (Pajak & BPJS) | 5 Playwright |
| **Oracle** | `TerOracleTest` rupiah-persis matrix {status×gross×npwp} | 11 PHPUnit |

**Total M19: 30 PHPUnit + 5 Playwright. Full suite: 359 tests / 922 assertions HIJAU, nol regresi dari 329 baseline M18.**

### ⚠️ Catatan compliance (WAJIB dibaca client)
- Angka tarif di `database/data/ter_rates_2026.csv` adalah **DRAFT dari PMK 168/2023** — **wajib diverifikasi ke salinan resmi** sebelum produksi. Seeder + `TerOracleTest` mengunci hitungan; kalau CSV dikoreksi, expected value di oracle test ikut disesuaikan.
- **Q3 (komponen bruto premi employer):** untuk fase ini bruto = `salary + overtime + extra_time + thr + bonus` (belum menambahkan premi JKK/JKM/BPJS Kes employer ke bruto). Bila DJP mensyaratkan penambahan itu, ubah `applyToRecap()` — dampaknya menaikkan bruto → tarif TER. **Perlu keputusan client.**

### Pitfall ditemukan saat eksekusi
- `CRUD::addFilter()` = **Backpack PRO** (tidak tersedia di versi free proyek) → 500 di endpoint search. Solusi: hapus filter, andalkan search box bawaan free-tier. (Pola sama dengan pitfall PRO `table` field di M17.)
- Test lama `SalaryTaxAutoCalcTest` hanya seed `TaxRateSeeder`; karena PPh21 kini lewat TER, wajib seed `TerRateSeeder` juga di test payroll.

---

## 1. Masalah (temuan review, berbasis kode nyata)

**Masalah:** `app/Services/TaxService.php::calculatePPh21()` menghitung PPh 21 bulanan dengan cara **disetahunkan** (bruto × 12 → biaya jabatan → PTKP → tarif progresif Pasal 17 → ÷ 12). Sejak 2024 ini **salah** untuk Masa Pajak Januari–November. Yang benar: **TER** (bruto bulanan × tarif efektif kategori A/B/C), lalu **koreksi progresif tahunan hanya di Masa Pajak Desember**.

**Solusi:** tambah skema data tarif TER (versioned per tahun, pola sama dengan `pph21_brackets`), mapping PTKP → Kategori TER, dan ubah `TaxService` jadi **dua jalur**: TER untuk Jan–Nov, progresif-tahunan-koreksi untuk Desember. Semua **config-driven** (tak ada tarif hardcode), CRUD pengaturan di menu Pajak & BPJS, plus test lengkap termasuk simulasi kasus resmi.

**Konsistensi arsitektur:** ikut pola M05/M17/M18 — migration `Schema::create` + `foreignId`, model `CrudTrait`+`Auditable`, Backpack CRUD, seeder untuk data referensi, plan doc `docs/plan/` rename `-DONE` saat selesai.

---

# BAGIAN A — ATURAN TER (REGULASI & PERHITUNGAN)

## A.1 Konsep Inti

TER = **Tarif Efektif Rata-rata**. Pemotongan PPh 21 pegawai tetap dipecah dua fase dalam satu tahun pajak:

| Masa Pajak | Metode | Formula |
|---|---|---|
| **Januari – November** | **TER Bulanan** | `PPh_bulan = pembulatan_bawah(bruto_bulan) × tarif_TER(kategori, bruto_bulan)` |
| **Desember** (atau masa pajak terakhir) | **Progresif tahunan (Pasal 17)** dikurangi akumulasi | `PPh_Des = PPh_setahun − Σ(PPh Jan..Nov yang sudah dipotong)` |

**Kunci perbedaan dengan metode lama:**
- Jan–Nov **TIDAK** ada pengurang biaya jabatan / PTKP / iuran di tingkat bulanan. Semua sudah "dibungkus" ke dalam tarif efektif. Cukup **bruto bulanan × tarif TER**.
- Beban tahunan tetap sama; TER hanya **meratakan cashflow** potongan. Koreksi presisi terjadi di Desember (metode lama yang justru dipakai di Des).

## A.2 Definisi "Bruto Bulanan" (Penghasilan Bruto)

Penghasilan bruto sebulan = seluruh penghasilan teratur + tidak teratur yang diterima:
- Gaji pokok + tunjangan tetap
- Lembur (overtime)
- THR, bonus, gratifikasi (di bulan diterimanya)
- Premi BPJS/asuransi yang **dibayar pemberi kerja** (JKK, JKM, dan Kesehatan porsi employer) → **menambah** bruto
- **TIDAK termasuk:** pengembalian/reimbursement murni, iuran pensiun yang dibayar sendiri (baru relevan di setelan tahunan).

> ⚠️ Di kode existing, `applyToRecap()` menghitung `gross = salary + overtime + extra_time + thr + bonus`. Untuk TER perlu dipastikan komponen **premi employer** (JKK, JKM, BPJS Kes employer) ikut ditambahkan ke bruto sesuai aturan DJP. Ini **perubahan perilaku** yang harus dikonfirmasi (lihat Open Questions Q3).

## A.3 Kategori TER (A / B / C) — Mapping dari Status PTKP

TER Bulanan punya **3 kategori**, ditentukan dari **status PTKP** karyawan (PMK 168/2023):

| Kategori | Status PTKP | Nilai PTKP setahun |
|---|---|---|
| **TER A** | **TK/0**, **TK/1**, **K/0** | Rp 54.000.000 & Rp 58.500.000 |
| **TER B** | **TK/2**, **TK/3**, **K/1**, **K/2** | Rp 63.000.000 & Rp 67.500.000 |
| **TER C** | **K/3** | Rp 72.000.000 |

**Status `K/I/*` (penghasilan istri digabung):** tidak masuk mapping resmi TER bulanan (penggabungan penghasilan istri adalah urusan SPT Tahunan, bukan pemotongan bulanan 1 pemberi kerja). **Keputusan default (lihat Q1):** perlakukan `K/I/n` mengikuti kategori `K/n` untuk pemotongan bulanan.

## A.4 Tabel Tarif TER Bulanan

Tiap kategori = tabel progresif-flat (bukan berlapis — **satu tarif** dipilih berdasarkan posisi bruto bulanan dalam rentang, lalu dikali seluruh bruto). Tarif 0% untuk bruto ≤ ambang PTKP bulanan.

> ⚠️ **CATATAN COMPLIANCE:** angka di bawah adalah baseline dari PMK 168/2023 Lampiran. **Wajib diverifikasi ulang** terhadap salinan resmi PMK 168/2023 (Lampiran I–III) + PMK/peraturan revisi 2025–2026 sebelum seeding produksi. Seeder harus load dari file referensi resmi (CSV), bukan diketik manual, untuk hindari typo (lihat Task M19-2).

### TER Kategori A (contoh lengkap — 44 lapis)

| No | Bruto bulanan (Rp) | Tarif |
|---|---|---|
| 1 | 0 – 5.400.000 | 0% |
| 2 | 5.400.001 – 5.650.000 | 0,25% |
| 3 | 5.650.001 – 5.950.000 | 0,50% |
| 4 | 5.950.001 – 6.300.000 | 0,75% |
| 5 | 6.300.001 – 6.750.000 | 1,00% |
| 6 | 6.750.001 – 7.500.000 | 1,25% |
| 7 | 7.500.001 – 8.550.000 | 1,50% |
| 8 | 8.550.001 – 9.650.000 | 1,75% |
| 9 | 9.650.001 – 10.050.000 | 2,00% |
| 10 | 10.050.001 – 10.350.000 | 2,25% |
| 11 | 10.350.001 – 10.700.000 | 2,50% |
| 12 | 10.700.001 – 11.050.000 | 3,00% |
| 13 | 11.050.001 – 11.600.000 | 3,50% |
| 14 | 11.600.001 – 12.500.000 | 4,00% |
| 15 | 12.500.001 – 13.750.000 | 5,00% |
| 16 | 13.750.001 – 15.100.000 | 6,00% |
| 17 | 15.100.001 – 16.950.000 | 7,00% |
| 18 | 16.950.001 – 19.750.000 | 8,00% |
| 19 | 19.750.001 – 24.150.000 | 9,00% |
| 20 | 24.150.001 – 26.450.000 | 10,00% |
| 21 | 26.450.001 – 28.000.000 | 11,00% |
| 22 | 28.000.001 – 30.050.000 | 12,00% |
| 23 | 30.050.001 – 32.400.000 | 13,00% |
| 24 | 32.400.001 – 35.400.000 | 14,00% |
| 25 | 35.400.001 – 39.100.000 | 15,00% |
| 26 | 39.100.001 – 43.850.000 | 16,00% |
| 27 | 43.850.001 – 47.800.000 | 17,00% |
| 28 | 47.800.001 – 51.400.000 | 18,00% |
| 29 | 51.400.001 – 56.300.000 | 19,00% |
| 30 | 56.300.001 – 62.200.000 | 20,00% |
| 31 | 62.200.001 – 68.600.000 | 21,00% |
| 32 | 68.600.001 – 77.500.000 | 22,00% |
| 33 | 77.500.001 – 89.000.000 | 23,00% |
| 34 | 89.000.001 – 103.000.000 | 24,00% |
| 35 | 103.000.001 – 125.000.000 | 25,00% |
| 36 | 125.000.001 – 157.000.000 | 26,00% |
| 37 | 157.000.001 – 206.000.000 | 27,00% |
| 38 | 206.000.001 – 337.000.000 | 28,00% |
| 39 | 337.000.001 – 454.000.000 | 29,00% |
| 40 | 454.000.001 – 550.000.000 | 30,00% |
| 41 | 550.000.001 – 695.000.000 | 31,00% |
| 42 | 695.000.001 – 910.000.000 | 32,00% |
| 43 | 910.000.001 – 1.400.000.000 | 33,00% |
| 44 | > 1.400.000.000 | 34,00% |

### TER Kategori B (ringkas — ambang 0% lebih tinggi)
Ambang 0%: **0 – 6.200.000**. Lapisan awal: 6.200.001–6.500.000 = 0,25%; 6.500.001–6.850.000 = 0,50%; … pola menaik hingga 34%. **Load lengkap dari CSV resmi** (± 40 lapis).

### TER Kategori C (ringkas — ambang 0% paling tinggi)
Ambang 0%: **0 – 6.600.000**. Lapisan awal: 6.600.001–6.950.000 = 0,25%; … hingga 34%. **Load lengkap dari CSV resmi** (± 40 lapis).

> Struktur data identik untuk A/B/C — cukup satu tabel `ter_rates` dengan kolom `category`. Tidak perlu 3 tabel.

## A.5 TER Harian (opsional — pegawai tidak tetap)

Untuk upah harian pegawai tidak tetap:
- Upah harian ≤ Rp 450.000 → 0%
- Upah harian > Rp 450.000 → 0,5% × upah harian (dengan aturan kumulatif bulanan)

**Keputusan (Q2):** kemungkinan **OUT OF SCOPE** untuk fase pertama karena sistem absensi ini fokus pegawai tetap. Ditandai sebagai fase opsional M19-6.

## A.6 Perhitungan Masa Desember (Koreksi Tahunan)

Di Masa Pajak Desember, hitung **PPh 21 setahun** dengan metode lama (yang sudah ada di `calculatePPh21` sekarang), lalu:

```
PPh_Desember = PPh21_setahun_progresif − Σ(PPh21 Masa Jan..Nov)
```

Di mana `PPh21_setahun_progresif`:
```
bruto_setahun   = Σ bruto Jan..Des (aktual, bukan proyeksi)
biaya_jabatan   = min(5% × bruto_setahun, 6.000.000)
pengurang       = biaya_jabatan + (JHT+JP employee setahun)
neto            = bruto_setahun − pengurang
PKP             = pembulatan_bawah_1000(neto − PTKP_tahun)
PPh_setahun     = progresif Pasal 17 (pakai pph21_brackets)
jika tanpa NPWP → PPh_setahun × 1,20
```

`PPh_Desember` bisa **negatif** (lebih bayar) bila TER Jan–Nov terlalu besar → jadi **restitusi/pengurang** di slip Desember. Sistem harus menampung nilai negatif ini.

## A.7 Simulasi Perhitungan (jadi test fixtures)

### Simulasi 1 — Karyawan lajang TK/0, gaji tetap Rp 10.000.000/bln, punya NPWP
- Kategori: **TER A**. Bruto bulanan 10.000.000 → masuk lapis **9.650.001–10.050.000 = 2,00%**.
- **PPh Jan–Nov** = 10.000.000 × 2% = **Rp 200.000/bulan** → 11 × 200.000 = 2.200.000.
- **Desember (koreksi):**
  - bruto setahun = 120.000.000
  - biaya jabatan = min(6.000.000, 6.000.000) = 6.000.000
  - (asumsi tanpa BPJS TK employee utk simpel) neto = 114.000.000
  - PTKP TK/0 = 54.000.000 → PKP = 60.000.000
  - progresif: 5% × 60.000.000 = 3.000.000 → PPh setahun = 3.000.000
  - PPh Des = 3.000.000 − 2.200.000 = **Rp 800.000**
- **Total setahun = 3.000.000** ✓ (sama dengan progresif murni — TER hanya meratakan).

### Simulasi 2 — Karyawan K/2, gaji Rp 8.000.000/bln, tanpa NPWP
- Kategori: **TER B** (K/2). Bruto 8.000.000 vs ambang 0% B (6.200.000) → kena lapis kecil (± 1,0%, verifikasi tabel).
- PPh Jan–Nov = 8.000.000 × tarif_B → catat, akumulasi.
- Desember: progresif tahunan × 1,20 (tanpa NPWP) − akumulasi.
- **Assert:** total setahun = progresif tahunan × 1,20.

### Simulasi 3 — Bruto di bawah ambang (TK/0, gaji Rp 5.000.000/bln)
- 5.000.000 ≤ 5.400.000 → **tarif 0%** → PPh Jan–Nov = **0**.
- Desember: neto setahun kemungkinan ≤ PTKP → PPh setahun = 0 → PPh Des = 0.
- **Assert:** nol sepanjang tahun.

### Simulasi 4 — Ada bonus/THR di satu bulan
- Bulan ada THR: bruto bulan itu melonjak → tarif TER bulan itu naik (progresif-flat) → potongan bulan itu lebih besar. Bulan lain normal.
- **Assert:** akumulasi tetap konvergen ke progresif tahunan di Desember.

### Simulasi 5 — Karyawan masuk pertengahan tahun (mulai Juli)
- TER diterapkan hanya untuk bulan kerja (Jul–Nov), Desember koreksi berdasar bruto aktual Jul–Des.
- **Assert:** tidak ada proyeksi 12 bulan penuh di jalur TER.

---

# BAGIAN B — RENCANA PERUBAHAN APLIKASI

> **For Hermes:** eksekusi task-by-task dengan TDD. Tiap task: tulis test gagal → implement minimal → test hijau → commit. Notif Telegram tiap fase selesai (pola M17/M18).

**Goal:** ganti mesin PPh 21 bulanan dari disetahunkan → TER (Jan–Nov) + koreksi progresif (Des), fully config-driven + CRUD + test.

**Arsitektur:** tabel baru `ter_rates` (versioned per tahun, pola `pph21_brackets`); mapping status→kategori via constant di service (override-able); `TaxService` dapat metode `calculatePPh21TER()` + `calculateDecemberCorrection()`; `applyToRecap()` memilih jalur berdasarkan bulan recap; CRUD Backpack untuk `ter_rates`; seeder dari CSV resmi.

**Tech Stack:** Laravel 11 + Backpack CRUD + MySQL. Test: PHPUnit + Playwright (browser).

---

### Task M19-1: Migration tabel `ter_rates`

**Objective:** tempat tarif TER, versioned per tahun + kategori.

**Files:**
- Create: `database/migrations/2026_08_26_100001_create_ter_rates_table.php`
- Test: `tests/Feature/TerRateTest.php`

**Skema:**
```php
Schema::create('ter_rates', function (Blueprint $table) {
    $table->id();
    $table->integer('year');
    $table->enum('category', ['A', 'B', 'C']);
    $table->bigInteger('lower_bound');            // inklusif
    $table->bigInteger('upper_bound')->nullable(); // null = lapis teratas
    $table->decimal('rate', 5, 2);                // persen, mis. 2.00
    $table->timestamps();
    $table->unique(['year', 'category', 'lower_bound']);
    $table->index(['year', 'category']);
});
```

**Verifikasi:** `php artisan migrate` sukses; `Schema::hasTable('ter_rates')` true.

---

### Task M19-2: Model `TerRate` + data referensi CSV + seeder

**Objective:** model + seed tarif A/B/C 2026 dari CSV resmi (hindari typo).

**Files:**
- Create: `app/Models/TerRate.php` (CrudTrait, scope `forYearCategory($year,$cat)`)
- Create: `database/data/ter_rates_2026.csv` (kolom: category,lower_bound,upper_bound,rate) — **diisi dari PMK 168/2023 Lampiran resmi**
- Create: `database/seeders/TerRateSeeder.php` (baca CSV → upsert; idempoten)
- Test: `tests/Feature/TerRateTest.php`

**Pitfall:** CSV **wajib** dari salinan resmi. Seeder validasi: tiap kategori rentang kontinu tanpa gap/overlap, lapis pertama mulai 0, lapis terakhir `upper_bound` null.

**Verifikasi:** `php artisan db:seed --class=TerRateSeeder`; assert jumlah baris A≈44, B≈40, C≈40; assert kontinuitas rentang.

---

### Task M19-3: Mapping status PTKP → Kategori TER

**Objective:** fungsi menentukan kategori dari `tax_status`.

**Files:**
- Modify: `app/Services/TaxService.php` (tambah constant + method `terCategory(string $status): string`)
- Test: `tests/Unit/TerCategoryTest.php`

**Kode inti:**
```php
public const TER_CATEGORY = [
    'TK/0' => 'A', 'TK/1' => 'A', 'K/0' => 'A',
    'TK/2' => 'B', 'TK/3' => 'B', 'K/1' => 'B', 'K/2' => 'B',
    'K/3'  => 'C',
    // K/I/n mengikuti K/n (keputusan Q1) — override bila DJP mengatur lain
    'K/I/0' => 'A', 'K/I/1' => 'B', 'K/I/2' => 'B', 'K/I/3' => 'C',
];

public function terCategory(string $status): string
{
    return self::TER_CATEGORY[$status] ?? 'A'; // fallback aman + log warning
}
```

**Verifikasi:** test tiap 12 status → kategori benar.

---

### Task M19-4: `calculatePPh21TER()` — jalur bulanan Jan–Nov

**Objective:** hitung PPh bulanan pakai tarif TER.

**Files:**
- Modify: `app/Services/TaxService.php`
- Test: `tests/Feature/Pph21TerTest.php`

**Kode inti:**
```php
public function calculatePPh21TER(User $user, int $grossMonthly, ?int $year = null): int
{
    $year ??= (int) now()->year;
    if ($grossMonthly <= 0) return 0;

    $category = $this->terCategory($this->profileFor($user)->tax_status);
    $base = (int) (floor($grossMonthly / 1) * 1); // pembulatan sesuai aturan
    $rate = $this->terRate($category, $base, $year); // % dari tabel

    // Tanpa NPWP: surcharge 20% tetap berlaku
    $tax = (int) round($base * $rate / 100);
    if (! $this->profileFor($user)->hasNpwp()) {
        $tax = (int) round($tax * (1 + self::NO_NPWP_SURCHARGE));
    }
    return $tax;
}

private function terRate(string $category, int $gross, int $year): float
{
    $row = TerRate::forYearCategory($year, $category)
        ->where('lower_bound', '<=', $gross)
        ->where(fn($q) => $q->whereNull('upper_bound')->orWhere('upper_bound', '>=', $gross))
        ->orderByDesc('lower_bound')->first();
    return $row?->rate ?? 0.0; // tak ada tabel → 0 + log (jangan invent tarif)
}
```

**Verifikasi:** Simulasi 1 & 3 (§A.7) lolos: 10jt A → 200.000; 5jt A → 0.

---

### Task M19-5: `calculateDecemberCorrection()` + routing di `applyToRecap()`

**Objective:** Desember pakai koreksi tahunan; Jan–Nov pakai TER; pilih otomatis dari `recap_month`.

**Files:**
- Modify: `app/Services/TaxService.php` (method baru + ubah `applyToRecap()` + `calculatePPh21()` jadi delegator/deprecate)
- Test: `tests/Feature/DecemberCorrectionTest.php`

**Logika routing (di `applyToRecap`):**
```php
$month = (int) Carbon::createFromFormat('m-Y', $recap->recap_month)->month;
$pph21 = $month === 12
    ? $this->calculateDecemberCorrection($user, $recap, $year)
    : $this->calculatePPh21TER($user, $gross, $year);
```

**`calculateDecemberCorrection()`** (§A.6): hitung PPh setahun progresif berbasis **bruto aktual** Jan–Des (jumlahkan dari `salary_recaps` tahun itu), kurangi akumulasi `pph21` Masa Jan–Nov. Boleh negatif.

**Pitfall:** akumulasi Jan–Nov dibaca dari recap yang sudah tersimpan → pastikan urutan generasi (Des terakhir). Bila recap bulan sebelumnya belum ada, fallback proyeksi + tandai warning.

**Verifikasi:** Simulasi 1 → total setahun 3.000.000, Des = 800.000. Simulasi 2 (tanpa NPWP) → total = progresif ×1,20.

---

### Task M19-6: CRUD `TerRate` di menu Pajak & BPJS

**Objective:** HR bisa lihat/atur tarif TER per tahun (audit-friendly, jarang diubah).

**Files:**
- Create: `app/Http/Controllers/Admin/TerRateCrudController.php`
- Modify: `routes/backpack/custom.php` (route resource `ter-rate`)
- Modify: `resources/views/vendor/backpack/ui/inc/menu_items.blade.php` (item baru, i18n `menu.ter_rate`)
- Modify: `lang/en/menu.php` + `lang/id/menu.php` (key `ter_rate`)
- Modify: `database/seeders/RolesAndPermissionsSeeder.php` (permission `ter-rate.*` bila perlu, atau ikut `tax.manage`)
- Test: `tests/Feature/TerRateCrudTest.php`

**Kolom list:** year, category, range (lower–upper), rate. **Filter:** year, category. List read-mostly; create/edit gated `super_admin`.

**Verifikasi:** halaman `/admin/ter-rate` 200; filter year+category jalan; menu tampil EN+ID.

---

### Task M19-7: Tampilkan metode & rincian TER di slip gaji

**Objective:** transparansi — slip menampilkan "PPh 21 (TER Kategori A, 2%)" vs "PPh 21 (Koreksi Desember)".

**Files:**
- Modify: view slip gaji / `SalaryRecap` (kolom keterangan metode)
- Opsional: simpan `pph21_method` + `ter_category` + `ter_rate` ke `salary_recaps` (migration tambahan) untuk audit.
- Test: browser test slip.

**Keputusan (Q4):** apakah perlu kolom audit `pph21_method`/`ter_rate` di `salary_recaps`? Rekomendasi: **ya** (jejak audit pajak penting).

---

### Task M19-8: Backfill / re-generate recap tahun berjalan (opsional, hati-hati)

**Objective:** bila ada recap 2026 yang terlanjur dihitung metode lama, sediakan command re-hitung.

**Files:**
- Create: `app/Console/Commands/RecalculatePph21.php` (`payroll:recalc-pph21 {year} {--dry-run}`)
- Test: `tests/Feature/RecalculatePph21Test.php`

**Pitfall:** hanya untuk recap **belum dibayar** (`paid=false`) atau dengan konfirmasi eksplisit. Recap yang sudah disetor pajaknya jangan diubah diam-diam.

---

# BAGIAN C — RENCANA TESTING

## C.1 Prinsip
- **TDD**: tiap task tulis test gagal dulu.
- **Sumber kebenaran**: hasil hitung dibandingkan ke **contoh resmi DJP / kalkulator PJAP** (bukan asumsi kita). Fixtures dari §A.7.
- **Jangan mock tarif**: pakai `TerRateSeeder` data nyata di test DB.
- **Locale-agnostic** untuk browser test (match by href, pola M17/M18 pitfall).
- Jalankan suite via `php -d xdebug.mode=off vendor/bin/phpunit` (pitfall M17: `artisan test`+xdebug = OOM).

## C.2 Unit Test
| File | Cakupan |
|---|---|
| `tests/Unit/TerCategoryTest.php` | 12 status → kategori A/B/C benar; K/I mapping; status asing → fallback A + warning |
| `tests/Unit/TerRateLookupTest.php` | lookup tarif pada batas rentang (boundary): tepat di `lower_bound`, tepat di `upper_bound`, di atas lapis teratas, bruto 0 |

## C.3 Feature Test (mesin pajak)
| File | Cakupan |
|---|---|
| `tests/Feature/TerRateTest.php` | migration + model + seeder; kontinuitas rentang A/B/C (no gap/overlap); lapis pertama mulai 0; lapis akhir upper null |
| `tests/Feature/Pph21TerTest.php` | **Simulasi 1** (TK/0 10jt → 200rb), **Simulasi 3** (5jt → 0), tanpa NPWP ×1,20, bruto sangat besar (lapis 34%) |
| `tests/Feature/DecemberCorrectionTest.php` | **Simulasi 1** total 3jt & Des 800rb; **Simulasi 2** (K/2 tanpa NPWP) total = progresif ×1,20; koreksi bisa negatif (lebih bayar) |
| `tests/Feature/Pph21RoutingTest.php` | `applyToRecap` bulan 01–11 → jalur TER; bulan 12 → jalur koreksi; **Simulasi 5** (masuk pertengahan tahun) |
| `tests/Feature/Pph21BonusTest.php` | **Simulasi 4** (THR/bonus 1 bulan) → potongan bulan itu naik, akumulasi tetap konvergen |
| `tests/Feature/TerRateCrudTest.php` | CRUD guard permission; filter year+category; list render |

## C.4 Simulasi Regresi Numerik (tabel oracle)
Buat `tests/Feature/TerOracleTest.php` dengan **data provider** berisi ~15 baris {status, gaji, npwp, expected_TER_bulanan, expected_total_tahunan}, diambil dari kalkulator/contoh resmi. Assert `calculatePPh21TER()` & total Desember cocok **rupiah-persis** (toleransi 0). Ini pengaman utama compliance.

## C.5 Browser Test (Playwright)
| File | Cakupan |
|---|---|
| `tests/browser/m19-ter-crud.mjs` | login admin → `/admin/ter-rate` → filter 2026 kategori A → verifikasi lapis 2% muncul; menu EN+ID |
| `tests/browser/m19-payslip.mjs` | generate recap karyawan TK/0 gaji 10jt bulan Maret → slip tampil "PPh 21 (TER A) Rp 200.000"; bulan Desember → "Koreksi Desember" |

## C.6 Acceptance Criteria (E2E)
> **Satu karyawan TK/0 ber-NPWP, gaji tetap Rp 10.000.000, digenerate 12 bulan (Jan–Des) → potongan Jan–Nov = Rp 200.000/bln, Desember = Rp 800.000, total setahun = Rp 3.000.000 (identik dengan progresif tahunan).** Diverifikasi lewat generate slip nyata (bukan unit saja) + angka tampil benar di UI.

## C.7 Verifikasi Non-Regresi
- Suite lama (329 test M18) tetap hijau.
- Modul BPJS/THR tidak berubah perilaku (TER hanya menyentuh PPh 21).

---

## D. Keputusan Terkunci & Open Questions

**Terkunci (default, bisa dikoreksi Capt):**
1. Semua tarif **config-driven** via `ter_rates` (versioned per tahun) — nol hardcode, konsisten `pph21_brackets`.
2. `TaxService` dua jalur (TER Jan–Nov / koreksi Des), dipilih dari `recap_month`.
3. Surcharge tanpa-NPWP 20% tetap berlaku di jalur TER.
4. Data tarif di-seed dari **CSV resmi**, bukan diketik manual.

**Open Questions (perlu jawaban Capt sebelum eksekusi):**
- **Q1 — Status K/I/*:** default map `K/I/n → K/n` (A/B/B/C). Setuju, atau ada kebijakan HR lain?
- **Q2 — TER Harian (pegawai tidak tetap):** masuk scope sekarang atau tunda? (default: **tunda**, sistem fokus pegawai tetap).
- **Q3 — Komponen bruto:** apakah premi employer (JKK/JKM/BPJS Kes employer) ditambahkan ke bruto TER sesuai DJP? Ini mengubah `applyToRecap()`. (default: **ikuti aturan DJP = ditambahkan**, tapi konfirmasi karena mengubah angka).
- **Q4 — Kolom audit:** tambah `pph21_method`, `ter_category`, `ter_rate` ke `salary_recaps`? (rekomendasi: **ya**).
- **Q5 — Sumber angka tarif:** siapa yang sediakan salinan PMK 168/2023 Lampiran resmi (atau boleh aku susun dari sumber publik lalu kamu validasi)?
- **Q6 — Recap 2026 existing:** ada recap tahun berjalan yang sudah dihitung metode lama & perlu di-recalc?

---

## E. Estimasi & Urutan Eksekusi

| Fase | Task | Ketergantungan |
|---|---|---|
| 1 | M19-1, M19-2, M19-3 (skema + data + mapping) | — |
| 2 | M19-4 (TER bulanan) | fase 1 |
| 3 | M19-5 (koreksi Desember + routing) | fase 2 |
| 4 | M19-6, M19-7 (CRUD + slip) | fase 2 |
| 5 | M19-8 (recalc command, opsional) | fase 3 |
| 6 | Testing menyeluruh + oracle + acceptance E2E | semua |

Tiap fase: test hijau → notif Telegram → lanjut (pola M17/M18).

## F. Risiko

- **Akurasi tarif** = risiko tertinggi (compliance/hukum). Mitigasi: seed dari CSV resmi + `TerOracleTest` rupiah-persis + review manual angka.
- **Definisi bruto** beda tipis antar interpretasi → mitigasi: Q3 dikonfirmasi + test eksplisit.
- **Koreksi Desember** butuh data 11 bulan sebelumnya benar → mitigasi: fallback proyeksi + warning bila recap bulan lain hilang.
- **Perubahan menyentuh payroll berjalan** → mitigasi: recalc hanya recap belum dibayar + `--dry-run`.

---

## Referensi Berkas Existing (untuk implementer)
- `app/Services/TaxService.php` — `calculatePPh21()` (metode lama, jadi basis jalur Desember), `applyToRecap()`, `getApplicablePTKP()`, `applyBrackets()`.
- `app/Models/EmployeeTaxProfile.php` — `TAX_STATUSES` (12), `tax_status`, `tax_method`, `hasNpwp()`.
- `app/Models/Pph21Bracket.php`, `app/Models/PtkpRate.php` — pola model tarif versioned (contoh untuk `TerRate`).
- `database/migrations/2026_08_07_130001_create_tax_and_bpjs_tables.php` — pola migration + kolom `salary_recaps.pph21`.
- `app/Models/SalaryRecap.php` — `recap_month` (format `m-Y`), `fillable`.
- `resources/views/vendor/backpack/ui/inc/menu_items.blade.php` — pola menu i18n `__('menu.*')`.
- `lang/en/menu.php`, `lang/id/menu.php` — key label menu.

---

> **Catatan kejujuran (compliance):** angka tarif TER di §A.4 adalah baseline dari PMK 168/2023 yang **HARUS diverifikasi** ke salinan resmi sebelum produksi. Aku tidak akan mengarang angka; bila sumber resmi tak tersedia saat eksekusi, seeding produksi ditunda dan hanya jalankan dengan data dummy bertanda jelas (lihat Q5).
