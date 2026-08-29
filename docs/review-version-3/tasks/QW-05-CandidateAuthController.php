# QW-05 — `app/Http/Controllers/Career/CandidateAuthController.php`

**Fokus:** SEC-3 — password kuat saat registrasi kandidat
**Severity:** 🟡 Sedang
**Status:** [x] DONE — commit: `pending` (terverifikasi 2026-08-29)
**File utama:** `app/Http/Controllers/Career/CandidateAuthController.php`
**File pendamping:** `tests/Feature/CareerPortalTest.php` (fixture `password123` tak lolos rule → `Zx9Qm2Lp7Kv`)

---

## Masalah
`register()` (baris 31) hanya `min:8|confirmed`. Naikkan ke rule kompleksitas + cek
kebocoran (HIBP) bawaan Laravel.

## Diff
```php
use Illuminate\Validation\Rules\Password;   // di atas file

         $data = $request->validate([
             'name'     => 'required|string|max:120',
             'email'    => 'required|email|max:150|unique:candidates,email',
             'phone'    => 'nullable|string|max:30',
-            'password' => 'required|string|min:8|confirmed',
+            'password' => ['required','confirmed', Password::min(8)->mixedCase()->numbers()->uncompromised()],
         ], [ ... ]);
```

## Cek per file
- [ ] Daftar dgn password lemah → ditolak.
- [ ] Daftar dgn password kuat → akun terbuat + auto-login (perilaku lama tak berubah).

---

## Verifikasi
- [x] `php -l ...CandidateAuthController.php` → bersih
- [x] Proof rule: `password123` DITOLAK, `Zx9Qm2Lp7Kv` LOLOS
- [x] `CareerPortalTest` → **11/11 OK** (throttle QW-03 tak ganggu; cache array reset per test)
- [x] Full PHPUnit → 424 lulus / 2 baseline time-dependent
- [x] `crud-suite.mjs` → **146 PASS**
- [x] Flip `Status:` ke `[x] DONE`

## PROOF (2026-08-29)
```
$ Validator dgn rule QW-05:
password123      -> DITOLAK: must contain at least one uppercase and one lowercase letter
Zx9Qm2Lp7Kv      -> LOLOS

$ phpunit tests/Feature/CareerPortalTest.php
OK (11 tests, 44 assertions)
```
Rule diselaraskan dgn QW-04 (portal) untuk konsistensi kebijakan. Fixture register test
diperbarui dari `password123` (fixture lemah) ke `Zx9Qm2Lp7Kv`.

### Regresi
- crud-suite: **146/146**. PHPUnit: 424 lulus / 2 baseline time-dependent.
