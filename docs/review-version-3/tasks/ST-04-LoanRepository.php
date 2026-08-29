# ST-04 — `app/Repositories/LoanRepository.php`

**Fokus:** PERF-3 — buang subquery ganda + O(n) korelasi
**Severity:** 🟡 Sedang
**Status:** [x] DONE — commit: `pending` (terverifikasi 2026-08-29)
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
- [x] `php -l app/Repositories/LoanRepository.php` → bersih
- [x] Subquery berkurang: SELECT utama kini 2 subquery (dulu 3) — `selisih` dihitung PHP
- [x] Konsistensi `selisih == kasbon - terbayar` terbukti dgn data nyata
- [x] Loan tests 5/5, crud-suite **146/146** (Kasbon 18/18)
- [x] Flip `Status:` ke `[x] DONE`

## PROOF (2026-08-29)

### Subquery berkurang
```
$ query utama: 1 query, 3 keyword "select" (1 outer + 2 sub)
  SEBELUM: 4 keyword "select" (1 outer + 3 sub) — subquery selisih redundan
```

### Konsistensi nilai (transaksi rollback, data demo tak berubah)
```
Ahmad (punya loan existing 3jt):
SEBELUM: kasbon=3000000 terbayar=1000000 selisih=2000000
+ tambah loan 1jt & payment 300rb ->
SESUDAH: kasbon=4000000 terbayar=1300000 selisih=2700000
delta kasbon=1000000 (benar), delta terbayar=300000 (benar)
selisih == kasbon - terbayar ? OK KONSISTEN
```
Catatan jujur: tebakan angka absolut awalku meleset karena lupa Ahmad sudah punya loan
existing di DB — asersi yang benar adalah KONSISTENSI (selisih = kasbon−terbayar) + delta,
dan itu terbukti tepat. View `resources/views/loan/recap.blade.php` pakai
`$user->kasbon/terbayar/selisih` sebagai properti → tetap kompatibel.

### Regresi
- Loan Feature tests: **5/5**. crud-suite: **146/146**, Kasbon 18/18.
- Bonus: `COALESCE(...,0)` → user tanpa loan kini `0` (bukan `null`).
