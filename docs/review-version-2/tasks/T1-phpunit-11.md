# T1 — PHPUnit 10 → 11 (test relevan)

**Status:** [x] DONE · commit: `(uncommitted)` · menyatu dengan F3
**Konteks:** 55+ file test PHPUnit murni, skema `absensi_testing` terpisah.

## Yang dikerjakan
- [x] Bump `phpunit/phpunit:^11` (ditarik oleh collision 8.9 di F3)
- [x] Konversi metadata docblock → **atribut PHP 8** (PHPUnit 11 deprecate docblock):
      - `ReportingDashboardTest`: 2× `@dataProvider reportRoutes` → `#[DataProvider('reportRoutes')]`
      - `TerOracleTest`: 1× `@dataProvider oracleProvider` → `#[DataProvider('oracleProvider')]`
      - tambah `use PHPUnit\Framework\Attributes\DataProvider;` di kedua file
- [x] Tidak ada `@test`/`@depends`/`@group` lain (sudah dicek grep)
- [x] `phpunit.xml` schema PHPUnit 11 kompatibel (tak ada opsi usang)

## Hasil
- PHPUnit 11.5.56 jalan **tanpa deprecation** (sebelumnya 3 deprecation)
- 403/403 test tetap lulus

## Pitfall terkonfirmasi
- `artisan test` OOM di repo ini → TETAP pakai `phpunit` langsung + `-d memory_limit=2G -d xdebug.mode=off`
