# Review RitmeHR — Versi 1

> Tanggal: 2026-08-27 · Penguji: Hermes Agent · Build/commit: `633716b` (M21: Recruitment Ranking View)
> Metode: Browser-first (Chromium, native handlers) → PHPUnit → review bisnis kritis.
> Lingkungan: `php artisan serve` @ 127.0.0.1:8000, Docker MySQL/Qdrant/WAHA sehat, DemoDataSeeder (5 karyawan, 110 presensi, 5 gaji).

## Ringkasan Eksekutif

RitmeHR sudah **matang secara fungsional dan sangat kokoh secara teknis** — 390 unit test dan 146 skenario browser CRUD lulus 100%, dengan RBAC, data-scoping per-role, export Excel/PDF, dan portal absensi publik semuanya bekerja. Fondasi ini layak untuk demo dan pilot terbatas. Namun aplikasi **belum siap untuk onboarding mandiri pelanggan baru**: tidak ada setup wizard, tidak ada jalur import data karyawan/payroll dari Excel/API lewat UI (import hanya untuk presensi via CLI), dan admin baru langsung mendarat di panel Backpack telanjang berisi 17 modul tanpa "mulai dari sini".

**Kekuatan terbesar:** kualitas engineering — cakupan test tinggi, keamanan peran rapi, portal karyawan yang bersih dan ramah. **Masalah terbesar:** tidak ada jalur **onboarding + migrasi data** self-service, sehingga *time-to-value* pelanggan baru bergantung penuh pada pendampingan manual — ini menghambat justifikasi "percepatan bisnis".

## Hasil Test

| Lapisan | Hasil | Catatan |
|---|---|---|
| Browser — regresi CRUD | **146 PASS / 0 FAIL** | `tests/browser/crud-suite.mjs` (RBAC, scoping, export, portal publik) |
| Browser — UI/AJAX | **Hijau** | `tests/browser/ui-test.mjs` (semua tabel load via AJAX, sidebar per-role benar) |
| Browser — walkthrough manual | 6 lensa ditelusuri sbg user baru | login, dashboard, users, salary, company-profile, portal `/my`, `/scan` |
| PHPUnit | **390 lulus / 0 gagal** (999 assertions) | `phpunit --no-coverage`, 1m22s |
| Bug baru ditemukan | **6** (🔴0 🟠2 🟡3 🟢1) | lihat [masukan-teknis.md](masukan-teknis.md) |

Catatan penting: **semua test hijau tetapi 6 temuan UX/bisnis di bawah tidak tertutup satu pun test** — ini gap kualitas (test menutupi logika, bukan pengalaman user).

## Scorecard 6 Lensa Bisnis

| # | Lensa | Verdict | Inti temuan | Bukti |
|---|---|---|---|---|
| 1 | Paham di tahap awal | ⚠️ Perlu perbaikan | Dashboard informatif tapi bukan panduan; admin baru tak tahu "langkah pertama" | `screenshots/02-dashboard.png` |
| 2 | Menentukan identitas (first setup) | ❌ Bermasalah | Tak ada setup wizard; admin baru mendarat di panel 17 modul telanjang; Company Profile harus dicari manual | `screenshots/05-company-profile-typo.png`, tak ada route `wizard/onboard/welcome` |
| 3 | Tidak bingung antar modul | ✅ Kuat | Sidebar terkelompok jelas (Absen, Gajian, Cuti, Pajak…), submenu rapi, breadcrumb konsisten | `screenshots/03-salary-list.png` |
| 4 | Migrasi data lama (Excel/API) | ❌ Bermasalah | Tidak ada import UI utk karyawan/gaji; import hanya presensi via CLI `import:presence-command` | `app/Console/Commands/ImportPresenceCommand.php`, tak ada route Import |
| 5 | Ideal untuk percepatan bisnis | ⚠️ Perlu perbaikan | Fitur end-to-end lengkap (absensi→gaji→PPh21→slip) & otomatis, tapi entry awal manual menahan ROI | `crud-suite` payroll/tax 25/25 PASS |
| 6 | Nyaman melihat UI existing | ⚠️ Perlu perbaikan | Login & portal cantik; tapi tabel Gaji sembunyikan nilai di viewport sedang, currency tanpa pemisah ribuan, campur ID/EN, typo "Pofile" | `screenshots/03-salary-list.png`, `screenshots/01-login.png` |

### Uraian per lensa

