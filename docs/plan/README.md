# Development Plan Framework — Absensi/HRIS

> **Dibuat:** 2026-08-24
> **Tujuan:** Kerangka kerja pengembangan modul yang terstruktur, dengan evaluasi
> bisnis wajib sebelum eksekusi. Setiap modul punya file plan terpisah di folder ini.

---

## 1. Cara Kerja Framework Ini

1. **Satu modul = satu file plan** (`Mxx-nama-modul.md`) di folder `docs/plan/`.
2. Setiap plan **wajib lulus evaluasi bisnis 7-poin** (lihat §2) sebelum masuk antrian eksekusi.
3. Eksekusi **maksimal 1 modul per sesi, tidak melompat** — selesaikan penuh dulu.
4. Kalau sebuah modul **sudah diimplementasi penuh** (semua checklist ✅ + diuji),
   rename filenya jadi `Mxx-nama-modul-DONE.md`.
5. Urutan eksekusi ditentukan oleh **urgency + keterkaitan antar modul** (lihat §4).

### Prinsip inti (dari arahan Capt)
- **"Kode ada" ≠ "bisa dipakai".** Yang dinilai: apakah proses bisnis selesai end-to-end lewat UI.
- **Tidak boleh gantung ke sistem luar.** Kalau ada integrasi keluar, harus bisa di-manage sendiri (internal).
- **Third-party (storage/hitung/gateway) wajib punya halaman config** yang diatur super admin — bukan hardcode di `.env`.
- **Fitur yang berkaitan harus seamless** — pengelompokan menu & aliran data saling nyambung.

---

## 2. Rubrik Evaluasi Bisnis Wajib (7 Poin)

Setiap file plan **wajib menjawab** 7 pertanyaan ini di bagian "Evaluasi Bisnis":

| # | Pertanyaan | Yang dicek |
|---|-----------|-----------|
| **E1. Kelengkapan proses bisnis** | Apakah produk memenuhi proses bisnis penuh? (mis. user mgmt: buat, hapus, reset password, dll). Apakah modul cuma "sampai kodenya" atau MVP-nya bisa input data juga? | CRUD lengkap, aksi operasional, jalur input data nyata |
| **E2. Integrasi keluar** | Apakah sistem tergantung integrasi eksternal? Jika YA → **ubah jadi bisa manage sendiri (internal)**. | Panggilan API luar, dependensi service pihak ketiga |
| **E3. Best-practice tampilan** | Apakah cara menampilkan data sudah tepat? (mis. data tanggal → cukup tabel atau lebih baik calendar view?) | UX sesuai jenis data (kalender, chart, timeline, dsb) |
| **E4. Third-party config** | Apakah pakai sistem pihak ketiga (storage, perhitungan, dll)? Jika YA → **wajib ada halaman config yang diatur super admin.** | Config UI vs hardcode `.env` |
| **E5. Keterkaitan antar fitur** | Apakah fitur A berkaitan dengan fitur B? Jika YA → bagaimana bikin seamless (menu + aliran data)? | Grouping menu, auto-flow data lintas modul |
| **E6. Bahasa (i18n)** | Apakah sudah multi-language atau masih hardcode? | Label ter-translate, language switcher |
| **E7. Currency** | Apakah sudah bisa multi-currency? Perlu di-setup dari awal? | Format mata uang configurable, bukan hardcode "Rp" |

**Setiap poin harus diberi status:** ✅ Terpenuhi · ⚠️ Sebagian · ❌ Belum · ➖ N/A,
disertai temuan + tindakan yang diperlukan.

---

## 3. Temuan Cross-Cutting (berlaku untuk SEMUA modul)

Hasil audit kode 2026-08-24. Ini masalah lintas-modul, jadi diangkat jadi modul tersendiri:

### 🔴 CC-1. Belum multi-language (E6 gagal global)
- `config/app.php`: `locale='id'`, tapi folder `lang/` **cuma punya `lang/en/`** (default Laravel).
- Semua label UI di-hardcode Bahasa Indonesia langsung di controller/blade.
- **Dampak:** tidak bisa ganti bahasa; tidak ada `lang/id/`. → **Plan M13 (i18n)**.

### 🔴 CC-2. Belum multi-currency (E7 gagal global)
- "Rp"/"Rp." di-hardcode di `number_format(...)`, `TransalateService`, Blade directive `rupiah`, export Excel.
- Tidak ada tabel/konfigurasi currency, tidak ada konsep exchange rate. → **Plan M14 (multi-currency)**.

### 🔴 CC-3. Akuntansi masih integrasi keluar (E2 gagal)
- Modul ACC push transaksi ke **Firefly III eksternal** via API (`ACC_HOST`/`ACC_KEY` di `.env`).
- Sesuai arahan: **harus bisa manage sendiri (buku besar internal)**. → **Plan M12 (akuntansi internal)**.

### 🔴 CC-4. Third-party tanpa halaman config (E4 gagal)
- Firefly (`ACC_*`), WhatsApp Fonnte (`services.fonnte.token`), Storage disk, koordinat kantor (`LAT/LNG`) semua dari `.env`.
- Sesuai arahan: **wajib ada halaman Pengaturan yang diatur super admin.** → **Plan M15 (platform config)**.

### 🟠 CC-5. Onboarding: `DatabaseSeeder` kosong
- `HrisSeeder` (role, permission, dll) tidak dipanggil otomatis → fresh install "kelihatan mati". → **Plan M15 / setup**.

