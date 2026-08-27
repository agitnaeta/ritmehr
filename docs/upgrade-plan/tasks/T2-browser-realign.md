# T2 — Browser Suite Realign (Playwright) · RISIKO TEST TERBESAR

**Status:** [ ] TODO · Estimasi: 3–5 hari
**Konteks:** 37 suite `.mjs` + `lib.mjs` shared. Backpack 7 + theme-tabler 2 mengubah DOM/kelas → selector lama bisa merah massal.

## Langkah
- [ ] Jalankan suite setelah F1 (Backpack 7) untuk lihat mana yang merah:
      `node tests/browser/crud-suite.mjs`, `ui-test.mjs`, tiap `mXX-*.mjs`
- [ ] Perbaiki **`lib.mjs` dulu** (dipakai semua) — helper `login()`, `rowCount()`, selector `#crudTable tbody tr`, `.dataTables_info`. Kalau DOM Backpack 7 ganti wrapper, cukup 1 tempat.
- [ ] Sisir per suite: selector tombol/aksi yang berubah (label & kelas Tabler 2), form field, drawer/modal
- [ ] Suite modul baru pastikan tetap relevan: `m20-breakdown`, `m20b-inline-allowance`, `m21-ranking`, dan tombol Import Excel (IMP) + Setup Wizard (WIZ) bila ditambah suite-nya
- [ ] Pertimbangkan tambah suite untuk fitur baru yang belum tercover browser: import Excel end-to-end, wizard end-to-end (saat ini hanya diuji handler asli + phpunit)
- [ ] Standардисasi timeout (artisan serve single-thread) — sudah ada di `lib.mjs` (120s)

## Pitfalls
- Backpack 7 mungkin ganti nama kelas DataTables / struktur `#crudTable` → cek 1x, fix di `lib.mjs`
- Jangan bypass UI via API untuk "menghijaukan" — automasi bukan hacking (test harus lewat handler app)
- Login submit flaky di artisan serve — pertahankan pola `waitForNavigation` + fallback

## Kriteria selesai
- 37 suite + `crud-suite`/`ui-test` hijau di Laravel 12 + Backpack 7
- Selector terpusat di `lib.mjs` sedapat mungkin (biaya maintenance turun)
