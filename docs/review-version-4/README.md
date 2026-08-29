# Review Version 4 — Modul User (Admin) `/admin/user`

Master index + metodologi untuk perbaikan modul **User (sisi admin)** RitmeHR.
Sumber: laporan analisa Capt (10 poin) + verifikasi langsung ke KODE dan UI.

> **Prinsip skill `structured-module-development`:** evaluasi dulu (verifikasi ke
> kode, bukan asumsi), baru koding. Satu task = satu file, dikerjakan
> satu-per-satu, tiap task WAJIB test UI nyata sebelum ditandai DONE.

> 📋 **[`audit-plan.md`](audit-plan.md)** — hasil audit plan-vs-code (skill
> `laravel-codebase-audit`). Semua 11 klaim akar-masalah terbukti di kode; memuat
> **1 temuan keamanan 🔴 (B2: export/print bypass permission+scope)** yang sudah
> ditambahkan ke UM-10 & UM-11. Baca sebelum eksekusi.

---

## Ringkasan 10 poin → task

| Task | Poin | Judul | Tipe | Urgensi | Risiko | Status |
|------|------|-------|------|---------|--------|--------|
| [UM-01](tasks/UM-01-tabel-responsif-mobile.md) | 1 | Tabel responsif & layout mobile berantakan | CSS + kolom | Tinggi | Rendah | [ ] TODO |
| [UM-02](tasks/UM-02-kontras-field-form.md) | 2 | Kontras `.form-control`/`.form-select` abu-di-abu | CSS | Tinggi | Rendah | [x] DONE |
| [UM-03](tasks/UM-03-toolbar-presisi.md) | 3 | Tata letak toolbar (filter/tambah/export/import/search) tidak presisi | CSS/layout | Tinggi | Rendah | [ ] TODO |
| [UM-04](tasks/UM-04-import-employee-id.md) | 4 | Import tidak dukung kolom NIK/`employee_id` | Import | Sedang | Rendah | [ ] TODO |
| [UM-05](tasks/UM-05-default-locale-id.md) | 5 | Bahasa (locale) karyawan default `id` | Migration+model | Sedang | Rendah | [ ] TODO |
| [UM-06](tasks/UM-06-show-tab-foto-pelatihan.md) | 6 | Halaman Show bertab: Profil + Foto + Riwayat Pelatihan | View (struktural) | Sedang | Sedang | [ ] TODO |
| [UM-07](tasks/UM-07-show-label-terjemahan.md) | 7 | Label Show/form masih campur Inggris | i18n/label | Sedang | Rendah | [ ] TODO |
| [UM-08](tasks/UM-08-locale-dropdown-bahasa.md) | 8 | `locale` jadi dropdown pilihan bahasa | Field | Sedang | Rendah | [ ] TODO |
| [UM-09](tasks/UM-09-import-background-queue.md) | 9 | Import 1000+ user → background/queue + progress | Struktural | Tinggi | Sedang | [ ] TODO |
| [UM-10](tasks/UM-10-export-background-queue.md) | 9 | Export skala besar → background/queue + unduh | Struktural | Sedang | Sedang | [ ] TODO |
| [UM-11](tasks/UM-11-cetak-id-aman-skala.md) | 10 | "Cetak Semua ID" aman untuk 10rb user | Struktural | Tinggi | Sedang | [ ] TODO |

---

## Urutan eksekusi (by urgensi + dependensi)

Kerjakan **satu-per-satu**, dari cepat/aman ke struktural:

1. **UM-02** — kontras field (quick, high-visible, low-risk) ✅ DONE
2. **B2** — keamanan export/print (fix scope+guard) ✅ DONE
3. **UM-03** — toolbar presisi (dropdown ⋯) ✅ DONE
4. **UM-01** — tabel responsif & mobile ✅ DONE
5. **UM-05** — default locale `id` (fondasi untuk UM-08) ✅ DONE
6. **UM-08** — locale dropdown bahasa (butuh UM-05) ✅ DONE
7. **UM-04** — import `employee_id` ✅ DONE
8. **UM-07** — sapu label campur (form + hapus auto-show Inggris) ✅ DONE
9. **UM-06** — custom Show bertab + foto + pelatihan (struktural → **mockup HTML dulu**)
10. **UM-11** — cetak semua ID aman skala (struktural → **mockup HTML dulu**)
11. **UM-09** — import background/queue (struktural)
12. **UM-10** — export background/queue (struktural)