---

## 4. Master Index & Urutan Eksekusi

Status: ✅ Done · ⚠️ Ada gap · ❌ Belum · 🆕 Modul baru dari evaluasi

| Urutan | Modul | File | Status kode | Prioritas |
|:------:|-------|------|:-----------:|:---------:|
| — | M0 Foundation | (done, tidak perlu plan) | ✅ | — |
| 1 | M15 Platform Config + Setup | `M15-platform-config-DONE.md` | ✅🆕 DONE | 🔴 fondasi CC-4/CC-5 |
| 2 | M05 Tax & BPJS (auto-calc) | `M05-tax-bpjs-DONE.md` | ✅ DONE | 🔴 gap MVP |
| 3 | M12 Akuntansi Internal | `M12-internal-accounting-DONE.md` | ✅🆕 DONE | 🔴 CC-3 (E2) |
| 4 | M13 Multi-language (i18n) | `M13-i18n-multilanguage-DONE.md` | ✅🆕 DONE (fondasi) | 🟠 CC-1 (E6) |
| 5 | M14 Multi-currency | `M14-multi-currency-DONE.md` | ✅🆕 DONE | 🟠 CC-2 (E7) |
| 6 | M01 Org Structure | `M01-org-structure-DONE.md` | ✅ DONE (polish) | 🟡 polish |
| 7 | M02 Leave Management | `M02-leave-management-DONE.md` | ✅ DONE (polish) | 🟡 polish (E3 kalender) |
| 8 | M03 Notification | `M03-notification-DONE.md` | ✅ DONE (polish + WAHA) | 🟡 polish |
| 9 | M04 Self-Service Portal | `M04-self-service-portal-DONE.md` | ✅ DONE (polish) | 🟡 polish |
| 10 | M06 Employee Documents | `M06-employee-documents-DONE.md` | ✅ DONE (polish) | 🟡 polish |
| 11 | M07 Multi-Branch | `M07-multi-branch-DONE.md` | ✅ DONE (polish) | 🟡 polish |
| 12 | M08 Reporting & Dashboard | `M08-reporting-dashboard-DONE.md` | ✅ DONE (polish) | 🟡 polish |
| 13 | M09 Recruitment | `M09-recruitment-DONE.md` | ✅ DONE | ⚪ optional (dikerjakan) |
| 14 | M10 Performance | `M10-performance-DONE.md` | ✅ DONE | ⚪ optional (dikerjakan) |
| 15 | M11 Training | `M11-training.md` | ❌ | ⚪ optional |
| 16 | M16 Pluggable Storage (Local/S3/GDrive/Nextcloud) | `M16-pluggable-storage.md` | ✅ FASE 1-3 DONE (S3+GDrive+Nextcloud) · fase 4 opsional | 🟠 CC-4 (E2+E4) |
| 17 | M17 Recruitment 2.0 (Portal Kandidat + AI/Qdrant) | `M17-recruitment-ai-portal-DONE.md` | ✅ DONE (M17-1..5 + acceptance) · 302 tests HIJAU · AI wired+fallback (tes AI nyata nunggu key) | 🟢 |
| 18 | M18 Recruitment UX Overhaul (kelola lamaran terkonsolidasi) | `M18-recruitment-ux-overhaul-DONE.md` | ✅ DONE (M18-1..6 + acceptance) · 329 tests HIJAU · drawer detail + jadwal wawancara inline + bulk + retensi ghosting/archive | 🟢 |

### Alasan urutan
- **M15 duluan**: config platform (storage/gateway/ACC toggle) + auto-seed adalah fondasi. Modul lain bergantung ke sini untuk E4.
- **M05 kedua**: gap MVP nyata (pajak tak auto-hitung) + langsung terkait payroll yang sudah jalan.
- **M12 ketiga**: menggantikan Firefly (E2). Terkait erat dengan M05 (net gaji) & payroll → dikerjakan setelah pajak beres.
- **M13→M14**: cross-cutting UI/format, dikerjakan setelah fondasi data stabil supaya tidak rework.
- **M01–M08**: sudah usable, tinggal polish sesuai temuan E1–E7 di masing-masing plan.
- **M09–M11**: optional, hanya jika ada kebutuhan bisnis.

---

## 5. Konvensi Penamaan & Aturan Eksekusi

- File plan aktif: `Mxx-nama-modul.md`
- File plan selesai penuh: `Mxx-nama-modul-DONE.md` (rename setelah semua checklist ✅ + diuji UI nyata)
- **1 modul per eksekusi, tidak melompat.** Tuntaskan + uji dulu sebelum pindah.
- Setiap plan punya: Ringkasan · Evaluasi Bisnis 7-poin · Gap & Temuan · Task Breakdown · Definition of Done.

---

## 6. Definition of Done (standar semua modul)

Sebuah modul boleh di-rename `-DONE` jika:
1. Semua 7 poin evaluasi bisnis berstatus ✅ (atau ➖ N/A dengan alasan jelas).
2. Proses bisnis bisa diselesaikan end-to-end lewat UI (bukan cuma route/kode).
3. Tidak ada dependensi eksternal yang tak ter-manage (E2).
4. Third-party punya halaman config super admin (E4).
5. Sudah diuji dengan skenario UI nyata (tunjukkan pass/fail).
6. Menu & aliran data seamless dengan modul terkait (E5).
