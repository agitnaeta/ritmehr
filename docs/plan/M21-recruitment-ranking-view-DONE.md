# M21 — Recruitment Ranking View (Urutan 1..N + Alasan Jelas)

> **Status:** ✅ DONE (implemented & tested) · **Dibuat:** 2026-08-26 · **Selesai:** 2026-08-26
> **Basis:** menutup gap nyata M17/M18 — AI **menghitung** skor tapi UI **tidak
> mengurutkan** pelamar by skor.
> **Pemicu:** review Capt — *"misal lowongan QA, kita bisa lihat sorting berapa yang
> masuk dan urutan 1..N nya jelas alasannya kenapa."*

---

## 0. Ringkasan Implementasi (apa yang benar-benar dibangun)

| Fase | Isi | Test |
|---|---|---|
| **M21-1** | `MatchingService::rankedApplicants()/rankMap()/rankingStats()` — urut ai_score→vector→tanggal, NULL di bawah, rank 1..N global per-lowongan | 7 PHPUnit |
| **M21-2** | View `recruitment/ranking` (tabel peringkat + medali top-3 + 4 stat card + toggle urut + empty state) + route + menu "Peringkat Kandidat" + reuse drawer | 11 Playwright |
| **M21-3** | Board kanban urut by skor + badge `#N` di kartu + drawer di-upgrade (bar warna per kriteria + blok bukti), diekstrak jadi partial bersama (`partials/detail-drawer*.blade.php`) | 3 PHPUnit (route+order) |
| **ACCEPTANCE** | Lowongan QA + 5 pelamar skor campur → view urut 1..N benar, medali, stat, kandidat belum-dinilai di bawah, drawer alasan nyambung, toggle jalan | 11/11 Playwright |

**Total test: 10 PHPUnit (23 assertions) + 11 Playwright (m21-ranking) HIJAU.
Full regression: 378 PHPUnit (966 assertions) HIJAU — nol regresi dari 365 baseline
(M18 drawer 8/8 tetap hijau setelah refactor drawer jadi partial bersama).**

Catatan: skor AI di test di-seed (fabricated) SENGAJA — yang diuji logika PENGURUTAN
(ai_score→vector→tanggal, NULL last, rank map, stats), bukan penilaian AI-nya (itu M17,
masih nunggu API key aktif). Ranking view degrade graceful: tanpa skor, urut fallback ke tanggal.

### File yang dibuat/diubah
- `app/Services/Matching/MatchingService.php` — `rankedQuery/rankedApplicants/rankMap/rankingStats`.
- `app/Http/Controllers/Admin/RecruitmentController.php` — `ranking()` baru; `pipeline()` urut by skor + kirim `rankMap`; `applicantDetail()` + `rank`/`rank_total`.
- `resources/views/admin/recruitment/ranking.blade.php` — view peringkat (baru).
- `resources/views/admin/recruitment/partials/detail-drawer.blade.php` + `detail-drawer-js.blade.php` — drawer bersama (dipakai ranking; siap dipakai pipeline).
- `resources/views/admin/recruitment/pipeline.blade.php` — badge `#N` + drawer bar warna + bukti.
- `routes/backpack/custom.php` — route `recruitment/ranking`.
- `resources/views/vendor/backpack/ui/inc/menu_items.blade.php` + `lang/{id,en}/menu.php` — menu "Peringkat Kandidat".
- `tests/Feature/RankingOrderTest.php` + `tests/browser/m21-ranking.mjs` + `tests/browser/_m21_helper.php`.

### Pitfall yang ketemu saat eksekusi
- **`backpack_url($path, [array])` = SEGMEN path, bukan query string** → `recruitment/ranking/5` (404). Untuk query param pakai `backpack_url('path') . '?k=v'`. Kena di view toggle links + test; sudah diperbaiki.
- **Suite 328 error serempak = Docker mati** (bukan kode rusak). `open -a Docker` → compose auto-up mysql/qdrant/waha → rerun. Sesuai pitfall skill.

---

## 1. Masalah (temuan berbasis KODE, bukan plan) — TERBUKTI & DIPERBAIKI

Plan M17 §5 menjanjikan *"Papan/list di-SORT by ai_score"*. Verifikasi ke kode aktual
menunjukkan **janji itu belum terpenuhi**:

