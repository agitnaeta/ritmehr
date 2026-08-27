# T1 — PHPUnit 10 → 11 (test relevan)

**Status:** [ ] TODO · Estimasi: 2–3 hari
**Konteks:** 55 file test PHP, PHPUnit murni (bukan Pest), skema `absensi_testing` terpisah.

## Langkah
- [ ] Bump `phpunit/phpunit:^11` (bareng F2)
- [ ] PHPUnit 11 menghapus dukungan docblock metadata → ubah ke **atribut PHP 8**:
      - `/** @test */` → `#[Test]`
      - `@dataProvider foo` → `#[DataProvider('foo')]`
      - `@depends`, `@group` → `#[Depends]`, `#[Group]`
      Repo ini sudah pakai `@dataProvider` (mis. `ReportingDashboardTest`) — wajib dikonversi.
- [ ] Update `phpunit.xml` ke schema PHPUnit 11 (atribut `cacheDirectory`, hapus opsi usang)
- [ ] Perbaiki assertion/method yang di-deprecate (`assertObjectHasAttribute`, dll bila ada)
- [ ] Pastikan env test (`DB_DATABASE=absensi_testing`, dll) tetap terbaca
- [ ] Jalankan sampai 0 deprecation warning

## Pitfalls
- `artisan test` OOM di repo ini (render menu) — TETAP pakai `phpunit` langsung + `-d memory_limit=2G -d xdebug.mode=off`
- Konversi atribut bisa di-otomasi sebagian dgn Rector (`rector-phpunit`) — review hasilnya

## Kriteria selesai
- PHPUnit 11 jalan tanpa deprecation
- Semua test lama tetap lulus (≥ 403)
