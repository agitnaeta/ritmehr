# RV5-01 — Task Breakdown (per file)

**Konteks:** Audit header 2-baris GLOBAL lintas semua halaman list `/admin`.
Hasil audit (read-only, `tests/browser/rv5-01-header-audit.mjs` + `rv5-01-mobile-dark.mjs`):

- **37 halaman list Backpack CRUD → PASS 100%** di desktop 1280px, mobile 390px, dark mode.
  Header 2-baris (breadcrumb kiri / tombol kanan / heading+search+filter baris 2) **tidak pecah**,
  tidak ada overflow horizontal, tidak ada overlap search×tombol, `#datatable_info_stack`
  konsisten `inline-grid`. Bukti: `tests/browser/rv5-01-report.json` +
  `docs/review-version-5/screenshots/rv5-01-*.png`.
- **3 halaman `/admin/*` bukan CRUD Backpack** (pakai `Controller` biasa + Blade custom, TIDAK
  kena override `list.blade.php`): `training`, `notification`, `employee-document`.

**Kesimpulan:** untuk halaman CRUD, RV5-01 **tidak butuh perbaikan layout** — pola global sudah
konsisten. Yang tersisa: (A) keputusan konsistensi 3 halaman custom, (B) kunci hasil audit dengan
regression guard supaya tidak regres diam-diam saat ada perubahan `list.blade.php`/`admin.css` di masa depan.

---

## Ringkasan Task

| ID | File | Deskripsi | Status |
|----|------|-----------|--------|
| RV5-01-C1 | `resources/views/components/admin/page-header.blade.php` *(baru)* | Komponen header 2-baris reusable | [x] DONE |
| RV5-01-C2 | `resources/views/admin/training/index.blade.php` | Pelatihan pakai header 2-baris | [x] DONE |
| RV5-01-C3 | `resources/views/admin/notification/index.blade.php` | Notifikasi pakai header 2-baris | [x] DONE |
| RV5-01-C4 | `resources/views/admin/document/index.blade.php` | Dokumen Karyawan pakai header 2-baris | [x] DONE |
| RV5-01-C5 | `tests/browser/rv5-01c-noncrud-header.mjs` *(baru)* | Regression guard 3 halaman non-CRUD | [x] DONE |
| RV5-01-A | `tests/browser/rv5-01-header-audit.mjs` | Guard permanen 37 CRUD (exit non-zero kalau pecah) | [x] DONE |
| RV5-01-B | `docs/review-version-5/README.md` | Flip sub-checklist audit DONE + catat hasil | [ ] TODO |

**Desain RV5-01-C sudah disetujui Capt** — mockup di `docs/review-version-5/mockups/`.

---

## RV5-01-A — Regression guard header 2-baris

**Status:** [x] DONE (uncommitted, terverifikasi) — `rv5-01-header-audit.mjs` exit 0, 37 CRUD PASS

**File:** `tests/browser/rv5-01-header-audit.mjs` (sudah ada, dipakai buat audit).

**Yang dikerjakan:**
- Ubah dari script cetak-laporan → test yang **exit non-zero** kalau ada CRUD page ISSUE
  (biar bisa masuk CI / dijalankan sebagai gate).
- Kecualikan 3 slug non-CRUD (`training`, `notification`, `employee-document`) dari daftar —
  mereka bukan target pola ini.
- Assert per halaman: `!horizOverflow`, `searchBtnOverlap !== true`, `hasH1`, `hasSearch`,
  `infoDisplay === 'inline-grid'`.

**Verifikasi:** `node tests/browser/rv5-01-header-audit.mjs` → exit 0 & "ISSUE 0" untuk 37 CRUD.
Baseline regresi yang harus tetap hijau: `crud-suite.mjs` + `phpunit`.

---

## RV5-01-B — Update backlog README

**Status:** [ ] TODO — commit: `______`

**File:** `docs/review-version-5/README.md`.

**Yang dikerjakan:** di blok RV5-01, flip sub-checklist yang sudah terverifikasi:
- [x] Audit SEMUA halaman list admin lain → **37 CRUD PASS**, bukti report.json + screenshots.
- [x] Halaman banyak tombol `top` (salary) → rapi.
- [x] Halaman TANPA tombol create (audit-log) → rapi.
- [x] Dark mode + mobile (<992px) → sampel 6 halaman PASS.
- Tambah catatan: 3 halaman custom (`training`/`notification`/`employee-document`) di luar pola,
  lihat RV5-01-C.

**Verifikasi:** README mencerminkan hasil audit; tidak ada klaim tanpa bukti.

---

## RV5-01-C — Seragamkan header 3 halaman non-CRUD (DESIGN APPROVED)

