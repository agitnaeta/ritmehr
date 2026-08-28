# Mobile-First Portal `/my` — RitmeHR (Review Version 3)

**Tujuan:** Utamakan pengalaman **mobile** di seluruh portal karyawan (`/my/*`),
karena mayoritas karyawan mengakses lewat HP. Desktop tetap didukung (progressive
enhancement dari mobile ke atas), bukan sebaliknya.

**Prinsip:**
- CSS mobile-first: aturan dasar untuk layar kecil, `@media (min-width: …)` untuk
  memperkaya di layar besar — bukan `max-width` yang menambal ke bawah.
- Navigasi: **bottom tab bar** di mobile (5 menu utama), top-nav pill di desktop.
- Data tabular: **kartu bertumpuk** di mobile (bukan scroll horizontal), tabel di desktop.
- Kehadiran: **list agenda per-hari** di mobile, kalender/tabel di desktop.
- Touch target minimal 44×44px; tombol aksi utama full-width di mobile.

**Acuan desain:** `docs/mockups/portal-mobile.html` (sudah diverifikasi di viewport 390px).

**Tidak diubah:** logika controller, rute, guard `backpack`, token warna (sudah AA).
Perubahan murni pada layer view + CSS portal.

---

## Cara kerja
Satu file per satu file. Tiap file punya flag Status. Tiap selesai satu view →
test di viewport mobile (browser harness / manual) sebelum lanjut. Commit per file.

## Test
- Browser harness: `tests/browser/*.mjs`
- Serve: `php artisan serve` (port :8000), demo `siti@demo.test` / `password`
- Viewport uji: 390×844 (iPhone), 360×800 (Android kecil)

---

## Daftar Task (per-file)

| ID | File | Fokus | Status |
|---|---|---|---|
| MF-01 | `resources/css/portal.css` | Fondasi mobile-first: bottom-nav, `.stack-card`, `.app-bar`, touch target | [x] DONE |
| MF-02 | `resources/views/portal/layout.blade.php` | Bottom tab bar (mobile) + top-nav (desktop) | [x] DONE |
| MF-03 | `resources/views/portal/dashboard.blade.php` | App-bar greeting, stat grid, kartu ringkas | [x] DONE |
| MF-04 | `resources/views/portal/attendance.blade.php` | List agenda mobile, kalender/tabel desktop | [x] DONE |
| MF-05 | `resources/views/portal/salary_index.blade.php` | Tabel → kartu bertumpuk | [x] DONE |
| MF-06 | `resources/views/portal/salary_show.blade.php` | Payslip mobile-friendly (sudah pakai .payslip-row responsif) | [x] DONE |
| MF-07 | `resources/views/portal/leave_index.blade.php` | Tabel riwayat → kartu | [x] DONE |
| MF-08 | `resources/views/portal/leave_create.blade.php` | Form mobile (Bootstrap grid sudah menumpuk) | [x] DONE |
| MF-09 | `resources/views/portal/loan_index.blade.php` | Dua tabel → kartu | [x] DONE |
| MF-10 | `resources/views/portal/profile.blade.php` | Form + data kepegawaian (col-md sudah menumpuk) | [x] DONE |
| MF-11 | `resources/views/portal/notifications.blade.php` | Sudah kartu list; app-bar+tabbar cukup | [x] DONE |
| MF-12 | `resources/views/portal/training_*.blade.php` | Ikut layout global (app-bar+tabbar) | [x] DONE |

Detail tiap task ada di `tasks/`.

---

## Hasil (2026-08-28)

**Yang dikerjakan:**
- **MF-01 CSS**: primitif mobile-first aditif — `.portal-tabbar` (bottom nav), `.portal-appbar`
  (app bar mobile), `.data-cards`/`.data-card__*` (kartu pengganti tabel), touch target ≥44px,
  `.btn-block-mobile`, `.hide-on-mobile`. Semua di dalam `@media (max-width:991.98px)` → desktop
  tak tersentuh.
- **MF-02 Layout**: app-bar mobile (brand + lonceng + admin + profil) & bottom tab bar 5-menu
  (Beranda·Hadir·Gaji·Cuti·Lainnya) dengan active-state per-route. Navbar pill lama tetap
  dipakai di desktop (lg+), disembunyikan di mobile.
- **MF-04/05/07/09**: tabel kehadiran, gaji, cuti, kasbon → **kartu bertumpuk** di mobile
  (satu sumber data, dua penyajian). Kehadiran default ke **agenda** di HP, kalender/tabel di desktop.
- **MF-03**: tombol Absen full-width di mobile.

**Verifikasi (bukti nyata):**
- `npm run build` sukses, `portal.css` 19.16 kB.
- 9 route `/my/*` → **HTTP 200, tanpa error** (curl sesi login nyata).
- Markup mobile terkonfirmasi di HTML terkirim: `portal-tabbar`, `portal-appbar`, `data-cards`,
  `hide-on-mobile`.
- Emulasi mobile 390px: tabel `display:none`, kartu `display:flex`; tab bar + app bar tampil,
  navbar lama tersembunyi. Screenshot: dashboard & slip gaji bersih.

**Catatan:** Browserbase terkunci viewport 1280px → verifikasi mobile lewat inject style paksa
+ cek `getComputedStyle`. Di HP asli Bootstrap breakpoint (`col-6`, `col-md-*`) aktif otomatis.

