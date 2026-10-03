# RV5-02 — Audit: Deprecate varian ukuran `-sm` (GLOBAL)

**Tujuan:** hapus semua `.btn-sm`, `.btn-group-sm`, `.form-control-sm`, `.form-select-sm`,
`.input-group-sm` — semua kontrol pakai ukuran default (medium). Konsistensi tap target.

**Skala terukur (`search_files` di `resources/`):** **163 pemakaian di 46 file**
(turun dari 174 — 3 blade non-CRUD sudah dipangkas sebagian saat RV5-01).

**Temuan kunci: INI BUKAN SWEEP MEKANIS MURNI.** Ada coupling CSS↔blade yang WAJIB
ditangani bareng, kalau tidak filter bar & beberapa kontrol akan rusak/melebar.

---

## ⚠️ Risiko & coupling (baca sebelum eksekusi)

### R1 — Filter bar CRUD: CSS di-key ke `-sm` (JALUR KRITIS)
`resources/views/vendor/backpack/crud/buttons/simple_filters.blade.php` merender form
filter dengan `.form-select-sm` / `.form-control-sm` / `.btn-sm`. `admin.css` punya rule
yang **bergantung pada class itu**:
- `form:has(> div > .form-select-sm):has(.la-filter)` (baris 337) → **DETEKTOR** form filter
  untuk gaya bar bernada. Kalau `-sm` hilang, selector tak match.
- `form:has(.la-filter) .form-select-sm, ...form-control-sm` (baris 351) → **batas lebar**
  (min 7.5rem / max 11rem). Kalau hilang → select filter **melebar** tak terkendali.
- `form:has(.la-filter) .btn-sm` (baris 399) → min-height 2rem tombol Filter/Reset.

**→ Menghapus `-sm` di `simple_filters.blade.php` HARUS dibarengi migrasi rule CSS**
(ganti selector `.form-select-sm`→`.form-select` yang di-scope dalam `form:has(.la-filter)`,
supaya intent sizing tetap hidup tanpa class). Ini inti struktural RV5-02.
Filter ini muncul di SEMUA 37 halaman CRUD → regresi lintas-modul kalau salah.

### R2 — Tombol aksi per-baris tabel (tinggi baris)
Tombol Lihat/Ubah/Hapus/Print/Download per-baris (`btn-sm btn-link`, dll) di banyak view.
Menghapus `-sm` **menaikkan tinggi baris tabel**. README RV5-02 sudah mewanti: sesuaikan
via padding/gap, JANGAN kembalikan `-sm`. Perlu verifikasi visual tinggi baris tiap tabel.

### R3 — Portal mobile tap-target (AMAN, tapi ada dead rule)
`portal.css` baris 633: `.portal-mobile .btn-sm { min-height: 38px }`. Kalau `-sm` dihapus
dari blade portal, rule `.portal-mobile .btn { min-height: 44px }` (baris 632) yang berlaku
→ tap target JUSTRU lebih besar (bagus utk mobile). Aman. Cukup **hapus rule dead** baris 633.

### R4 — Careers = situs publik terpisah (pertanyaan scope)
`career/*` (3 file, 6 hit) pakai layout `career/layout.blade.php`, situs lowongan publik —
BUKAN admin. Perlu keputusan: masuk scope "konsistensi kontrol admin" atau di luar?

---

## Inventory per kategori (work-stream)

### WS-A — CSS core (3 file, 11 hit) — KERJAKAN BARENG simple_filters
| File | Hit | Aksi |
|------|-----|------|
| `resources/css/admin.css` | 7 | Migrasi selector `-sm`→default di-scope `.la-filter`; sesuaikan `.form-control-sm/.form-select-sm` block |
| `resources/css/portal.css` | 3 | Hapus dead rule `.portal-mobile .btn-sm`; cek `.form-control-sm/.form-select-sm` block (457-458) |
| `resources/css/base.css` | 1 | Cek rule `.btn-sm` (baris 30) — migrasi/hapus |

### WS-B — Backpack vendor buttons (8 file, 14 hit)
| File | Hit | Catatan |
|------|-----|---------|
| `.../buttons/simple_filters.blade.php` | 4 | **JALUR KRITIS** — barengi WS-A |
| `.../buttons/generate_balances.blade.php` | 4 | tombol toolbar |
| `.../buttons/print_salary.blade.php` | 1 | tombol toolbar |
| `.../buttons/recalculate_salary.blade.php` | 1 | tombol toolbar |
| `.../buttons/set_payment_cash.blade.php` | 1 | tombol aksi baris (R2) |
| `.../buttons/set_payment_transfer.blade.php` | 1 | tombol aksi baris (R2) |
| `.../buttons/user-print.blade.php` | 1 | tombol aksi baris (R2) |
| `.../buttons/approval_actions.blade.php` | 1 | tombol aksi baris (R2) |

