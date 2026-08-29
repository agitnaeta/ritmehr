# QW-05 — `app/Http/Controllers/Career/CandidateAuthController.php`

**Fokus:** SEC-3 — password kuat saat registrasi kandidat
**Severity:** 🟡 Sedang
**Status:** [ ] TODO — commit: `______`
**File (satu-satunya) yang disentuh:** `app/Http/Controllers/Career/CandidateAuthController.php`

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
- [ ] `php -l app/Http/Controllers/Career/CandidateAuthController.php` bersih (kalau file PHP)
- [ ] `php -d memory_limit=2G -d xdebug.mode=off vendor/bin/phpunit --no-coverage` → tetap hijau (baseline)
- [ ] `node tests/browser/crud-suite.mjs` → tetap hijau (baseline 146)
- [ ] Verifikasi manual di browser sesuai bagian "Cek" di atas
- [ ] Flip `Status:` ke `[x] DONE` + isi commit SHA setelah semua centang
