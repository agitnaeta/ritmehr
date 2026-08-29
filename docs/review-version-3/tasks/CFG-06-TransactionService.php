# CFG-06 — `app/Services/TransactionService.php`

**Fokus:** BP-1/PERF-2 — ganti env() → config() (baris 30)
**Severity:** 🟠 Tinggi
**Status:** [ ] TODO — commit: `______`
**File (satu-satunya) yang disentuh:** `app/Services/TransactionService.php`

---

## Masalah
`env()` dipanggil di runtime (baris 30) → `null` setelah `config:cache`. Ganti ke
`config()` (key sudah didefinisikan di **CFG-01**).

## Diff
```php
-            : (bool) setting('acc_active', env('ACC_ACTIVE'));
+            : (bool) setting('acc_active', config('services.acc.active'));
```

## Cek per file
- [ ] `grep -n "env(" app/Services/TransactionService.php` → **0 hasil** setelah edit.
- [ ] Dengan `config:cache` aktif, fitur terkait (Qdrant/LLM/Embedding/CV/Acc) tetap
      berfungsi (integrasi tak jadi null).

---

## Verifikasi
- [ ] `php -l app/Services/TransactionService.php` bersih (kalau file PHP)
- [ ] `php -d memory_limit=2G -d xdebug.mode=off vendor/bin/phpunit --no-coverage` → tetap hijau (baseline)
- [ ] `node tests/browser/crud-suite.mjs` → tetap hijau (baseline 146)
- [ ] Verifikasi manual di browser sesuai bagian "Cek" di atas
- [ ] Flip `Status:` ke `[x] DONE` + isi commit SHA setelah semua centang
