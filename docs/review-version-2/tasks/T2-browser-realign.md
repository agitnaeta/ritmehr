# T2 — Browser Suite Realign (Playwright)

**Status:** [x] DONE · Estimasi: 3–5 hari → **aktual ~30 menit (tak perlu perubahan kode)**
**Konteks:** 37 suite `.mjs` + `lib.mjs` shared. Dugaan awal: DOM Backpack + CSRF/cookie L11+ pecah.

## Temuan sebenarnya (root cause 2 FAIL di F2)
Kegagalan `branch/C-valid` & `department/C-valid` (3→3, http 302) **BUKAN**:
- ❌ CSRF/cookie L11+ (session persist normal — dashboard render sbg Siti)
- ❌ DOM Backpack (selector `#crudTable` cocok, list render)
- ❌ bug app (PHPUnit HTTP test: create branch payload minimal TERSIMPAN)

**Root cause sebenarnya:** data test **tidak idempoten**. Branch `ZZ Cabang Uji`
(code `ZZBR`) tersisa di DB dari run crud-suite yang di-*kill* di tengah jalan
(saat debug isu port :8000). Create berikutnya = duplikat `code` unik → ditolak,
count tak berubah (`3→3`). Bukti: `SELECT * FROM branches` menampilkan id=8
`ZZ Cabang Uji` masih ada padahal test belum "create".

## Yang dikerjakan
- [x] Diagnosa: query DB langsung membuktikan branch ZZ tersimpan (backend sehat)
- [x] Bersihkan data sisa: `DELETE FROM branches/departments/positions WHERE code/name LIKE 'ZZ%'`
- [x] Jalankan ulang crud-suite di serve L12 bersih (port :8000, XDEBUG_MODE=off)
- [x] Hasil: **146 PASS / 0 FAIL / 0 SKIP** — semua modul hijau di Laravel 12

## Kesimpulan
DOM Backpack 6.8 + CSRF + session **kompatibel penuh dengan Laravel 12** —
`lib.mjs` tak perlu diubah. Isu murni higiene data test.

## Pitfall (dicatat utk ke depan)
- Jangan kill crud-suite di tengah jalan tanpa membersihkan data `ZZ%` sesudahnya —
  baris sisa membuat run berikutnya gagal di CREATE (duplikat unique).
- Untuk browser test L11+: `XDEBUG_MODE=off php -d display_errors=Off artisan serve --port=8000`
  dan pastikan :8000 tak dipegang serve lama (`lsof -ti :8000`).

## Kriteria selesai
- [x] crud-suite 146/146 hijau di Laravel 12 + Backpack 6.8
- [x] Tak ada perubahan kode harness yang diperlukan (isu data, bukan kode)