| Lokasi | Kode aktual | Akibat |
|--------|-------------|--------|
| `RecruitmentController::pipeline()` (baris 49-51) | `Applicant::with([...])->active()->latest()` | Kartu urut **created_at DESC** (tanggal), **BUKAN skor** |
| `pipeline.blade.php` (baris 76, 96-104) | `@foreach($byStage[$stage] as $applicant)` + badge skor | Skor cuma **nempel sebagai badge**; HR harus **melototin manual** siapa tertinggi |
| Tombol "Ranking dengan AI" (`rankWithAi`) | `MatchingService::rankOpening()` simpan `ai_score`/`vector_score` lalu **reload** | Setelah dinilai, board **balik ke urutan tanggal** — hasil ranking tak terlihat sebagai urutan |

**Kesimpulan jujur:** AI-nya menghitung ranking (skor + `ai_reasoning` tersimpan), tapi
UI **tidak pernah menyusun pelamar dari peringkat 1 ke N**. Yang lo bayangkan
("lowongan QA → urut 1..N jelas alasannya") **belum ada di web**.

### Yang SUDAH ada (jangan dibangun ulang)
- ✅ Skor AI per kartu (`ai_score` badge) + fallback `vector_score`.
- ✅ Alasan per kriteria di **drawer** (`ai_reasoning.criteria[]` = nama/skor/alasan/bukti) — `applicantDetail()` sudah kirim `ai_reasoning` penuh.
- ✅ Jumlah pelamar per tahap (badge `data-count` per kolom kanban).
- ✅ Kolom DB: `ai_score`, `vector_score`, `ai_reasoning`, `ai_model`, `ai_scored_at` (Applicant model, sudah ter-cast).

