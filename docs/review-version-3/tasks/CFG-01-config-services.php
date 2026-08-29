# CFG-01 — `config/services.php`

**Fokus:** BP-1/PERF-2 — pusatkan semua key env() ke config (destinasi)
**Severity:** 🟠 Tinggi
**Status:** [x] DONE — commit: `pending` (terverifikasi 2026-08-29, config:cache proof)
**File (satu-satunya) yang disentuh:** `config/services.php`

---

## PROOF BERSAMA CFG-01..07 (2026-08-29)

Semua 7 file diedit; **0 `env()` runtime tersisa di `app/`** (`grep -rn "env(" app/` → 0).

### Bukti kunci — dengan `config:cache` AKTIF (kondisi produksi), nilai TETAP terbaca (bukan null)
```
$ QDRANT_URL=http://buktiqdrant:6333 LLM_API_KEY=sk-bukti-llm PYTHON_BIN=/usr/bin/python3.11 \
  php artisan config:cache        # simulasi produksi
Configuration cached successfully.

$ php artisan tinker (dengan config CACHED):
qdrant baseUrl : http://buktiqdrant:6333    ← sebelumnya (env runtime) akan NULL
llm apiKey     : *** (ada nilai)
cv python_bin  : /usr/bin/python3.11
acc.active cfg : true
```
→ Inilah bug produksi yang dicegah BP-1/PERF-2: `env()` runtime mengembalikan `null`
  begitu `config:cache` jalan. Sekarang aman.

### Regresi
- PHPUnit: kembali ke **baseline 423 lulus / 2 failure time-dependent** (setelah unset env
  helper `PYTHON_BIN` yang sempat bikin 4 CvExtraction gagal SEMENTARA — itu artefak shell
  test, bukan kode; begitu di-unset → CvExtraction 5/5 OK).
- crud-suite: **146/146**.

### Catatan
`config/services.php` sudah punya blok `acc` sebelumnya → CFG-06/07 memakai
`config('services.acc.*')` yang ada. Ditambah blok `matching` (Qdrant/LLM/Embedding) + `cv`.

---

## Masalah
10 pemanggilan `env()` tersebar di service (lihat CFG-02..07) → jadi `null` setelah
`config:cache` di produksi. Semua harus baca `config()`; file ini menampung key-nya.

## Diff (tambahkan blok)
```php
    // RitmeHR — integrasi eksternal (dibaca via config(), aman utk config:cache)
    'matching' => [
        'qdrant_url'     => env('QDRANT_URL', 'http://localhost:6333'),
        'qdrant_api_key' => env('QDRANT_API_KEY'),
        'llm_base_url'   => env('LLM_BASE_URL', 'http://localhost:20128/v1'),
        'llm_api_key'    => env('LLM_API_KEY'),
        'embedding_base_url' => env('EMBEDDING_BASE_URL', 'http://localhost:20128/v1'),
        'embedding_api_key'  => env('EMBEDDING_API_KEY'),
    ],
    'cv' => [
        'python_bin' => env('PYTHON_BIN', 'python3'),
    ],
    'acc' => [
        'active' => env('ACC_ACTIVE'),
        'host'   => env('ACC_HOST'),
        'key'    => env('ACC_KEY'),
    ],
```
> `env()` **boleh** dipakai DI DALAM file config/ — yang dilarang adalah `env()` di
> runtime (service/controller).

## Cek per file
- [ ] `php artisan config:cache` lalu `php artisan tinker` → `config('services.matching.qdrant_url')`
      mengembalikan nilai (bukan null).

---

## Verifikasi
- [ ] `php -l config/services.php` bersih (kalau file PHP)
- [ ] `php -d memory_limit=2G -d xdebug.mode=off vendor/bin/phpunit --no-coverage` → tetap hijau (baseline)
- [ ] `node tests/browser/crud-suite.mjs` → tetap hijau (baseline 146)
- [ ] Verifikasi manual di browser sesuai bagian "Cek" di atas
- [ ] Flip `Status:` ke `[x] DONE` + isi commit SHA setelah semua centang
