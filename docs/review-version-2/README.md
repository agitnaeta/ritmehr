# Review Teknis RitmeHR — Versi 2 (Utang Teknis & Modernisasi)

> Tanggal: 2026-08-27 · Penyusun: Hermes Agent · Build/commit: `953cfed`
> Fokus: **kesehatan teknis** (framework, dependency, automation test, CI) — bukan UX.
> Metode: audit kode nyata (`composer why-not`, struktur app, hitung test suite), bukan asumsi.

## Ringkasan Eksekutif

RitmeHR **sehat secara fungsional** (403 test PHPUnit + 146 browser hijau di kondisi
terakhir), tapi menua secara teknis: **Laravel 10.39** (support berakhir), **Backpack
CRUD 6.5**, **PHPUnit 10**, tanpa **CI**. Risiko terbesar bukan bug — melainkan
**ketertinggalan versi** yang makin mahal ditunda dan **automation test yang akan usang**
begitu framework/Backpack naik.

**Critical path terverifikasi:** yang mengunci Laravel 12 adalah **Backpack CRUD 6.5**
(`^L10`), bukan Laravel-nya. Jalur wajib: Backpack 6→7 dulu (selagi L10), baru L10→11→12.

**Kekuatan terbesar:** cakupan test tinggi jadi jaring pengaman upgrade.
**Masalah terbesar:** tanpa CI, tiap upgrade rawan regres diam-diam; dan browser suite
akan merah massal saat DOM Backpack 7 berubah.

## Kondisi saat ini vs target

| Komponen | Sekarang | Target | Blocker |
|---|---|---|---|
| Laravel | 10.39 | 12.x | Backpack 6 (`^L10`) |
| PHP | 8.2.25 | 8.2 min → 8.3 | — |
| Backpack CRUD | 6.5 | 7.x | **critical path** |
| PHPUnit | 10.5 | 11.x | `@dataProvider` docblock |
| CI/CD | **tidak ada** | GitHub Actions | — |
| LICENSE | **tidak ada** | MIT file | — |

## Hasil audit (bukti)

| Pemeriksaan | Hasil |
|---|---|
| `composer why-not laravel/framework 12.0` | Backpack CRUD 6.5 = `^10.0` → penahan utama |
| Ketersediaan Backpack 7 | 7.1.15 rilis (v8-dev ada) |
| `nunomaduro/collision` 7 | *conflict* dgn L11+ → wajib naik 8 |
| Test PHPUnit | 55 file, PHPUnit murni, 403 lulus (baseline) |
| Browser suite | 37 `.mjs` + `lib.mjs` shared |
| CRUD controller | 37 (pakai Backpack fluent API) |
| Custom view Backpack | 20 file `vendor/backpack/**` (risiko theme-tabler 2) |
| `@dataProvider` di test | 2 file (perlu konversi atribut PHPUnit 11) |
| CI workflow | tidak ada `.github/workflows` |

## Scorecard Kesehatan Teknis

| # | Lensa teknis | Verdict | Inti temuan | Bukti |
|---|---|---|---|---|
| 1 | Framework mutakhir | ❌ Bermasalah | Laravel 10 (EOL mendekat), 3 mayor di belakang | `php artisan --version` |
| 2 | Dependency sehat | ⚠️ Perlu perbaikan | 19 paket direct outdated, beberapa mayor | `composer outdated --direct` |
| 3 | Test relevan & tahan upgrade | ⚠️ Perlu perbaikan | Hijau sekarang, tapi selector browser rapuh thd Backpack 7 | 37 suite, 20 custom view |
| 4 | CI/CD guardrail | ❌ Bermasalah | Tak ada CI — regres tak terdeteksi otomatis | tak ada workflow |
| 5 | Kesiapan rilis/legal | ⚠️ Perlu perbaikan | `composer.json` MIT tapi tak ada file LICENSE | README §Lisensi |
| 6 | Konsistensi environment | 🟢 Rendah | `.env.example` DB_PORT=3306 ≠ docker 3307 | `.env.example` |

## Estimasi (jawaban pertanyaan Capt)

### 1. Upgrade Laravel ke terbaru — **~8–13 hari kerja**

| Fase | Pekerjaan | Estimasi |
|---|---|---|
| F0 | Prep & baseline test hijau | 0.5–1 hr |
| **F1** | **Backpack 6→7 (di L10)** — critical path | 3–5 hr |
| F2 | Laravel 10→11 (Sanctum 4, dompdf 3, guzzle 8, collision 8) | 2–3 hr |
| F3 | Laravel 11→12 (low-friction) | 1–2 hr |
| F4 | Regresi penuh + rilis | 1–2 hr |

### 2. Merapikan automation test — **~6–10 hari kerja**

| Fase | Pekerjaan | Estimasi |
|---|---|---|
| T1 | PHPUnit 10→11: `@dataProvider` → `#[DataProvider]`, schema xml | 2–3 hr |
| **T2** | Realign 37 browser suite (DOM Backpack 7 + tabler 2) — risiko terbesar | 3–5 hr |
| T3 | CI GitHub Actions (matrix PHP 8.2/8.3, phpunit tiap PR) | 1–2 hr |

### Total realistis
Test = jaring pengaman upgrade → dikerjakan **beriringan**:
- **1 developer fokus:** ~3–4 minggu kalender
- **2 developer** (1 Backpack/Laravel, 1 test+CI): **~2 minggu kalender**

## Urutan eksekusi

```
F0 baseline hijau
 └─► F1 Backpack 6→7 (L10) ──┬─► T2 realign browser suite
                             └─► T1 PHPUnit 10→11
      └─► F2 Laravel 10→11 ──► verifikasi test
           └─► F3 Laravel 11→12 ──► verifikasi test
                └─► T3 CI guardrail
                     └─► F4 regresi + rilis
```

## Tautan
- [Masukan teknis](masukan-teknis.md) — temuan utang teknis + severity + lokasi
- [Rencana perbaikan](rencana-perbaikan.md) — detail fase, paket, risiko, keputusan
- [tasks/](tasks/) — 8 task ber-checklist DONE (F0–F4 upgrade, T1–T3 test)

## Keputusan yang perlu Capt ambil dulu
1. **Skeleton L11 slim** — migrasi sekarang atau tunda? (rekomendasi: tunda, iterasi terpisah)
2. **PHP 8.3 sekalian?** (rekomendasi: ya, bila produksi mendukung)
3. **Backpack FREE atau PRO?** (rekomendasi: tetap FREE — `HasSimpleFilters` sudah cukup)