### Yang BELUM ada (scope M21)
- ❌ Urutan pelamar **by skor** (bukan tanggal).
- ❌ **Nomor peringkat** (#1, #2, … #N) yang eksplisit.
- ❌ Cara lihat **"berapa yang masuk"** dalam konteks ranking 1 lowongan (bukan cuma count per stage).
- ❌ Kontrol urutan eksplisit (toggle: Skor AI / Tanggal / Nama).

---

## 2. Tujuan

Untuk **satu lowongan** (mis. QA), HR bisa:
1. Lihat pelamar **terurut peringkat 1..N by skor AI** (fallback vector_score → tanggal).
2. Lihat **nomor peringkat** eksplisit di tiap kartu/baris (#1, #2, …).
3. Lihat **berapa yang masuk & berapa yang sudah dinilai AI** (mis. "30 pelamar · 12 dinilai AI").
4. Klik peringkat mana pun → **alasan lengkap** (sudah ada di drawer; tinggal dipastikan nyambung).

**Prinsip:** ini **enhancement kecil & additive** — jangan bongkar arsitektur M17/M18.
AI tetap **asisten pengurut, bukan pemutus** (guard rail M17 dipertahankan: tak ada auto-reject/auto-hire by skor).

---

## 3. Evaluasi Bisnis (7 Poin)

| # | Poin | Status | Temuan / Tindakan |
|---|------|:------:|-------------------|
| E1 | Kelengkapan proses bisnis | ⚠️ Sebagian | Skor & alasan ada, tapi **urutan 1..N** (inti nilai bisnis ranking) belum. M21 menutup ini. |
| E2 | Integrasi keluar | ➖ N/A | Tak ada integrasi baru; pakai `ai_score`/`vector_score` yang sudah tersimpan. |
| E3 | Best-practice tampilan | ⚠️ Sebagian | Ranking = data ber-peringkat → butuh **urutan eksplisit + nomor**, bukan sekadar badge di kartu urut-tanggal. Tambah **tabel peringkat** untuk 1 lowongan (best-practice untuk "leaderboard"). |
| E4 | Third-party config | ➖ N/A | Tak ada third-party baru (Qdrant/LLM config sudah di M15). |
| E5 | Keterkaitan antar-fitur | ✅ | Reuse drawer (M18), `MatchingService` (M17), `applicantDetail` (M18). Nyambung, tanpa duplikasi. |
| E6 | Bahasa (i18n) | ➖ | Ikut level proyek: menu ID hardcode. Label baru samakan pola (jangan over-translate). |
| E7 | Currency | ➖ N/A | Skor 0-100, bukan uang. (`expected_salary` di drawer sudah `money()`.) |

---

## 4. Arsitektur Solusi (2 opsi, minta keputusan Capt)

### Opsi A — Sorting minimal (board tetap kanban)
Ubah query `pipeline()` supaya tiap kolom kanban urut by skor + tambah nomor peringkat di kartu.

```php
// RecruitmentController::pipeline()
$query = Applicant::with(['jobOpening', 'hiredUser'])
    ->active()
    ->orderByRaw('ai_score IS NULL, ai_score DESC')       // dinilai LLM → tertinggi dulu
    ->orderByRaw('vector_score IS NULL, vector_score DESC') // fallback shortlist Qdrant
    ->latest();                                            // fallback terakhir: tanggal
```

- Kartu dapat badge `#N` (peringkat DALAM kolom stage-nya).
- **Plus:** karena skor global per lowongan lintas-stage, nomor peringkat bisa
  ambigu kalau dihitung per-kolom. → M21 hitung **peringkat per-lowongan** di
  controller (rank map: `application_id => posisi`), lalu tampilkan di kartu.
- Ringan, low-risk, langsung kelihatan.

### Opsi B — Panel "Peringkat Kandidat" khusus (RECOMMENDED untuk "1..N jelas")
Tambah **satu view tabel peringkat** untuk 1 lowongan terpilih — persis skenario QA lo.

```
/admin/recruitment/ranking?job_opening_id=QA
┌──────────────────────────────────────────────────────────────────┐
│ Lowongan: QA Engineer   ·   30 pelamar   ·   12 dinilai AI         │
│ [Ranking dengan AI]   Urut by: (Skor AI ▼)  [Skor AI|Vektor|Tgl]   │
├────┬──────────────┬────────┬──────────────┬───────────┬──────────┤
│ #  │ Nama         │ Skor AI│ Ringkasan AI │ Tahap     │ Aksi     │
├────┼──────────────┼────────┼──────────────┼───────────┼──────────┤
│ 1  │ Budi S.      │  91    │ Kuat di auto…│ Interview │ [Detail] │
│ 2  │ Sari W.      │  87    │ 4th QA, lead…│ Screening │ [Detail] │
│ …  │ …            │  …     │ …            │ …         │ …        │
│ 30 │ (belum dinilai)│ ~42  │ shortlist    │ Applied   │ [Detail] │
└────┴──────────────┴────────┴──────────────┴───────────┴──────────┘
```

- Kolom **#** = peringkat 1..N eksplisit.
- Klik **[Detail]** → drawer yang SAMA (M18) → CV + `ai_reasoning` per kriteria (alasan lengkap).
- Header ringkas jawab "berapa yang masuk & berapa dinilai".
- Toggle urut eksplisit (Skor AI / Vektor / Tanggal / Nama).
- Board kanban existing **tetap ada** (view proses/drag-drop); ranking view = lensa "leaderboard".

> **Rekomendasi:** **Opsi B** paling pas dengan permintaan ("urutan 1..N jelas
> alasannya"), dan Opsi A (sorting kanban) bisa disertakan sebagai bonus kecil
> di fase yang sama karena perubahannya satu query. Keputusan final = Capt.

---

## 5. Task Breakdown (usulan, 1 sub-fase per eksekusi + test hijau)

### M21-1 — Rank service + query terurut
- `MatchingService` (atau method baru `rankedApplicants($openingId, $orderBy)`): kembalikan koleksi terurut + map `application_id => posisi`.
- Aturan urut: `ai_score DESC` → `vector_score DESC` → `created_at DESC`; NULL selalu di bawah.
- **Test PHPUnit** `RankingOrderTest`: seed 5 pelamar skor campur (ada yang null) → assert urutan tepat + nomor peringkat benar + NULL di bawah.

### M21-2 — Ranking view (Opsi B) + header statistik
- Route `recruitment/ranking` (grup `permission:recruitment.view`), controller `ranking(Request)`.
- View tabel `#/Nama/Skor AI/Ringkasan/Tahap/Aksi`, header "N pelamar · M dinilai AI", selector lowongan + toggle urut.
- Tombol "Ranking dengan AI" reuse `rankWithAi` (sudah ada).
- **Test Playwright** `m21-ranking.mjs`: buka ranking QA → assert baris urut menurun by skor, kolom # = 1..N, header angka benar, klik Detail → drawer muncul dengan `ai_reasoning`.

### M21-3 — (Opsi A bonus) nomor peringkat di kartu kanban + sort per kolom
- `pipeline()` pakai query terurut + kirim rank map ke view.
- Kartu tampil badge `#N`; kolom urut by skor.
- **Test**: PHPUnit query-order pada `pipeline()` + Playwright assert kartu teratas = skor tertinggi.

### M21-4 — Acceptance + regresi + dokumentasi
- Playwright acceptance: 1 lowongan (mis. QA) + N pelamar berskor → ranking view tampil 1..N benar, drawer alasan nyambung, kanban konsisten.
- Full regression: `php -d memory_limit=2G -d xdebug.mode=off vendor/bin/phpunit --no-coverage` → nol regresi dari 365 baseline.
- Update plan → rename `-DONE`, catat hasil test, update README index.

---

## 8. Keputusan Terkunci (Capt, 2026-08-26)

Mockup UI sudah di-review & disetujui Capt (lihat §9). Keputusan final:

1. **Scope = A+B.** Bangun **view Peringkat Kandidat khusus (Opsi B)** sebagai jalur
   utama untuk lihat "1..N jelas", PLUS **fix sorting kanban (Opsi A)** sebagai bonus
   di fase M21-3 karena cuma satu perubahan query.
2. **Peringkat dihitung GLOBAL per-lowongan** (semua pelamar 1 lowongan diperingkat
   1..N lintas-stage) — sesuai permintaan "urutan 1..N".
3. **Pelamar belum dinilai AI TAMPIL di bawah** dengan skor vektor sebagai fallback,
   ditandai badge "belum dinilai AI". Tidak disembunyikan.
4. **Tanpa export dulu** (on-screen only, YAGNI). Bisa nyusul kalau diminta.
5. **Aturan urut:** `ai_score DESC` → `vector_score DESC` → `created_at DESC`; NULL
   selalu di bawah. Toggle eksplisit: Skor AI / Vektor / Tanggal / Nama.
6. **Medali top-3** (emas/perak/perunggu) dipertahankan di kolom #; peringkat 4+ pakai
   badge angka polos.
7. **Guard rail M17 dipertahankan** — AI hanya mengurutkan; hire/reject tetap manual
   HR. Footer view mencantumkan "AI = asisten pengurut, keputusan tetap di HR".
8. **Drawer detail = REUSE M18** (`applicantDetail` + offcanvas existing). Tidak bikin
   drawer baru; tinggal dipastikan `ai_reasoning.criteria[]` ter-render dengan bar warna
   + bukti (mockup m21-drawer sudah jadi acuan tampilan target).

---

## 9. Spesifikasi UI (acuan mockup — sudah di-approve)

Mockup HTML tersimpan di `docs/plan/mockup/`:
- `m21-ranking.html` — view Peringkat Kandidat (layar utama).
- `m21-drawer.html` — drawer detail (target tampilan `ai_reasoning`).

### 9.1 View Peringkat Kandidat (`ranking.blade.php`)

**Struktur atas → bawah:**
1. **Breadcrumb + judul** "🤖 Peringkat Kandidat".
2. **Baris kontrol:**
   - Dropdown **Lowongan** (label + jumlah pelamar, mis. "QA Engineer (30 pelamar)").
   - Toggle **Urut berdasarkan** (segmented): Skor AI (default) · Vektor · Tanggal · Nama.
   - Tombol **"🤖 Ranking dengan AI"** (reuse `rankWithAi`, hanya muncul kalau `canEdit`).
3. **Kartu statistik** (4 kartu): Pelamar masuk · Sudah dinilai AI · Belum dinilai · Skor tertinggi.
4. **Tabel peringkat** kolom: `#` · Kandidat (nama+email) · Skor AI (angka + bar) · Ringkasan Penilaian AI · Tahap (pill warna) · Aksi (Detail).
   - `#` top-3 = medali (emas/perak/perunggu bulat), 4+ = badge abu.
   - Skor AI: `NN/100` + progress bar gradien. Kalau belum dinilai AI → tampil `~NN vektor` + badge "belum dinilai AI".
   - Ringkasan = `ai_reasoning.summary` (fallback "Baru shortlist Qdrant…" kalau vektor saja).
   - Pill tahap: applied(abu)/screening(biru)/interview(hijau)/offer(oranye).
5. **Footer tabel:** "Menampilkan peringkat 1–N dari N pelamar · M dinilai AI · K shortlist vektor" + catatan guard rail.

**Kosong state:** kalau lowongan belum dipilih → prompt "Pilih lowongan untuk lihat peringkat". Kalau 0 pelamar → "Belum ada pelamar untuk lowongan ini".

### 9.2 Drawer detail (reuse M18, pastikan render lengkap)

Target tampilan (mockup m21-drawer):
- **Header skor:** angka besar `NN/100` + badge **#peringkat dari total** + model + tanggal.
- **Ringkasan AI** (`ai_reasoning.summary`) di blok highlight.
- **Rincian per kriteria** (`ai_reasoning.criteria[]`): tiap item = nama + skor + bar warna (hi≥80 hijau / mid 60-79 oranye / lo<60 merah) + alasan + **bukti kutipan** (`evidence`).
- CV inline (iframe) · Wawancara · Timeline riwayat (existing M18).
- Aksi: Pindah tahap / Terima / Tolak (existing).

> Catatan: drawer M18 saat ini sudah render `summary` + `criteria` (list-group). M21
> hanya perlu memastikan **bar warna per kriteria + blok bukti** tampil sesuai mockup
> (kalau belum ada, tambahkan styling di `pipeline.blade.php` bagian `drawer-ai-criteria`).

---

## 10. Definition of Done

1. Untuk 1 lowongan, pelamar tampil **terurut peringkat 1..N** by skor (fallback vektor→tanggal), NULL di bawah.
2. **Nomor peringkat** (#1..#N) terlihat eksplisit; top-3 pakai medali.
3. Header menampilkan **jumlah masuk + dinilai AI + belum + skor tertinggi**.
4. Toggle urut (Skor AI/Vektor/Tanggal/Nama) berfungsi.
5. Klik peringkat → drawer menampilkan **alasan lengkap** (`ai_reasoning` per kriteria + bukti).
6. Guard rail M17 dipertahankan: AI hanya mengurutkan; hire/reject tetap manual.
7. Kanban existing dapat sorting by skor + badge `#N` (Opsi A) tanpa regresi drag-drop/bulk/drawer.
8. PHPUnit + Playwright hijau (tunjukkan pass/fail nyata), nol regresi dari 365 baseline.
9. Tampilan view & drawer sesuai mockup yang di-approve.

---

## 11. Files (perkiraan yang disentuh)

- `app/Http/Controllers/Admin/RecruitmentController.php` — method `ranking()` (baru), ubah query `pipeline()` (Opsi A).
- `app/Services/Matching/MatchingService.php` — `rankedApplicants($openingId, $orderBy)` + rank map `id=>posisi`.
- `resources/views/admin/recruitment/ranking.blade.php` — view tabel peringkat (baru, ikut mockup m21-ranking).
- `resources/views/admin/recruitment/pipeline.blade.php` — badge `#N` di kartu (Opsi A) + pastikan drawer render bar warna + bukti per kriteria.
- `routes/backpack/custom.php` — route `recruitment/ranking` (grup `permission:recruitment.view`).
- Menu sidebar Rekrutmen — sub-item "Peringkat Kandidat" (samakan pola menu existing, hardcode ID sesuai level i18n proyek).
- Tests: `tests/Feature/RankingOrderTest.php` + `tests/browser/m21-ranking.mjs`.

---

## 12. Task Breakdown Final (1 sub-fase per eksekusi, test hijau sebelum lanjut)

### M21-1 — Rank service + query terurut
- `MatchingService::rankedApplicants($openingId, $orderBy='ai_score')` → koleksi terurut + map `application_id => posisi` (1..N global per-lowongan).
- Aturan urut terkunci (§8.5); NULL di bawah; toggle `orderBy` (ai_score|vector_score|created_at|name).
- **PHPUnit** `RankingOrderTest`: seed 5 pelamar skor campur (ada null) → assert urutan tepat + nomor peringkat benar + NULL di bawah + tiap mode toggle.

### M21-2 — Ranking view (Opsi B) + header statistik + route + menu
- Route `recruitment/ranking` + `ranking(Request)` controller (guardView, ambil opening terpilih, stats, koleksi terurut).
- View `ranking.blade.php` sesuai §9.1 (kontrol, 4 stat card, tabel, footer, empty state).
- Reuse tombol "Ranking dengan AI" (`rankWithAi`) + drawer detail (`applicantDetail`).
- Sub-item menu "Peringkat Kandidat".
- **Playwright** `m21-ranking.mjs`: pilih lowongan → assert baris urut menurun by skor, kolom # = 1..N, header angka benar, toggle urut jalan, klik Detail → drawer muncul dengan `ai_reasoning`.

### M21-3 — (Opsi A) nomor peringkat di kartu kanban + sort per kolom + polish drawer
- `pipeline()` pakai query terurut + kirim rank map ke view; kartu tampil badge `#N`; kolom urut by skor.
- Pastikan drawer render **bar warna per kriteria + blok bukti** (§9.2).
- **Test:** PHPUnit query-order pada `pipeline()` + Playwright assert kartu teratas = skor tertinggi + badge #N tampil.

### M21-4 — Acceptance + regresi + dokumentasi
- Playwright acceptance: 1 lowongan (QA) + N pelamar berskor → ranking view 1..N benar, drawer alasan nyambung, kanban konsisten.
- Full regression: `php -d memory_limit=2G -d xdebug.mode=off vendor/bin/phpunit --no-coverage` → nol regresi dari 365.
- Update plan (hasil test) → rename `-DONE` → update README index. Notif TG tiap fase (pola M17/M18).
