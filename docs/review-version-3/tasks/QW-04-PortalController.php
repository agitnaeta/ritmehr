# QW-04 — `app/Http/Controllers/Portal/PortalController.php`

**Fokus:** SEC-3 password kuat + SEC-4 pesan error konsisten
**Severity:** 🟡 Sedang
**Status:** [ ] TODO — commit: `______`
**File (satu-satunya) yang disentuh:** `app/Http/Controllers/Portal/PortalController.php`

---

## Masalah
1. `changePassword` (baris 288) hanya `min:8` — tanpa cek kompleksitas/kebocoran.
2. Saat password lama salah (baris 298) pakai `back()->with('error',...)` (flash), tak
   konsisten dgn form lain yang pakai `withErrors`.

## Diff
```php
use Illuminate\Validation\Rules\Password;   // di atas file (kalau belum ada)

     public function changePassword(Request $request): RedirectResponse
     {
         $request->validate([
             'current_password' => 'required',
-            'password'         => 'required|string|min:8|confirmed',
+            'password'         => ['required','confirmed', Password::min(8)->mixedCase()->numbers()->uncompromised()],
         ], [ ... ]);

         $user = $this->me();

         if (! Hash::check($request->input('current_password'), $user->password)) {
-            return back()->with('error', 'Password saat ini salah.');
+            return back()->withErrors(['current_password' => 'Password saat ini salah.']);
         }
```

## Cek per file
- [ ] Submit password lemah (`12345678`) → ditolak validasi.
- [ ] Salah password lama → error muncul di bawah field `current_password`.
- [ ] Ganti password valid tetap sukses.

---

## Verifikasi
- [ ] `php -l app/Http/Controllers/Portal/PortalController.php` bersih (kalau file PHP)
- [ ] `php -d memory_limit=2G -d xdebug.mode=off vendor/bin/phpunit --no-coverage` → tetap hijau (baseline)
- [ ] `node tests/browser/crud-suite.mjs` → tetap hijau (baseline 146)
- [ ] Verifikasi manual di browser sesuai bagian "Cek" di atas
- [ ] Flip `Status:` ke `[x] DONE` + isi commit SHA setelah semua centang