**1 — Paham di tahap awal (⚠️).**
*Dicoba:* login super_admin → mendarat di `/admin/dashboard`. *Terjadi:* dashboard menampilkan KPI hari ini (Hadir 0/5, Belum Absen 5), ringkasan bulan ini, tren kehadiran 12 bulan, headcount per departemen, quick-link laporan. Informatif untuk operasional harian, **tapi bukan orientasi first-run** — tidak ada empty-state/checklist "mulai di sini" untuk instance yang masih kosong. *Kenapa penting:* pelanggan baru tanpa data akan melihat banyak "0"/"Rp 0" dan tidak tahu apa langkah berikutnya. *Rekomendasi:* tambah kartu onboarding-checklist kondisional saat data inti (karyawan/gaji) masih kosong.

**2 — Menentukan identitas (❌).**
*Dicoba:* simulasi admin baru mencari cara mengatur perusahaan & diri sendiri. *Terjadi:* tidak ada wizard; identitas perusahaan ada di menu **Profile Perusahaan** yang harus ditemukan sendiri di antara 17 item sidebar; tak ada langkah terpandu company→cabang→departemen→user. *Kenapa penting:* setup identitas adalah gerbang pertama; tanpa panduan, adopsi mandiri tersendat dan butuh pendampingan. *Rekomendasi:* Setup Wizard 4 langkah (Perusahaan → Cabang/Dept → Admin/Role → Import Karyawan).

**3 — Tidak bingung antar modul (✅).**
*Dicoba:* menelusuri seluruh sidebar sebagai super_admin & manager. *Terjadi:* modul dikelompokkan logis dengan submenu (Gajian → Gaji/Jenis Tunjangan/Rekap Gaji), breadcrumb konsisten, dan sidebar menyesuaikan role (manager tak melihat Pengaturan — `TC-SET-01 PASS`). Data mengalir antar modul (presensi → rekap gaji → slip → PPh21). *Rekomendasi:* pertahankan; hanya rapikan konsistensi label (lihat lensa 6).

**4 — Migrasi data lama (❌).**
*Dicoba:* mencari jalur import karyawan/gaji dari Excel/API. *Terjadi:* satu-satunya import adalah **presensi lewat CLI** (`import:presence-command` + `App\Imports\PresenceImport`), tidak ada UI import maupun template untuk karyawan/payroll. User export ada, tapi tidak simetris dengan import. *Kenapa penting:* pelanggan yang pindah dari Excel/HRIS lain harus re-entry manual — penghalang go-live terbesar. *Rekomendasi:* import Excel berbasis UI untuk Karyawan & Gaji (dengan template unduhan + preview + validasi), lalu merge ke DB langsung (sesuai preferensi: tanpa tabel perantara manual).

**5 — Ideal untuk percepatan bisnis (⚠️).**
*Dicoba:* menilai rantai nilai absensi→payroll. *Terjadi:* rantai lengkap & otomatis (presensi geofence → rekap → tunjangan → PPh21 TER → slip PDF → export), terbukti hijau di `crud-suite` (Pajak 25/25, Penggajian 4/4). Ini jelas mempercepat vs Excel manual **setelah data masuk**. Hambatan ROI justru di hulu (entry awal manual, lensa 2 & 4). *Rekomendasi:* prioritaskan onboarding+import agar nilai otomasi cepat terasa.

**6 — Nyaman melihat UI existing (⚠️).**
*Dicoba:* menilai estetika & keterbacaan di beberapa layar. *Terjadi:* login split-screen ber-brand dan portal karyawan (`/my`) bersih & modern (bukti `06-portal-home`, `01-login`). Namun list admin punya masalah keterbacaan: **tabel Gaji menyembunyikan seluruh kolom nilai** di viewport sedang (hanya Nama terlihat, sisanya collapse), currency tampil **"Rp12031000"** tanpa pemisah ribuan, header kolom **campur Inggris/Indonesia** ("Name/Email/Locale/Join date" vs "Departemen"), dan ada **typo "Pofile Perusahaan"**. *Kenapa penting:* angka gaji tak terbaca = ketidakpercayaan langsung pada modul paling sensitif. *Rekomendasi:* format ribuan + prioritas kolom nilai, seragamkan bahasa, perbaiki typo.

## Kesimpulan & Rekomendasi Rilis

- **Layak untuk:** demo, pilot terpandu (dengan pendampingan setup).
- **Belum layak untuk:** self-serve onboarding pelanggan baru tanpa bantuan.
- **3 hal yang paling menggerakkan bisnis:** (1) Setup Wizard, (2) Import Excel Karyawan+Gaji, (3) rapikan keterbacaan tabel Gaji + konsistensi bahasa. Detail & urutan di [rencana-perbaikan.md](rencana-perbaikan.md).

## Tautan
- [Masukan teknis](masukan-teknis.md) — daftar temuan + severity + lokasi kode
- [Rencana perbaikan](rencana-perbaikan.md) — quick wins vs struktural, urutan impact-first
- Screenshot bukti: `screenshots/`