> Task struktural (UM-06, UM-09, UM-10, UM-11) → sesuai kebiasaan Capt:
> **mockup/rancangan HTML atau alur di-approve dulu** sebelum koding.

---

## Rubrik evaluasi 7-poin (hasil terhadap modul User)

| # | Aspek | Temuan |
|---|-------|--------|
| 1 | Kelengkapan proses bisnis | CRUD + reset password (via edit) ADA. Show masih dump mentah (UM-06). Import/export ADA tapi sinkron & tak skala (UM-09/10/11). |
| 2 | Dependensi eksternal | Tidak ada dependensi eksternal wajib. QR & PDF lokal (dompdf, simplesoftwareio/qrcode). OK. |
| 3 | Best-practice UI/UX | **Bermasalah:** tabel collapse ke 1 kolom (UM-01), kontras field buruk (UM-02), toolbar berantakan (UM-03), Show mentah (UM-06). |
| 4 | Third-party config | Tidak relevan (tak ada layanan pihak-3 di modul ini). |
| 5 | Keterkaitan antar-fitur | Show belum menautkan **riwayat pelatihan** (relasi `TrainingEnrollment` sudah ada tapi tak ditampilkan) → UM-06. |
| 6 | Lokalisasi (i18n) | Fondasi i18n ADA (`lang/id`, `lang/en/menu.php`, switcher "Bahasa (ID)"). Modul ini pakai level **hardcode ID** untuk label CRUD (konsisten proyek). Show masih Inggris (UM-07). Locale user null (UM-05). |
| 7 | Mata uang | Tidak relevan untuk modul User. |

---

## Definition of Done (per task)

Sebuah task hanya boleh ditandai `[x] DONE` bila:

1. Kode diimplementasikan sesuai rencana di file task.
2. **Test UI nyata** dijalankan (Playwright `tests/browser/*.mjs` mengikuti konvensi
   `tests/browser/lib.mjs`) — happy path + edge case, tunjukkan `N PASS / 0 FAIL`.
3. Untuk perubahan yang menyentuh service inti / import-export: jalankan **seluruh
   suite** PHPUnit (`php -d memory_limit=2G -d xdebug.mode=off vendor/bin/phpunit --no-coverage`) → nol regresi.
4. Verifikasi VISUAL via screenshot + `vision_analyze` untuk task UI (UM-01/02/03/06).
5. Update `Status` di file task jadi `[x] DONE` + hash commit, dan centang di tabel README ini.

---

## Konvensi

- File task: `tasks/UM-NN-slug.md`, satu masalah = satu file.
- Setiap task memuat: Konteks, Akar Masalah (dengan `file:line` hasil verifikasi),
  Rencana Solusi (daftar file yang disentuh), Rencana Test UI, Status.
- Bukti visual & analisa mendalam ada di [`analisis-teknis.md`](analisis-teknis.md).
- **Test browser: reuse `tests/browser/lib.mjs`** (helper `session()`, `rowCount()`,
  `record()`, `summary()`; `BASE=http://127.0.0.1:8000`; login demo `password`).
  Jalankan `node tests/browser/um-<nn>-*.mjs`. JANGAN bikin harness baru.
- Test PHPUnit: `php -d memory_limit=2G -d xdebug.mode=off vendor/bin/phpunit --no-coverage`
  (wrapper `artisan test` bisa OOM/segfault dgn xdebug). Role/permission di guard **backpack**.
- Test browser di `tests/browser/um-<nn>-*.mjs`; feature test di `tests/Feature/`.