**Keputusan Capt:** SERAGAMKAN ketiga halaman non-CRUD ke pola header 2-baris.

**Referensi desain:** `docs/review-version-5/mockups/rv5-01c-header-noncrud.html`
(preview: `mockups/preview-rv5-01c-{training,notif,doc}.png`). Design disetujui Capt.

**Desain yang disepakati:**
- Header 2-baris identik dengan pola CRUD (`resources/views/vendor/backpack/crud/list.blade.php`):
  - **Baris 1:** breadcrumb (kiri) + tombol aksi utama (kanan, `.um-header-actions`).
  - **Baris 2:** judul + subjudul (kiri, `.um-header-title`) + tools/tab/filter (kanan, `.um-header-tools`).
- Reuse token CSS `.um-*` yang SUDAH ADA di `resources/css/admin.css` (tak perlu CSS baru).
- Tombol/tab/filter dipindah dari dalam card ke slot header; card cuma berisi tabel/list.
- Sekalian buang `btn-sm` di ketiga blade (overlap dengan RV5-02).

**Pemetaan per halaman:**
| Halaman | Baris 1 kanan (actions) | Baris 2 kanan (tools) |
|---------|-------------------------|------------------------|
| Pelatihan (`training/index`) | Buat Pelatihan | tab Aktif / Diarsipkan |
| Notifikasi (`notification/index`) | Tandai Semua Dibaca | tab Semua / Belum Dibaca |
| Dokumen (`document/index`) | Kelengkapan + Unggah Dokumen | form filter (Karyawan/Jenis/checkbox/Filter) |

---

### RV5-01-C1 — Partial header reusable

**Status:** [x] DONE (uncommitted, terverifikasi) — komponen `<x-admin.page-header>` render OK

**File (BARU):** `resources/views/admin/partials/page-header.blade.php`

**Yang dikerjakan:** partial yang mirror struktur `.um-page-header` dari list.blade.php,
menerima variabel/slot:
- `$breadcrumb` (array `label => url|false`) — render `<ol.breadcrumb>` di baris 1 kiri.
- `$heading` (string) + `$subheading` (string|null) — baris 2 kiri.
- section `actions` (opsional) — baris 1 kanan (`.um-header-actions`).
- section `tools` (opsional) — baris 2 kanan (`.um-header-tools`).

Implementasi via komponen anonim Blade (`<x-...>`) ATAU `@include` dengan `@section`/`$slot`.
Pilih `@include` + variabel + `$__env->yieldContent` untuk 2 slot, atau lebih bersih:
komponen anonim `resources/views/components/admin/page-header.blade.php` dengan `{{ $actions ?? '' }}`
& `{{ $tools ?? '' }}` named slots + `{{ $slot }}` tak dipakai.
**Rekomendasi:** komponen anonim `<x-admin.page-header>` — named slots paling rapi untuk 2 area.

**Verifikasi:** render di salah satu halaman → struktur DOM sama dengan CRUD (`.um-header-top`,
`.um-header-actions`, `.um-header-title`, `.um-header-tools` hadir). `php artisan view:clear`.

---

### RV5-01-C2 — Pelatihan

**Status:** [x] DONE (uncommitted, terverifikasi) — browser: bc+actions+tab, 0 overflow, 0 btn-sm

**File:** `resources/views/admin/training/index.blade.php`

**Yang dikerjakan:**
- Ganti `@section('header')` (yang sekarang cuma `<h2>`) → pakai `<x-admin.page-header>`:
  breadcrumb `Admin / Pelatihan`, heading "Pelatihan", subheading "kelola materi & latihan untuk karyawan".
- Slot `actions`: tombol "Buat Pelatihan" (`btn btn-success`, HAPUS `btn-sm`), gated `@if($canEdit && !$showArchived)`.
- Slot `tools`: btn-group tab Aktif/Diarsipkan (HAPUS `btn-sm`).
- Hapus card `<div class="card mb-3">` berisi tab+tombol lama dari `@section('content')`; sisakan card tabel saja.

**Cek per file:**
- [ ] `@section('header')` pakai `<x-admin.page-header>`.
- [ ] Tombol "Buat Pelatihan" pindah ke slot actions, tanpa `btn-sm`.
- [ ] Tab Aktif/Diarsipkan pindah ke slot tools, tanpa `btn-sm`.
- [ ] Card tab lama dihapus dari content.
- [ ] `btn-outline-primary btn-sm` pada tombol "Kelola" per-baris → boleh tetap kecil? (aksi tabel;
      putuskan saat RV5-02 — di task ini biarkan, catat).

**Verifikasi:** browser `/admin/training` → header 2-baris, tab & tombol di header, tabel utuh,
tak ada overflow/overlap. Empty-state tetap muncul.

