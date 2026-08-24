# Modul 15 — Dashboard & Laporan

| | |
|---|---|
| **Controller** | [DashboardController](../../app/Http/Controllers/Admin/DashboardController.php) |
| **Service** | [DashboardService](../../app/Services/DashboardService.php) |

Halaman baca-saja — tidak ada CRUD. Yang diuji: **kebenaran angka**,
konsistensi terhadap modul sumber, perilaku cache, dan hak akses.

---

## 15.1 Dashboard — `/admin/dashboard`

Menggantikan dashboard bawaan Backpack.

### Render

| ID | Skenario | Langkah | Expected | Status |
|---|---|---|---|---|
| DASH-R-01 | Halaman termuat | Login SA → dashboard | 200, **nol JS error** | 🌐 |
| DASH-R-02 | Kartu hari ini | Amati baris atas | 4 kartu: Hadir · Belum Absen · Terlambat · Di Luar Radius | 🌐 |
| DASH-R-03 | Kartu bulan ini | Amati baris kedua | Total Gaji · Total Lembur · Total Potongan · Sisa Kasbon | 🌐 |
| DASH-R-04 | **Grafik tren 12 bulan** | Amati Chart.js | Canvas 593×178 ter-render; dua seri (Tingkat Kehadiran % + kali telat); sumbu Sep 25–Agt 26 berlabel | 🌐 |
| DASH-R-05 | Headcount | Amati panel | Total Aktif 5 · Teknologi 2 · HRD 2 · Operasional 1 · Head Office 0 | 🌐 |
| DASH-R-06 | Top keterlambatan | Amati panel | "Tidak ada keterlambatan bulan ini." — empty state tampil rapi | 🌐 |
| DASH-R-07 | Cuti minggu ini | Amati panel | Empty state tampil rapi | 🌐 |
| DASH-R-08 | Pintasan laporan | Amati baris bawah | 6 tombol: Kehadiran, Gaji, Kasbon, Cuti, Pajak, BPJS | 🌐 |

### Kebenaran angka

| ID | Skenario | Langkah | Expected | Status |
|---|---|---|---|---|
| DASH-X-01 | **Cuti bukan absen** | Karyawan cuti disetujui hari ini | Dihitung di panel cuti, **tidak** masuk "Belum Absen" | ⬜ |
| DASH-X-02 | Hadir konsisten | Bandingkan dengan `/admin/presence` hari ini | Angka cocok | ⬜ |
| DASH-X-03 | Payroll konsisten | Bandingkan Total Gaji vs `/admin/salary-recap` | Angka cocok | ⬜ |
| DASH-X-04 | Sisa kasbon konsisten | Bandingkan vs `/admin/loan/recap` | Rp 2.000.000 pada data demo — cocok | 🌐 |
| DASH-X-05 | Headcount hanya aktif | Set satu karyawan `resigned` | Total Aktif turun jadi 4 (`User::employed()`) | ⬜ |
| DASH-X-06 | Probation ikut dihitung | Set satu karyawan `probation` | **Ikut** dihitung aktif | ⬜ |
| DASH-X-07 | Top keterlambatan terurut | Buat beberapa keterlambatan | Terurut menurun, hanya karyawan aktif | ⬜ |

### Cache

| ID | Skenario | Langkah | Expected | Status |
|---|---|---|---|---|
| DASH-X-08 | Cache 5 menit | Ubah presensi → refresh dashboard | Angka hari ini boleh tertinggal ≤5 menit — **perilaku benar** | ⬜ |
| DASH-X-09 | Flush cache | Panggil `DashboardService::flushCache()` | Angka langsung segar | ⬜ |
| DASH-X-10 | Angka non-hari-ini | Ubah data bulan lalu | Tidak terpengaruh cache hari ini | ⬜ |

> **Jangan laporkan sebagai bug:** pada data demo dashboard menampilkan
> "Hadir 0 dari 5" dan "Total Gaji Rp 0, 0 rekap". Data demo sengaja ditaruh di
> **bulan sebelumnya** — rekap gaji mengukur satu bulan penuh, sehingga bulan
> berjalan yang baru separuh akan terbaca seperti absen massal. Grafik tren pun
> datar sampai Jul 26 karena hanya satu bulan yang terisi.

---

## 15.2 Laporan Kehadiran — `/admin/report/attendance`

| ID | Skenario | Langkah | Expected | Status |
|---|---|---|---|---|
| RPT-A-01 | Halaman termuat | Buka halaman | 200 | ✅ |
| RPT-A-02 | Filter periode | Ganti bulan | Data menyesuaikan | ⬜ |
| RPT-A-03 | Kolom rekap | Amati tabel | Hadir, telat, absen, cuti terpisah | ⬜ |
| RPT-A-04 | Cuti terpisah dari absen | Karyawan dengan cuti disetujui | Masuk kolom cuti, bukan absen | ⬜ |
| RPT-A-05 | Konsistensi | Bandingkan vs `/admin/presence` | Angka cocok | ⬜ |

## 15.3 Laporan Gaji — `/admin/report/salary`

