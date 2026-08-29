# UM-11 — "Cetak Semua ID" aman untuk 10rb user

**Poin Capt #10** · Tipe: Struktural · Urgensi: Tinggi · Risiko: Sedang
**Status:** [ ] TODO · ⚠️ **Mockup alur dulu** + Keputusan Terbuka

---

## Konteks
"Cetak Semua ID" merender SATU PDF berisi semua user secara sinkron. 10rb user =
CPU/RAM hang + timeout. Butuh strategi aman.

## Akar masalah (terverifikasi)
- `UserCrudController::printAll()` (`:397-400`) → `User::all()` (muat semua ke RAM).
- `_print()` (`:401-420`) loop tiap user: baca file gambar + generate QR base64, lalu
  render satu dompdf. Tidak ada batas jumlah, tidak ada chunk/queue.

## 🔴 Temuan keamanan (WAJIB diperbaiki bareng task ini)
- Route `GET /admin/user/print-all` & `/{id}/print` (`custom.php:60-61`) **tanpa
  middleware `permission:`**; `printAll()`/`print()` **tak `hasAccessOrFail()`**.
  `setup()` (guard + scope `visibleTo`) tak jalan untuk route custom →
  `printAll()` = `User::all()` mencetak ID **seluruh** karyawan, kebal permission &
  scope. `print($id)` mencetak siapa pun tanpa cek visibilitas.
- **Fix wajib:** guard `abort_unless(backpack_user()?->can('user.view'),403)` di awal
  `print`/`printAll`; batasi query dengan `->visibleTo(backpack_user())`; (defense in
  depth) `->middleware('permission:user.view')` pada route group. Lihat `audit-plan.md` §B2.
- ⚠️ Catatan refactor: `_print()` menimpa `$user->image`/`$user->qr` in-memory —
  saat pindah ke batch/queue, jaga agar perubahan ini tak bocor ke path yang `save()`.

> ⚠️ **Prasyarat infra queue** (untuk opsi background) sama dengan UM-09 (driver `sync`,
> tabel `jobs` belum ada). Baca section "Realita Infra" di UM-09. **Namun** opsi 1 & 2
> di bawah (cetak terpilih / cetak hasil filter) TIDAK butuh queue — bisa dikerjakan
> lebih dulu tanpa infra, dan sudah menyelesaikan mayoritas kasus nyata HR.

## Rencana solusi (desain — pilih kombinasi)
1. **Cetak terseleksi** (prioritas UX): checkbox baris + tombol "Cetak Terpilih" →
   hanya render yang dipilih (paling sering dibutuhkan HR).
2. **Cetak hasil filter**: printAll menghormati filter aktif (departemen/cabang/status)
   alih-alih literal semua.
3. **Guard ambang sinkron**: jika jumlah > N (mis. 200) → tolak render sinkron, arahkan
   ke **mode background**: job men-generate PDF (chunk) → simpan storage → tautan unduh.
4. **Background job** untuk batch besar (reuse pola queue UM-09/10): status + polling +
   unduh saat siap. PDF besar bisa dipecah per-halaman/batch.

File yang disentuh (rencana):
- `app/Http/Controllers/Admin/UserCrudController.php` (printAll → filter/selection/guard;
  print selected; dispatch job untuk batch besar; status & download endpoint)
- `app/Jobs/GenerateIdCardsJob.php` (BARU)
- `resources/views/user/detail.blade.php` (pastikan efisien) + view status/unduh
- `resources/views/vendor/backpack/...` tombol "Cetak Terpilih" + bulk checkbox
- `routes/backpack/custom.php`

## Mockup (WAJIB sebelum koding)
- `docs/review-version-4/mockup/um-11-cetak-terpilih.html` — bar seleksi + tombol.
- `docs/review-version-4/mockup/um-11-cetak-background-status.html` — status + unduh.
- Render + kirim ke Capt untuk ACC.

## Keputusan Terbuka (TANYA Capt)
1. **Ambang N** untuk paksa background (rekomendasi 200)?
2. Prioritas fitur: cetak **terpilih** & **hasil filter** dulu (cukup untuk mayoritas
   kasus), background job untuk 10rb menyusul? Atau ketiganya sekaligus?
3. Layout kartu: tetap 1 kartu/halaman (`[0,0,300,470]`) atau multi-kartu per halaman A4
   (hemat kertas & lebih cepat)?
4. Queue & worker (sama seperti UM-09).

## Rencana test UI
`tests/browser/um-11-print-id.mjs` + PHPUnit:
- TC1 (browser): pilih 2 user via checkbox → "Cetak Terpilih" → PDF hanya 2 kartu.
- TC2 (browser): filter departemen → Cetak → PDF hanya user departemen itu.
- TC3 (PHPUnit): panggil printAll dengan count > ambang → TIDAK render sinkron
  (dispatch job / redirect ke status), tak memuat semua ke RAM.
- TC4: PDF valid (magic bytes `%PDF`), tak 5xx.

## Definition of Done
Mockup di-ACC; cetak tak lagi hang untuk data besar (seleksi/filter/guard+background);
test PASS; update Status + centang README.