### WS-C — Admin custom views (~24 file, ~112 hit)
| File | Hit |
|------|-----|
| `admin/training/manage.blade.php` | 18 |
| `admin/recruitment/pipeline.blade.php` | 17 |
| `admin/dashboard.blade.php` | 7 |
| `admin/recruitment/partials/detail-drawer.blade.php` | 6 |
| `admin/leave/calendar.blade.php` | 5 |
| `admin/recruitment/calendar.blade.php` | 5 |
| `admin/recruitment/ranking.blade.php` | 5 |
| `admin/accounting/journal_form.blade.php` | 5 |
| `admin/report/attendance.blade.php` | 4 |
| `admin/performance/index.blade.php` | 4 |
| `admin/accounting/journal.blade.php` | 3 |
| `admin/department/org_chart.blade.php` | 3 |
| `admin/leave/report.blade.php` | 3 |
| `admin/tax/bpjs_report.blade.php` | 3 |
| `admin/salary/show.blade.php` | 3 |
| `admin/user/show.blade.php` | 3 |
| `admin/presence/approvals.blade.php` | 3 |
| `admin/report/salary.blade.php` | 3 |
| `admin/settings/index.blade.php` | 2 |
| `admin/performance/review.blade.php` | 2 |
| `admin/tax/annual_report.blade.php` | 2 |
| `admin/presence/show.blade.php` | 2 |
| `admin/document/index.blade.php` | 2 | *(sisa tombol per-baris dari RV5-01 C4)* |
| `admin/performance/scoreboard.blade.php` | 1 |
| `admin/training/index.blade.php` | 1 | *(sisa tombol "Kelola" per-baris dari RV5-01 C2)* |
| `loan/recap.blade.php` | 1 |

### WS-D — Portal karyawan (7 file, 19 hit) — mobile-first (R3)
| File | Hit |
|------|-----|
| `portal/attendance.blade.php` | 5 |
| `portal/dashboard.blade.php` | 3 |
| `portal/leave_index.blade.php` | 3 |
| `portal/training_show.blade.php` | 3 |
| `portal/notifications.blade.php` | 2 |
| `portal/salary_index.blade.php` | 2 |
| `portal/training_index.blade.php` | 1 |

### WS-E — Careers publik (3 file, 6 hit) — SCOPE? (R4)
| File | Hit |
|------|-----|
| `career/show.blade.php` | 4 |
| `career/index.blade.php` | 1 |
| `career/dashboard.blade.php` | 1 |

---

## Keputusan scope (Capt — TERKUNCI)

1. **Tombol aksi per-baris tabel (R2)** → ✅ **IKUT dihapus** `-sm`. Baris tabel jadi lebih
   tinggi; sesuaikan via padding/gap (JANGAN kembalikan `-sm`). Verifikasi tinggi baris tiap tabel.
2. **Careers publik (WS-E)** → ✅ **MASUK scope**, seragamkan juga.
3. **Portal (WS-D)** → ✅ hapus `-sm` (tap target jadi 44px, lebih besar — cocok mobile-first).

**Artinya: SEMUA 46 file masuk scope. Tidak ada pengecualian.**

---

## Usulan urutan eksekusi
1. **WS-A + simple_filters (WS-B kritis)** — migrasi CSS + filter blade BARENG, verifikasi
   filter bar di ≥3 halaman CRUD (banyak filter / sedikit / tanpa) + dark mode. Guard regresi.
2. **WS-B sisanya** (7 tombol toolbar/aksi) — per file.
3. **WS-C** admin custom views — batch dari terpadat (training/manage 18, pipeline 17).
4. **WS-D** portal — hapus `-sm` + hapus dead rule portal.css.
5. **WS-E** careers — hanya jika masuk scope.
6. **Guard akhir:** `tests/browser/rv5-02-no-sm.mjs` — assert 0 `-sm` di DOM halaman kunci
   + grep `search_files` 0 hit di `resources/` (kecuali yang sengaja dikecualikan).

Tiap file: edit → `php artisan view:clear` / `npm run build` (CSS) → browser verify tinggi
kontrol & tak ada layout pecah → `phpunit` + `crud-suite.mjs` tetap hijau → flag DONE.

**Baseline regresi yang harus tetap hijau:** `crud-suite.mjs` 146 + guard RV5-01 (A + C5).