| ID | Skenario | Langkah | Expected | Status |
|---|---|---|---|---|
| RPT-S-01 | Halaman termuat | Buka halaman | 200 | ✅ |
| RPT-S-02 | Total per periode | Amati angka | Bruto, potongan, netto | ⬜ |
| RPT-S-03 | Konsistensi | Bandingkan vs Rekap Gaji | Angka cocok | ⬜ |
| RPT-S-04 | Karyawan resigned | Amati daftar | Riwayat tetap ada, tidak masuk total berjalan | ⬜ |

## 15.4 Laporan Kasbon — `/admin/report/loan`

| ID | Skenario | Langkah | Expected | Status |
|---|---|---|---|---|
| RPT-L-01 | Halaman termuat | Buka halaman | 200 | ✅ |
| RPT-L-02 | Saldo per karyawan | Amati tabel | Kasbon − pembayaran = sisa | ⬜ |
| RPT-L-03 | Konsistensi | Bandingkan vs `/admin/loan/recap` | Angka cocok | ⬜ |

## 15.5 Laporan Headcount — `/admin/report/headcount`

| ID | Skenario | Langkah | Expected | Status |
|---|---|---|---|---|
| RPT-H-01 | Halaman termuat | Buka halaman | 200 | ✅ |
| RPT-H-02 | Per departemen | Amati rincian | Jumlah per departemen benar | ⬜ |
| RPT-H-03 | Per status | Amati rincian | active / probation / resigned terpisah | ⬜ |
| RPT-H-04 | Konsistensi | Bandingkan vs panel Headcount di dashboard | Angka cocok | ⬜ |

## 15.6 Laporan lain

| ID | Laporan | URL | Expected | Status |
|---|---|---|---|---|
| RPT-X-01 | Rekap Cuti | `/admin/leave-report` | 200 | ✅ |
| RPT-X-02 | Rekap Pajak Tahunan | `/admin/tax-report/annual` | 200 | ✅ |
| RPT-X-03 | Rekap BPJS Bulanan | `/admin/tax-report/bpjs` | 200 | ✅ |

---

## 15.7 Notifikasi (lonceng topbar)

| ID | Skenario | Langkah | Expected | Status |
|---|---|---|---|---|
| NOT-R-01 | Halaman notifikasi | `/admin/notification` | 200 | ✅ |
| NOT-R-02 | Endpoint jumlah | `/admin/notification/unread-count` | 200, `application/json` | ✅ |
| NOT-R-03 | Badge lonceng | Amati topbar | Menampilkan jumlah belum dibaca | ⬜ |
| NOT-U-01 | Tandai satu dibaca | Klik satu notifikasi | Badge berkurang satu | ⬜ |
| NOT-U-02 | Tandai semua dibaca | Tombol Mark all read | Badge jadi nol | ⬜ |
| NOT-X-01 | **Channel database selalu ditulis** | Picu aksi bernotifikasi | Baris masuk tabel `notifications` — inilah jejak auditnya | ⬜ |
| NOT-X-02 | **Kegagalan kirim tidak rollback** | Rusakkan config mail → picu notifikasi | **Aksi pemicu tetap tersimpan**; kegagalan dicatat diam-diam | ⬜ |
| NOT-X-03 | WhatsApp fallback | `FONNTE_TOKEN` kosong | Pakai `LogWhatsAppGateway` — hanya mencatat, **tidak berpura-pura terkirim** | ⬜ |
| NOT-X-04 | Preferensi channel | Matikan email di `notification_preferences` | Tidak dikirim via email; database tetap ditulis | ⬜ |

### Notifikasi terjadwal

| ID | Jadwal | Command | Expected | Status |
|---|---|---|---|---|
| NOT-S-01 | Hari kerja 08:15 | `notify:attendance --type=checkin` | Karyawan belum absen dinotifikasi | ⬜ |
| NOT-S-02 | Hari kerja 09:30 | `notify:attendance --type=late` | Karyawan terlambat dinotifikasi | ⬜ |
| NOT-S-03 | Hari kerja 17:00 | `notify:attendance --type=checkout` | Belum check-out dinotifikasi | ⬜ |
| NOT-S-04 | Senin 07:30 | `documents:notify-expiring --days=30` | Dokumen mendekati kedaluwarsa | ⬜ |
| NOT-S-05 | Senin 08:00 | `notify:approval-digest` | Ringkasan approval tertunda | ⬜ |
| NOT-S-06 | 1 Jan 01:00 | `leave:generate-balances --carry-over --max-carry=6` | Saldo cuti tahunan | ⬜ |
| NOT-S-07 | Bulanan | `audit:prune --days=90` | Audit log lama dipangkas | ⬜ |
| NOT-S-08 | Akhir pekan | Jalankan pada Sabtu | Notifikasi kehadiran **tidak** terkirim | ⬜ |

---

## AKSES

| ID | Role | Expected | Status |
|---|---|---|---|
| DASH-A-01 | SA / HR | Akses penuh dashboard & seluruh laporan | ✅ 200 |
| DASH-A-02 | MGR | Punya `report.view` + `report.export` — boleh melihat laporan. ⚠️ Namun angkanya belum ter-scope tim (DEF-04) | ⚠️ |
| DASH-A-03 | EMP | Dialihkan ke `/my` | 🌐 |
