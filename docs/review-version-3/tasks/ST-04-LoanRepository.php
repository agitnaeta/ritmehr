# ST-04 — `app/Repositories/LoanRepository.php`

**Fokus:** PERF-3 — buang subquery ganda + O(n) korelasi
**Severity:** 🟡 Sedang
**Status:** [ ] TODO — commit: `______`
**File (satu-satunya) yang disentuh:** `app/Repositories/LoanRepository.php`

---

## Masalah
`recap()` menghitung `selisih` (baris 24) dengan **subquery korelasi mentah** yang
MENGULANG dua `selectSub` di baris 14-23. Redundan + O(n) subquery per baris pada list
besar.

## Diff (hilangkan raw duplikat, hitung selisih dari alias)
```php
         $recap  = User::select('id', 'name')
             ->selectSub(function ($query) {
                 $query->selectRaw('COALESCE(SUM(amount),0)')
                     ->from('loans')->whereColumn('user_id', 'users.id');
             }, 'kasbon')
             ->selectSub(function ($query) {
                 $query->selectRaw('COALESCE(SUM(amount),0)')
                     ->from('loan_payments')->whereColumn('user_id', 'users.id');
             }, 'terbayar')
-            ->selectRaw('(SELECT SUM(amount) FROM loans WHERE user_id = users.id) - (SELECT SUM(amount) FROM loan_payments WHERE user_id = users.id) AS selisih')
             ->get();
+
+        // selisih dihitung dari alias (tanpa subquery ke-3)
+        $recap->each(fn ($r) => $r->selisih = (int) $r->kasbon - (int) $r->terbayar);
```
> Alternatif skala besar: `leftJoin` + `groupBy` agregat sekali jalan.

## Cek per file
- [ ] Halaman rekap kasbon menampilkan `kasbon`, `terbayar`, `selisih` yang sama dgn
      sebelum perubahan (bandingkan beberapa user).
- [ ] Jumlah query berkurang (2 subquery, bukan 3).

---

## Verifikasi
- [ ] `php -l app/Repositories/LoanRepository.php` bersih (kalau file PHP)
- [ ] `php -d memory_limit=2G -d xdebug.mode=off vendor/bin/phpunit --no-coverage` → tetap hijau (baseline)
- [ ] `node tests/browser/crud-suite.mjs` → tetap hijau (baseline 146)
- [ ] Verifikasi manual di browser sesuai bagian "Cek" di atas
- [ ] Flip `Status:` ke `[x] DONE` + isi commit SHA setelah semua centang
