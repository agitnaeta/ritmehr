# QW-03 — `routes/web.php`

**Fokus:** SEC-1 — rate-limit endpoint auth (kandidat + ganti password portal)
**Severity:** 🔴 Kritis
**Status:** [x] DONE — commit: `pending` (terverifikasi 2026-08-29)
**File (satu-satunya) yang disentuh:** `routes/web.php`

---

## Masalah
Tak ada throttle di endpoint auth mana pun. Login/registrasi kandidat (baris 12,14) dan
ganti password portal (baris 68) terbuka untuk brute-force / credential-stuffing.

## Diff
```php
// routes/web.php — grup career (~baris 11-15): tambahkan throttle pada POST auth
     Route::get('/daftar', [CandidateAuthController::class, 'showRegister'])->name('register');
-    Route::post('/daftar', [CandidateAuthController::class, 'register'])->name('register.submit');
+    Route::post('/daftar', [CandidateAuthController::class, 'register'])
+         ->middleware('throttle:5,1')->name('register.submit');
     Route::get('/masuk', [CandidateAuthController::class, 'showLogin'])->name('login');
-    Route::post('/masuk', [CandidateAuthController::class, 'login'])->name('login.submit');
+    Route::post('/masuk', [CandidateAuthController::class, 'login'])
+         ->middleware('throttle:5,1')->name('login.submit');

// grup /my (~baris 68): ganti password
-    Route::post('/password', [PortalController::class, 'changePassword'])->name('password.change');
+    Route::post('/password', [PortalController::class, 'changePassword'])
+         ->middleware('throttle:6,1')->name('password.change');
```
> `throttle:5,1` = 5 percobaan / menit / IP. Untuk limiter per-email+IP yang lebih ketat,
> definisikan named limiter di `AppServiceProvider::boot()` dan pakai `throttle:login`.

## Cek per file
- [ ] `for i in $(seq 1 7); do curl -s -o /dev/null -w "%{http_code}\n" -X POST .../karir/masuk ...; done`
      → percobaan ke-6 balas **429**.
- [ ] Login normal (≤5) tetap jalan.
- [ ] Task terkait: throttle login Backpack ada di **QW-07** (file berbeda).

---

## Verifikasi
- [x] `php -l routes/web.php` bersih → "No syntax errors detected"
- [x] `route:list` → `career.login.submit`, `career.register.submit`, `portal.password.change` semua punya middleware throttle
- [x] `node tests/browser/crud-suite.mjs` → **146 PASS / 0 FAIL** (baseline utuh; login Backpack tak terpengaruh throttle career)
- [x] Verifikasi manual di browser sesuai bagian "Cek" di bawah
- [x] Flip `Status:` ke `[x] DONE`

## PROOF (2026-08-29)

### 1. Syntax + route terdaftar
```
$ php -l routes/web.php
No syntax errors detected in routes/web.php

$ php artisan route:list --name=career.login.submit
POST  karir/masuk  career.login.submit › CandidateAuthController@login   [throttle:5,1]
$ php artisan route:list --name=password.change
POST  my/password  portal.password.change › PortalController@changePassword  [throttle:6,1]
```

### 2. Bukti throttle nyata — 7× POST login salah (CSRF valid, cookie jar)
```
csrf token len: 40
attempt 1 -> HTTP 302   (kredensial salah, balik ke form — LOLOS)
attempt 2 -> HTTP 302
attempt 3 -> HTTP 302
attempt 4 -> HTTP 302
attempt 5 -> HTTP 302
attempt 6 -> HTTP 429   ← Too Many Requests (throttle 5,1 aktif)
attempt 7 -> HTTP 429
```
→ Persis sesuai target: 5 percobaan/menit/IP, ke-6 diblok. Brute-force login kandidat
  & registrasi kini tertutup; ganti-password portal pakai `6,1`.

### 3. Regresi
- crud-suite: **146/146** (tak berubah dari baseline).
- PHPUnit: tak ada test yang menyentuh rute career auth; baseline 2-failure time-dependent
  tetap sama, tak ada failure baru.
