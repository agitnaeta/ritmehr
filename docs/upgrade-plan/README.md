# Rencana Upgrade — Laravel & Test Automation (RitmeHR)

> Disusun: 2026-08-27 · Basis: audit kode nyata (composer, struktur app, test suite)
> Pertanyaan yang dijawab: (1) berapa lama upgrade Laravel ke versi terbaru & bagaimana caranya, (2) berapa lama merapikan automation test agar tetap relevan.

## Ringkasan Eksekutif

| | Sekarang | Target |
|---|---|---|
| Laravel | **10.39** | **12.x** (terbaru) |
| PHP | 8.2.25 | 8.2 (min) → 8.3 (disarankan) |
| Backpack CRUD | **6.5** | **7.x** |
| PHPUnit | 10.5 | 11.x |

**Critical path bukan Laravel-nya, tapi Backpack.** Backpack 6 mendukung Laravel 9/10/11 tapi **tidak** Laravel 12. Untuk sampai Laravel 12 kita **wajib** upgrade Backpack 6→7 lebih dulu. Laravel 11→12 sendiri low-friction; yang berat adalah Backpack 7 (37 controller + 20 custom view kena API/DOM baru).

**Strategi:** naikkan Backpack 6→7 **sambil masih di Laravel 10** (Backpack 7 mendukung L10), supaya breaking change Backpack terisolasi dan test masih bisa jadi jaring pengaman. Baru setelah itu Laravel 10→11→12.

## Estimasi (1 developer fokus)

### Q1 — Upgrade Laravel ke terbaru

| Fase | Pekerjaan | Estimasi |
|---|---|---|
| **F0** | Prep & jaring pengaman (branch, baseline test hijau, CI snapshot) | 0.5–1 hari |
| **F1** | **Backpack 6→7** di Laravel 10 *(critical path)* | 3–5 hari |
| **F2** | Laravel 10→11 (framework, sanctum 3→4, deprecations) | 2–3 hari |
| **F3** | Laravel 11→12 (low-friction, dep bumps) | 1–2 hari |
| **F4** | Regresi penuh + hardening (403 phpunit + 37 browser + smoke manual) | 1–2 hari |
| | **Total Q1** | **~8–13 hari kerja** (≈ 2–3 minggu kalender + review) |

### Q2 — Rapikan automation test

| Fase | Pekerjaan | Estimasi |
|---|---|---|
| **T1** | PHPUnit 10→11: docblock metadata → atribut PHP8 (`#[Test]`, `#[DataProvider]`), fix assertion usang, schema `phpunit.xml` | 2–3 hari |
| **T2** | **Browser suite (Playwright) realign** — Backpack 7 + theme-tabler 2 mengubah DOM; perbaiki selector di 37 `.mjs` + `lib.mjs` *(risiko test terbesar)* | 3–5 hari |
| **T3** | CI guardrail — GitHub Actions matrix (PHP 8.2/8.3) jalankan phpunit + browser tiap PR | 1–2 hari |
| | **Total Q2** | **~6–10 hari kerja** |

### Total gabungan

Test **adalah** jaring pengaman upgrade, jadi Q1 & Q2 **berjalan beriringan** (tiap fase upgrade langsung diverifikasi test yang sudah dirapikan). Realistis end-to-end:

- **1 developer fokus:** ~3–4 minggu kalender
- **2 developer** (1 Backpack/Laravel, 1 test harness + CI): **~2 minggu kalender**

> Angka ini mengasumsikan tidak ada dependency yang benar-benar mentok (lihat Risiko). Tambahkan buffer 20% untuk paket pihak ketiga yang lambat rilis kompatibilitas L12.

## Urutan eksekusi (sequencing)

```
F0 baseline hijau
   └─► F1 Backpack 6→7 (L10)  ─┬─► T2 realign browser suite (DOM Backpack 7)
                               └─► T1 PHPUnit 10→11
        └─► F2 Laravel 10→11 ──► verifikasi ulang test
             └─► F3 Laravel 11→12 ──► verifikasi ulang test
                  └─► T3 CI guardrail (kunci supaya tak regres)
                       └─► F4 regresi penuh + rilis
```

Detail per fase (dengan checklist DONE) ada di folder [`tasks/`](tasks/):

