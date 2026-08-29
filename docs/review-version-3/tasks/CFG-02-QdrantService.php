# CFG-02 — `app/Services/Matching/QdrantService.php`

**Fokus:** BP-1/PERF-2 — ganti env() → config() (baris 18,23)
**Severity:** 🟠 Tinggi
**Status:** [ ] TODO — commit: `______`
**File (satu-satunya) yang disentuh:** `app/Services/Matching/QdrantService.php`

---

## Masalah
`env()` dipanggil di runtime (baris 18,23) → `null` setelah `config:cache`. Ganti ke
`config()` (key sudah didefinisikan di **CFG-01**).

## Diff
```php
-        return rtrim((string) (setting('qdrant_url') ?: env('QDRANT_URL', 'http://localhost:6333')), '/');
+        return rtrim((string) (setting('qdrant_url') ?: config('services.matching.qdrant_url')), '/');
...
-        return setting('qdrant_api_key') ?: env('QDRANT_API_KEY');
+        return setting('qdrant_api_key') ?: config('services.matching.qdrant_api_key');
```

## Cek per file
- [ ] `grep -n "env(" app/Services/Matching/QdrantService.php` → **0 hasil** setelah edit.
- [ ] Dengan `config:cache` aktif, fitur terkait (Qdrant/LLM/Embedding/CV/Acc) tetap
      berfungsi (integrasi tak jadi null).

---

## Verifikasi
- [ ] `php -l app/Services/Matching/QdrantService.php` bersih (kalau file PHP)
- [ ] `php -d memory_limit=2G -d xdebug.mode=off vendor/bin/phpunit --no-coverage` → tetap hijau (baseline)
- [ ] `node tests/browser/crud-suite.mjs` → tetap hijau (baseline 146)
- [ ] Verifikasi manual di browser sesuai bagian "Cek" di atas
- [ ] Flip `Status:` ke `[x] DONE` + isi commit SHA setelah semua centang
