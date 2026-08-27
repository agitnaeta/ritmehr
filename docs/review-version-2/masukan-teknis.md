# Masukan Teknis — RitmeHR Versi 2 (Utang Teknis)

Semua temuan berbasis audit kode nyata (2026-08-27, commit `953cfed`). Severity:
🔴 Kritis · 🟠 Tinggi · 🟡 Sedang · 🟢 Rendah.

| ID | Judul | Severity | Bukti | Lokasi / Perbaikan |
|---|---|---|---|---|
| RV2-001 | Laravel 10 tertinggal 2 mayor dari terbaru (12) | 🟠 Tinggi | `laravel/framework 10.39.0` | Fase F1–F3 |
| RV2-002 | Backpack CRUD perlu dinaikkan 6.5→6.8 agar support L11/L12 | 🟠 Tinggi | `composer why-not` + packagist | Fase F1 (revisi) |
| RV2-003 | Tidak ada CI/CD — regres tak terdeteksi | 🟠 Tinggi | tak ada `.github/workflows` | Fase T3 |
| RV2-004 | Browser test rapuh thd perubahan DOM Backpack 7 | 🟠 Tinggi | 37 suite + 20 custom view | Fase T2 |
| RV2-005 | PHPUnit 10 pakai `@dataProvider` docblock (dihapus di 11) | 🟡 Sedang | 2 file test | Fase T1 |
| RV2-006 | `collision` 7 conflict dgn Laravel 11+ | 🟡 Sedang | `composer why-not` | Fase F2 |
| RV2-007 | Tidak ada file LICENSE (composer.json klaim MIT) | 🟡 Sedang | README §Lisensi | Tambah `LICENSE` |
| RV2-008 | `.env.example` DB_PORT=3306 ≠ docker 3307 | 🟢 Rendah | `.env.example` | Samakan ke 3307 |
| RV2-009 | `artisan test` OOM saat render menu | 🟡 Sedang | catatan repo | Pakai `phpunit` + flag memory (didokumentasikan) |

---

## Detail

### RV2-002 — Backpack CRUD 6.5→6.8 (bukan blocker fatal) 🟠
- **Temuan F0 (revisi rencana):** Backpack CRUD **7.x butuh Laravel ^12** — tidak ada Backpack 7 untuk L10/L11. TAPI **Backpack 6.8.16 mendukung Laravel `^10|^11|^12`**.
- **Implikasi:** TIDAK perlu lompat ke Backpack 7 untuk sampai Laravel 12. Cukup naik Backpack 6.5→6.8 (minor, low-risk) yang jalan di L10/11/12.
- **Dampak:** upgrade jauh lebih murah dari perkiraan awal — critical path Backpack 7 hilang. Backpack 6→7 jadi fase opsional terpisah (butuh doctrine/dbal 4, DOM baru theme-tabler 2).
- **Perbaikan:** F1 revisi — `composer require backpack/crud:^6.8` selagi masih L10, verifikasi 37 controller + 20 view tetap render.
- **Bukti:** packagist p2 — BP 6.8.16 = `^10.0|^11.0|^12`; BP 7.0.31+ = `^12`.

### RV2-001 — Laravel 10 tertinggal 🟠
- **Gejala:** `laravel/framework 10.39.0`; terbaru 12.x.
- **Dampak:** kehilangan patch keamanan & fitur; makin mahal ditunda; rekrutmen dev makin sulit.
- **Perbaikan:** F2 (10→11) lalu F3 (11→12). L11→12 sengaja low-friction.

### RV2-003 — Tidak ada CI/CD 🟠
- **Gejala:** tak ada `.github/workflows`. Test hanya jalan manual di lokal.
- **Dampak:** upgrade besar tanpa gerbang otomatis = regres mudah lolos ke master.
- **Perbaikan:** T3 — GitHub Actions matrix PHP 8.2/8.3, phpunit tiap PR + branch protection.

### RV2-004 — Browser test rapuh thd Backpack 7 🟠
- **Gejala:** 37 suite `.mjs` bergantung selector DOM (`#crudTable tbody tr`, `.dataTables_info`, kelas Tabler). Backpack 7 + theme-tabler 2 mengubah DOM.
- **Dampak:** suite bisa merah massal setelah F1, memberi sinyal palsu.
- **Perbaikan:** T2 — perbaiki `lib.mjs` (shared) lebih dulu, lalu sisir per suite. Terpusatkan selector agar biaya maintenance turun.
- **Coverage gap:** fitur baru (Import Excel IMP, Setup Wizard WIZ) belum punya browser suite — baru diuji handler asli + PHPUnit. Tambahkan saat T2.

### RV2-005 — `@dataProvider` docblock 🟡
- **Gejala:** 2 file test pakai `@dataProvider` (mis. `ReportingDashboardTest`). PHPUnit 11 menghapus metadata docblock.
- **Perbaikan:** T1 — konversi ke atribut `#[DataProvider('...')]`, `#[Test]`. Bisa dibantu Rector.

### RV2-006 — collision 7 conflict L11+ 🟡
- **Gejala:** `nunomaduro/collision v7.10.0 conflicts laravel/framework >=11.0.0`.
- **Perbaikan:** naik ke collision 8 di F2 (dev dependency).

### RV2-007 — Tidak ada LICENSE 🟡
- **Gejala:** `composer.json` menyatakan MIT, tapi tak ada file `LICENSE`. README §Lisensi sudah mencatat TODO ini.
- **Dampak:** ambiguitas legal untuk kontributor/pengguna; buruk untuk repo publik.
- **Perbaikan:** tambah file `LICENSE` (MIT) atau perbarui `composer.json` bila akan tertutup.

### RV2-008 — DB_PORT mismatch 🟢
- **Gejala:** `.env.example` DB_PORT=3306, `docker-compose.yml` map ke 3307. README sudah kasih catatan manual.
- **Perbaikan:** samakan `.env.example` ke 3307, hapus catatan manual di README.

### RV2-009 — `artisan test` OOM 🟡
- **Gejala:** `php artisan test` kehabisan memori saat render menu.
- **Perbaikan (workaround terpakai):** `php -d memory_limit=2G -d xdebug.mode=off vendor/bin/phpunit --no-coverage`. Dokumentasikan di CI (T3) agar tak terulang.
