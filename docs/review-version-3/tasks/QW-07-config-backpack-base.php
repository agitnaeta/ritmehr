# QW-07 — `config/backpack/base.php`

**Fokus:** SEC-1 (bagian 2) — throttle login Backpack (admin/karyawan)
**Severity:** 🔴 Kritis
**Status:** [ ] TODO — commit: `______`
**File (satu-satunya) yang disentuh:** `config/backpack/base.php`

---

## Masalah
`config/backpack/base.php` hanya punya throttle utk *password-recovery* (baris 98) &
*email-verification* (baris 72). **Login POST Backpack tidak di-throttle.** Ini pintu
masuk admin/karyawan → prioritas kritis.

## Pendekatan (pilih satu, terverifikasi utk Backpack 6.8)
Backpack mendaftarkan rute login-nya sendiri, jadi throttle ditempel via **middleware
grup Backpack**, bukan cukup satu key config. Opsi paling rapi:

1. Tambah named limiter di `App\Providers\AppServiceProvider::boot()`:
   ```php
   use Illuminate\Support\Facades\RateLimiter;
   use Illuminate\Cache\RateLimiting\Limit;

   RateLimiter::for('backpack_login', fn($request) =>
       Limit::perMinute(5)->by($request->input('email').'|'.$request->ip()));
   ```
2. Terapkan pada rute login Backpack. Cara termudah tanpa fork: override rute login di
   `routes/backpack/custom.php` dengan `->middleware('throttle:backpack_login')`, atau
   set `config('backpack.base.middleware_key')` group agar menyertakan throttle khusus
   pada jalur `/admin/login`.

> Catatan: karena ini menyentuh **AppServiceProvider.php** juga bila pakai named limiter,
> pecah jadi task terpisah bila Capt mau "1 file 1 task" ketat — file utama tetap
> `config/backpack/base.php`/route; limiter provider = task pendamping.

## Cek per file
- [ ] 6× POST `/admin/login` password salah dari IP sama → percobaan ke-6 = **429**.
- [ ] Login admin benar (≤5) tetap lolos.
- [ ] `crud-suite.mjs` login helper masih hijau (≤5 attempt, tak kena limit).

---

## Verifikasi
- [ ] `php -l config/backpack/base.php` bersih (kalau file PHP)
- [ ] `php -d memory_limit=2G -d xdebug.mode=off vendor/bin/phpunit --no-coverage` → tetap hijau (baseline)
- [ ] `node tests/browser/crud-suite.mjs` → tetap hijau (baseline 146)
- [ ] Verifikasi manual di browser sesuai bagian "Cek" di atas
- [ ] Flip `Status:` ke `[x] DONE` + isi commit SHA setelah semua centang
