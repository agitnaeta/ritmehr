# CFG-04 — `app/Services/Matching/EmbeddingManager.php`

**Fokus:** BP-1/PERF-2 — ganti env() → config() (baris 34,41)
**Severity:** 🟠 Tinggi
**Status:** [x] DONE — commit: `pending` (terverifikasi 2026-08-29, config:cache proof)
**File (satu-satunya) yang disentuh:** `app/Services/Matching/EmbeddingManager.php`

---

## Masalah
`env()` dipanggil di runtime (baris 34,41) → `null` setelah `config:cache`. Ganti ke
`config()` (key sudah didefinisikan di **CFG-01**).

## Diff
```php
-            : (string) (env('EMBEDDING_BASE_URL', 'http://localhost:20128/v1'));
+            : (string) config('services.matching.embedding_base_url');
...
-        return setting('embedding_api_key') ?: env('EMBEDDING_API_KEY');
+        return setting('embedding_api_key') ?: config('services.matching.embedding_api_key');
```

## Cek per file
- [ ] `grep -n "env(" app/Services/Matching/EmbeddingManager.php` → **0 hasil** setelah edit.
- [ ] Dengan `config:cache` aktif, fitur terkait (Qdrant/LLM/Embedding/CV/Acc) tetap
      berfungsi (integrasi tak jadi null).

---

## Verifikasi
- [ ] `php -l app/Services/Matching/EmbeddingManager.php` bersih (kalau file PHP)
- [ ] `php -d memory_limit=2G -d xdebug.mode=off vendor/bin/phpunit --no-coverage` → tetap hijau (baseline)
- [ ] `node tests/browser/crud-suite.mjs` → tetap hijau (baseline 146)
- [ ] Verifikasi manual di browser sesuai bagian "Cek" di atas
- [ ] Flip `Status:` ke `[x] DONE` + isi commit SHA setelah semua centang
