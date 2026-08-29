# CFG-05 — `app/Services/CvExtractionService.php`

**Fokus:** BP-1/PERF-2 — ganti env() → config() (baris 94)
**Severity:** 🟠 Tinggi
**Status:** [ ] TODO — commit: `______`
**File (satu-satunya) yang disentuh:** `app/Services/CvExtractionService.php`

---

## Masalah
`env()` dipanggil di runtime (baris 94) → `null` setelah `config:cache`. Ganti ke
`config()` (key sudah didefinisikan di **CFG-01**).

## Diff
```php
-        return (string) (setting('cv_python_bin', null) ?: env('PYTHON_BIN', 'python3'));
+        return (string) (setting('cv_python_bin', null) ?: config('services.cv.python_bin'));
```

## Cek per file
- [ ] `grep -n "env(" app/Services/CvExtractionService.php` → **0 hasil** setelah edit.
- [ ] Dengan `config:cache` aktif, fitur terkait (Qdrant/LLM/Embedding/CV/Acc) tetap
      berfungsi (integrasi tak jadi null).

---

## Verifikasi
- [ ] `php -l app/Services/CvExtractionService.php` bersih (kalau file PHP)
- [ ] `php -d memory_limit=2G -d xdebug.mode=off vendor/bin/phpunit --no-coverage` → tetap hijau (baseline)
- [ ] `node tests/browser/crud-suite.mjs` → tetap hijau (baseline 146)
- [ ] Verifikasi manual di browser sesuai bagian "Cek" di atas
- [ ] Flip `Status:` ke `[x] DONE` + isi commit SHA setelah semua centang