---

### RV5-01-C3 — Notifikasi

**Status:** [x] DONE (uncommitted, terverifikasi) — browser: bc+tab, 0 overflow, 0 btn-sm

**File:** `resources/views/admin/notification/index.blade.php`

**Yang dikerjakan:**
- `@section('header')` → `<x-admin.page-header>`: breadcrumb `Admin / Notifikasi`,
  heading "Notifikasi", subheading "{{ $unreadCount }} belum dibaca".
- Slot `actions`: tombol "Tandai Semua Dibaca" (form POST, HAPUS `btn-sm`), gated `@if($unreadCount>0)`.
- Slot `tools`: btn-group tab Semua / Belum Dibaca (HAPUS `btn-sm`).
- Hapus `<div class="card-header ...">` berisi tab+tombol; card body list tetap.

**Cek per file:**
- [ ] Header pakai komponen; tab & tombol di header, bukan card-header.
- [ ] Tombol "Tandai Semua Dibaca" tanpa `btn-sm`, form POST + `@csrf` tetap utuh.
- [ ] Pagination `{{ $notifications->links() }}` tetap.

**Verifikasi:** browser `/admin/notification` → header 2-baris, aksi & tab di header,
daftar notifikasi + badge "Baru" utuh, mark-all-read masih berfungsi (klik → berkurang).

---

### RV5-01-C4 — Dokumen Karyawan

**Status:** [x] DONE (uncommitted, terverifikasi) — browser: bc+2 tombol+filter, 0 overflow, 0 -sm

**File:** `resources/views/admin/document/index.blade.php`

**Yang dikerjakan:**
- `@section('header')` → `<x-admin.page-header>`: breadcrumb `Admin / Dokumen Karyawan`,
  heading "Dokumen Karyawan", subheading "Arsip berkas & masa berlaku dokumen".
- Slot `actions`: "Kelengkapan" (`btn-outline-secondary`) + "Unggah Dokumen" (`btn-primary`), HAPUS `btn-sm`.
- Slot `tools`: form GET filter (Karyawan/Jenis select + 2 checkbox + Filter) — flat (tanpa card abu),
  `form-control-sm` → `form-select` default, HAPUS semua `-sm`.
- Hapus `<div class="card-header">` berisi filter+tombol; card body tabel tetap.

**Cek per file:**
- [ ] Filter form pindah ke slot tools, layout flat rapi (checkbox stack), submit GET tetap jalan.
- [ ] `form-control-sm` → `form-select`; `btn-sm` dibuang di tombol filter/aksi.
- [ ] Tombol download/hapus per-baris (btn-link btn-sm) — catat utk RV5-02, biarkan di task ini.
- [ ] Pagination `{{ $documents->links() }}` tetap.

**Verifikasi:** browser `/admin/employee-document` → header 2-baris, filter di kanan baris 2,
2 tombol di kanan baris 1, tabel utuh, filter Karyawan/Jenis submit & mempersempit hasil.

---

### RV5-01-C5 — Regression guard 3 halaman non-CRUD

**Status:** [x] DONE (uncommitted, terverifikasi) — `rv5-01c-noncrud-header.mjs` exit 0

**File (BARU):** `tests/browser/rv5-01c-noncrud-header.mjs`

**Yang dikerjakan:** browser test yang login super_admin, buka `/admin/{training,notification,employee-document}`,
assert TERUKUR:
- `.um-header-top` & `.um-header-title` hadir (struktur pola 2-baris),
- breadcrumb ada di baris 1,
- tombol aksi ada di `.um-header-actions` (kanan baris 1),
- `!horizOverflow` & tak ada overlap actions×tools,
- ZERO `btn-sm`/`form-control-sm` di dalam `.um-page-header`.

**Verifikasi:** `node tests/browser/rv5-01c-noncrud-header.mjs` exit 0.
Baseline yang harus tetap hijau: `crud-suite.mjs` + `phpunit`.

---

## Urutan eksekusi RV5-01 (usul)
1. **RV5-01-C1** (partial) — fondasi, dipakai C2–C4.
2. **RV5-01-C2** Pelatihan → test → commit.
3. **RV5-01-C3** Notifikasi → test → commit.
4. **RV5-01-C4** Dokumen → test → commit.
5. **RV5-01-C5** regression guard non-CRUD.
6. **RV5-01-A** jadikan audit CRUD sebagai guard permanen.
7. **RV5-01-B** update README backlog → flip DONE.

Tiap task: edit → `php artisan view:clear` → browser verify → `phpunit` + `crud-suite.mjs` tetap hijau → flag DONE → (opsi) lapor TG.
