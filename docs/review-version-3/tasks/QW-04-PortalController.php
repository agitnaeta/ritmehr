# QW-04 — `app/Http/Controllers/Portal/PortalController.php`

**Fokus:** SEC-3 password kuat + SEC-4 pesan error konsisten
**Severity:** 🟡 Sedang
**Status:** [x] DONE — commit: `pending` (terverifikasi 2026-08-29)
**File utama:** `app/Http/Controllers/Portal/PortalController.php`
**File pendamping (konsekuensi kebijakan baru):** `tests/Feature/PortalTest.php` (fixture password lama tak lagi lolos rule → diperbarui + 1 test baru)

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
- [x] `php -l ...PortalController.php` + `PortalTest.php` → bersih
- [x] Proof rule password (Validator) di bawah
- [x] `phpunit --filter password` → **4/4 OK** (termasuk test weak-password baru)
- [x] Full PHPUnit → 426 test, 424 lulus / 2 baseline time-dependent (naik 1 dari test baru)
- [x] `crud-suite.mjs` → **146 PASS**, Portal 5/5
- [x] Flip `Status:` ke `[x] DONE`

## PROOF (2026-08-29)

### 1. Rule password (Validator langsung)
```
lemah 12345678           -> DITOLAK (uppercase+lowercase)
tanpa angka Abcdefgh     -> DITOLAK (butuh angka)
tanpa uppercase abc12345 -> DITOLAK (uppercase+lowercase)
konfirmasi beda          -> DITOLAK (confirmation + HIBP leak)
kuat unik k9Xm2Qp7Lz     -> LOLOS
```
HIBP `uncompromised()` terbukti aktif ("appeared in a data leak").

### 2. Error konsisten
`back()->with('error',...)` → `back()->withErrors(['current_password'=>...])`. Test #1
kini assert `assertSessionHasErrors('current_password')` (bukan `assertSessionHas('error')`).

### 3. Temuan sampingan (jujur)
- **Test lama pakai fixture password lemah** `new-password-123` (huruf kecil semua) → gagal
  oleh rule baru. Diperbarui ke `Zx9Qm2Lp7Kv` (kuat + tak ada di HIBP).
- **`NewPass-123` ternyata SUDAH bocor di HIBP** → sempat bikin test gagal. Pelajaran:
  `uncompromised()` = **dependency jaringan HIBP**, fixture happy-path WAJIB string acak-kuat,
  dan di CI tanpa internet rule ini bisa flaky (catatan untuk ST-05 CI: pertimbangkan
  `Password::min(8)->mixedCase()->numbers()` tanpa `uncompromised()` di env test, atau mock).
- **Tambah test baru** `test_password_change_rejects_a_weak_password` → coverage naik.

### 4. Regresi
- crud-suite: **146/146**, Portal 5/5.
- PHPUnit: 424 lulus / 2 baseline time-dependent (tak ada failure baru).
