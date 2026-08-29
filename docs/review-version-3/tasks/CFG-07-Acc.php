# CFG-07 — `app/Services/Acc/Acc.php`

**Fokus:** BP-1/PERF-2 — ganti env() → config() (baris 21,22)
**Severity:** 🟠 Tinggi
**Status:** [ ] TODO — commit: `______`
**File (satu-satunya) yang disentuh:** `app/Services/Acc/Acc.php`

---

## Masalah
`env()` dipanggil di runtime (baris 21,22) → `null` setelah `config:cache`. Ganti ke
`config()` (key sudah didefinisikan di **CFG-01**).

## Diff
```php
-        $this->host = setting('acc_host', env('ACC_HOST'));
-        $this->key = setting('acc_key', env('ACC_KEY'));
+        $this->host = setting('acc_host', config('services.acc.host'));
+        $this->key = setting('acc_key', config('services.acc.key'));
```

## Cek per file
- [ ] `grep -n "env(" app/Services/Acc/Acc.php` → **0 hasil** setelah edit.
- [ ] Dengan `config:cache` aktif, fitur terkait (Qdrant/LLM/Embedding/CV/Acc) tetap
      berfungsi (integrasi tak jadi null).

---

## Verifikasi
- [ ] `php -l app/Services/Acc/Acc.php` bersih (kalau file PHP)
- [ ] `php -d memory_limit=2G -d xdebug.mode=off vendor/bin/phpunit --no-coverage` → tetap hijau (baseline)
- [ ] `node tests/browser/crud-suite.mjs` → tetap hijau (baseline 146)
- [ ] Verifikasi manual di browser sesuai bagian "Cek" di atas
- [ ] Flip `Status:` ke `[x] DONE` + isi commit SHA setelah semua centang
