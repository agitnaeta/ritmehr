# F4 — Regresi Penuh & Rilis

**Status:** [ ] TODO · Estimasi: 1–2 hari
**Prasyarat:** F3 selesai, T1+T2 test sudah relevan.

## Langkah
- [ ] PHPUnit penuh hijau (target ≥ baseline 403): `php -d memory_limit=2G -d xdebug.mode=off vendor/bin/phpunit --no-coverage`
- [ ] Browser suite penuh hijau: `crud-suite.mjs` (146) + `ui-test.mjs` + per-modul `mXX-*.mjs`
- [ ] Smoke manual lintas peran (super_admin/hr_admin/manager/employee):
      - Absensi `/scan` publik
      - Cetak slip gaji PDF + kartu ID (dompdf 3)
      - Import Excel karyawan & gaji (IMP)
      - Setup Wizard `/admin/setup` end-to-end (WIZ)
      - Portal `/my`
- [ ] Cek notifikasi WA (LogWhatsAppGateway) & backup command jalan
- [ ] Update README: badge Laravel 10→12, PHP, catatan versi
- [ ] Update `docs/upgrade-plan/README.md` — tandai semua fase DONE + tanggal
- [ ] Restore DB demo ke seed bila termutasi test
- [ ] Merge `upgrade/laravel-12` → `master`, tag rilis

## Kriteria selesai
- Semua lapis test hijau di Laravel 12
- Smoke manual lulus, tak ada regresi fungsional
- README & plan tercermin kondisi baru
