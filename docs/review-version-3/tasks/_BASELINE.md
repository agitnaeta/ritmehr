# Baseline Test — sebelum eksekusi Review v3

Tanggal: 2026-08-29 · Build awal: `cd1ba8d` · Diukur sebelum task apa pun dikerjakan.

## PHPUnit
```
php -d memory_limit=2G -d xdebug.mode=off vendor/bin/phpunit --no-coverage
Tests: 425, Assertions: 1104, Failures: 2.
```
**2 failure PRE-EXISTING (bukan akibat task Review v3) — time-dependent:**
1. `OrgStructureTest::test_months_of_service_counts_whole_months` — `now()->subMonths(18)`
   menghasilkan 17 vs 18 (off-by-one posisi hari dalam bulan).
2. `TaxServiceTest::test_thr_is_prorated_for_a_partial_year` — `now()->subMonths(6)`
   menghasilkan 5.000.000 vs 6.000.000 (prorata bergeser 1 bulan).

→ Kriteria "tetap hijau" untuk tiap task = **423 lulus / 2 failure yang SAMA ini**.
   Kalau muncul failure ke-3, itu regresi dari task.

## Browser — crud-suite.mjs
```
node tests/browser/crud-suite.mjs
146 PASS / 0 FAIL / 0 SKIP
```

## Lingkungan
- Docker: `absensi-mysql` + `absensi-qdrant` running (waha di-skip).
- `php artisan serve --port=8000` (XDEBUG_MODE=off), `/admin/login` → 200.
- Seed: users=5, presences=115.
- Git working tree: hanya docs Review v3 + promo-video (untracked). Tak ada staged
  deletion dari sesi paralel.