| ✔ | Fase | File |
|---|---|---|
| [ ] | F0 Prep & baseline | [F0-prep.md](tasks/F0-prep.md) |
| [ ] | F1 Backpack 6→7 | [F1-backpack-7.md](tasks/F1-backpack-7.md) |
| [ ] | F2 Laravel 10→11 | [F2-laravel-11.md](tasks/F2-laravel-11.md) |
| [ ] | F3 Laravel 11→12 | [F3-laravel-12.md](tasks/F3-laravel-12.md) |
| [ ] | F4 Regresi & rilis | [F4-regression.md](tasks/F4-regression.md) |
| [ ] | T1 PHPUnit 10→11 | [T1-phpunit-11.md](tasks/T1-phpunit-11.md) |
| [ ] | T2 Browser suite realign | [T2-browser-realign.md](tasks/T2-browser-realign.md) |
| [ ] | T3 CI guardrail | [T3-ci-guardrail.md](tasks/T3-ci-guardrail.md) |

## Paket yang butuh naik versi mayor

| Paket | Dari | Ke | Catatan |
|---|---|---|---|
| backpack/crud | 6.5 | 7.x | **Breaking** — critical path |
| backpack/theme-tabler | 1.2 | 2.x | Ubah DOM/CSS → dampak ke browser test |
| backpack/basset | 1.2 | 2.x | Asset loader |
| laravel/framework | 10.39 | 12.x | Lewat 11 dulu |
| laravel/sanctum | 3.3 | 4.x | Breaking (config + migration) |
| laravel/tinker | 2.8 | 3.x | |
| barryvdh/laravel-dompdf | 2.0 | 3.x | Cek render slip gaji/kartu ID |
| guzzlehttp/guzzle | 7.8 | 8.x | Cek integrasi WAHA/Fonnte |
| spatie/laravel-backup | 8.5 | 9.x | |
| nunomaduro/collision | 7 | 8.x | dev — konflik dgn L11+ (wajib naik) |
| phpunit/phpunit | 10.5 | 11.x | Lihat T1 |

**Dep transitif Backpack** (naik otomatis saat Backpack 6→7, verifikasi versi):
`prologue/alerts` 1.1→1.4, `creativeorange/gravatar` 1.0.23→1.0.26, `opcodesio/log-viewer`, `laravel/prompts`. Sudah dikonfirmasi punya rilis yang mendukung L11/12.

> **Verifikasi (2026-08-27):** `composer why-not laravel/framework 12.0` mengkonfirmasi Backpack CRUD 6.5 terkunci `^10.0` = critical path. Backpack 7.1.15 sudah rilis (bahkan v8-dev). Collision 7 *conflict* dengan L11+ → wajib naik ke 8 di F2.

## Risiko & mitigasi

| Risiko | Dampak | Mitigasi |
|---|---|---|
| Backpack 7 mengubah signature field/column/operation | 37 controller perlu penyesuaian | Kerjakan di L10 dulu, per-modul, test tiap modul |
| theme-tabler 2 ubah struktur DOM (`#crudTable`, kelas) | 37 browser suite bisa merah massal | T2 khusus realign selector + `lib.mjs` |
| dompdf 2→3 ubah output PDF | Slip gaji/kartu ID rusak | Smoke test cetak PDF di F4 |
| Sanctum 3→4 butuh migration | API token | Ikuti upgrade guide, jalankan migration di staging |
| Paket pihak ketiga lambat support L12 | Blokir F3 | Cek `composer why-not laravel/framework 12` sebelum mulai; pin sementara bila perlu |
| `artisan test` OOM (isu lama repo ini) | Test tak jalan | Tetap pakai `phpunit` langsung dgn flag memory (sudah didokumentasikan) |

## Keputusan yang perlu Capt ambil dulu

1. **Skeleton L11 slim (bootstrap/app.php) — migrasi sekarang atau nanti?**
   L11 tetap jalan dengan skeleton lama (Kernel.php dll). Menunda migrasi slim = risiko lebih rendah, tapi utang teknis. Rekomendasi: **tunda** ke iterasi terpisah setelah upgrade stabil.
2. **PHP 8.2 atau naik 8.3 sekalian?** L12 jalan di 8.2, tapi 8.3 lebih future-proof. Rekomendasi: **8.3** bila environment produksi mendukung.
3. **Target Backpack: PRO atau tetap FREE?** Saat ini FREE (pakai `HasSimpleFilters` sbg pengganti filter berbayar). Backpack 7 FREE tetap cukup. Rekomendasi: **tetap FREE**.
