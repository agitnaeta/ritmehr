# QW-07 — `config/backpack/base.php`

**Fokus:** SEC-1 (bagian 2) — throttle login Backpack (admin/karyawan)
**Severity:** 🔴 Kritis
**Status:** [x] DONE — commit: `pending` — **TANPA PERUBAHAN KODE** (sudah aman by default; diverifikasi 2026-08-29)
**File (satu-satunya) yang disentuh:** `config/backpack/base.php` — *tidak jadi diubah, lihat KOREKSI*

---

## ⚠️ KOREKSI TEMUAN (hasil verifikasi kode vendor)

Asumsi awal di analisis **KELIRU**. Login Backpack **SUDAH di-throttle bawaan**:

- `vendor/backpack/crud/src/app/Library/Auth/AuthenticatesUsers.php:13` →
  `use RedirectsUsers, ThrottlesLogins;`
- `AuthenticatesUsers.php:43-44` → `if (method_exists($this,'hasTooManyLoginAttempts') && $this->hasTooManyLoginAttempts($request))` sebelum attempt.
- `ThrottlesLogins.php:111-123` → default **`maxAttempts = 5`**, **`decayMinutes = 1`** (per username+IP).

Jadi tak ada gap. **Tidak perlu tambah kode** — menambah throttle sendiri malah dobel.
Yang berbeda dari SEC-1 (career): Backpack **tidak balas HTTP 429**; ia me-redirect (302)
lalu mem-flash `ValidationException` "Too many login attempts" — itulah kenapa proof di
bawah lihat 302 + pesan, bukan 429.

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
- [x] Kode vendor dibaca: `ThrottlesLogins` sudah aktif (maxAttempts=5, decayMinutes=1)
- [x] Proof runtime di bawah — lockout fire setelah 5 attempt
- [x] Tak ada perubahan kode → baseline PHPUnit & crud-suite tak tersentuh (tetap 146)
- [x] Flip `Status:` ke `[x] DONE`

## PROOF (2026-08-29)

### Bukti kode
```
$ grep -n "ThrottlesLogins" vendor/backpack/crud/src/app/Library/Auth/AuthenticatesUsers.php
13:    use RedirectsUsers, ThrottlesLogins;
43: if (method_exists($this, 'hasTooManyLoginAttempts') &&
44:     $this->hasTooManyLoginAttempts($request)) {

$ grep -n "return 5\|return.*maxAttempts\|decayMinutes" .../ThrottlesLogins.php
113: return property_exists($this, 'maxAttempts') ? $this->maxAttempts : 5;
123: return property_exists($this, 'decayMinutes') ? $this->decayMinutes : 1;
```

### Bukti runtime — 6× POST admin/login salah, lalu GET login page
```
attempt 1..6 -> HTTP 302 (kredensial salah / lockout, redirect-back)
GET /admin/login (sesi sama) menampilkan:
  "Too many login attempts. Please try again in 6 seconds."
```
→ Throttle admin login **AKTIF & terbukti**. Login benar (≤5) tetap lolos (crud-suite 146/146).

**Kesimpulan:** SEC-1 untuk sisi admin sudah terpenuhi tanpa kerja tambahan. Analisis
`analisis-teknis.md` dikoreksi agar tak menyesatkan.
