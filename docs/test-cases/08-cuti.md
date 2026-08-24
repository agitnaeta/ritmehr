# Modul 08 — Cuti & Izin

Dropdown **Cuti & Izin**: Pengajuan Cuti, Ajukan Cuti, Kalender Cuti,
Saldo Cuti, Jenis Cuti, Rekap Cuti.

| | |
|---|---|
| **Tabel** | `leave_types`, `leave_balances`, `leave_requests`, `leave_request_dates` |
| **Service** | [LeaveService](../../app/Services/LeaveService.php) |

Modul ini memperbaiki bug payroll paling penting: sebelumnya **setiap hari tidak
hadir dihitung absen tanpa keterangan**, sehingga cuti dan sakit yang sudah
disetujui tetap memotong gaji.

---

## 8.1 Jenis Cuti — `/admin/leave-type`

**Field:** `name`, `code`, `color`, `default_quota`, `is_paid`, `is_active`,
`max_consecutive_days`, `requires_attachment`

**Validasi:**

| Field | Aturan |
|---|---|
| `name` | `required\|string\|max:100` |
| `code` | `required\|string\|max:20\|unique:leave_types,code` |
| `default_quota` | `nullable\|integer\|min:0\|max:365` |
| `max_consecutive_days` | `nullable\|integer\|min:1\|max:365` |

| ID | Skenario | Data | Expected | Status |
|---|---|---|---|---|
| LVT-C-01 | Buka form | — | Form 8 field | ✅ 200 |
| LVT-C-02 | Cuti tahunan berbayar | `is_paid=1`, kuota 12 | Tersimpan | ⬜ |
| LVT-C-03 | Cuti tidak berbayar | `is_paid=0` | Tersimpan; berdampak beda di payroll | ⬜ |
| LVT-C-04 | Wajib lampiran | `requires_attachment=1` | Pengajuan tanpa berkas ditolak | ⬜ |
| LVT-R-01 | List | Buka list | 5 jenis pada data demo | ⬜ |
| LVT-U-01 | Ubah kuota default | Naikkan `default_quota` | Berlaku untuk generate saldo berikutnya, saldo lama tidak berubah | ⬜ |
| LVT-U-02 | Nonaktifkan | `is_active=0` | Hilang dari pilihan form pengajuan baru | ⬜ |
| LVT-U-03 | Ubah warna | Ganti `color` | Kalender cuti memakai warna baru | ⬜ |
| LVT-D-01 | Hapus jenis terpakai | Delete jenis yang punya pengajuan | Ditolak atau relasi tertangani | ⬜ |
| LVT-V-01 | Submit kosong | Semua kosong | ✅ 302 kembali ke form (validasi jalan) | ✅ |
| LVT-V-02 | Kode duplikat | Kode yang sudah ada | Ditolak — `unique` | ⬜ |
| LVT-V-03 | Kuota > 365 | `default_quota=400` | Ditolak | ⬜ |
| LVT-V-04 | Kuota negatif | `default_quota=-1` | Ditolak — `min:0` | ⬜ |
| LVT-V-05 | Max berturut = 0 | `max_consecutive_days=0` | Ditolak — `min:1` | ⬜ |

---

## 8.2 Saldo Cuti — `/admin/leave-balance`

**Field:** `user_id`, `leave_type_id`, `year`, `quota`, `carry_over`, `used`

Kolom `remaining` adalah **generated column** = `quota + carry_over − used`.
Rencana awal memakai `quota − used`, yang diam-diam membuang sisa carry-over.

| ID | Skenario | Data | Expected | Status |
|---|---|---|---|---|
| LVB-C-01 | Buka form | — | Form 6 field | ✅ 200 |
| LVB-C-02 | Buat saldo manual | User + jenis + tahun + kuota | Tersimpan | ⬜ |
| LVB-R-01 | List | Buka list | 15 baris pada data demo | ⬜ |
| LVB-R-02 | **Kolom `remaining`** | Set `quota=12`, `carry_over=6`, `used=3` | `remaining = 15` — carry-over **tidak** hilang | ⬜ |
| LVB-U-01 | Ubah kuota | Naikkan `quota` | `remaining` ikut naik otomatis | ⬜ |
| LVB-U-02 | `remaining` tidak bisa ditulis | Coba set `remaining` langsung | Ditolak — kolom generated | ⬜ |
| LVB-D-01 | Hapus saldo | Delete | Terhapus; pengajuan baru jenis itu gagal cek kuota dengan pesan jelas | ⬜ |
| LVB-V-01 | **Submit kosong** | Semua kosong | ⚠️ **GAGAL — 500** `Field 'user_id' doesn't have a default value`. Seharusnya pesan validasi | ✅ ⚠️ |
| LVB-V-02 | Saldo ganda | User + jenis + tahun yang sama dua kali | Ditolak — satu saldo per kombinasi | ⬜ |
| LVB-V-03 | `used` > `quota + carry_over` | Set `used` sangat besar | `remaining` negatif — ditolak atau perilaku terdefinisi | ⬜ |
| LVB-X-01 | Generate saldo | Tombol Generate (`POST .../generate`) | Saldo terbentuk untuk semua karyawan aktif | ⬜ |
| LVB-X-02 | Generate idempoten | Jalankan Generate dua kali | **Tidak** menggandakan saldo | ⬜ |
| LVB-X-03 | Carry over | Tombol Carry Over | Sisa tahun lalu terbawa, dibatasi `max-carry` | ⬜ |
| LVB-X-04 | Karyawan masuk tengah tahun | Generate untuk `join_date` bulan Juli | Kuota **prorata**, bukan penuh | ⬜ |
| LVB-X-05 | Via command | `php artisan leave:generate-balances --carry-over --max-carry=6` | Idempoten; terjadwal 1 Januari | ⬜ |

---

## 8.3 Pengajuan Cuti — `/admin/leave-request` & form `/admin/leave-request/create-form`

CRUD standar **dinonaktifkan** (`denyAccess(['create','update','delete'])`);
pengajuan hanya lewat form khusus.

**Field form:** `user_id`, `leave_type_id`, `start_date`, `end_date`, `reason`,
`attachment`

### CREATE

| ID | Skenario | Data | Expected | Status |
|---|---|---|---|---|
| LVR-C-01 | CRUD create ditutup | Buka `/admin/leave-request/create` | **404** — memang sengaja | ✅ |
| LVR-C-02 | Form khusus | Buka `/admin/leave-request/create-form` | 200; 6 field | ✅ |
| LVR-C-03 | Ajukan cuti valid | Semua field terisi | Tersimpan status pending; approval terbentuk | ⬜ |
| LVR-C-04 | Approval otomatis | Setelah submit, buka `/admin/approval` | Approval baru muncul untuk approver langkah 1 | ⬜ |
| LVR-C-05 | Notifikasi terkirim | Setelah submit | Approver menerima notifikasi | ⬜ |

### READ

| ID | Skenario | Langkah | Expected | Status |
|---|---|---|---|---|
| LVR-R-01 | List | Buka `/admin/leave-request` | Tabel AJAX **2 dari 2** pada data demo | 🌐 |
| LVR-R-02 | Scoping `leave.view_all` | Login role tanpa permission itu | Hanya melihat pengajuan sendiri | ⬜ |
| LVR-R-03 | Detail | Buka detail pengajuan | Tanggal, alasan, lampiran, riwayat approval tampil | ⬜ |
| LVR-R-04 | Kolom `total_days` | Amati kolom | Sudah dikurangi akhir pekan & libur nasional | ⬜ |

### UPDATE / CANCEL

| ID | Skenario | Langkah | Expected | Status |
|---|---|---|---|---|
| LVR-U-01 | Edit ditutup | Coba edit lewat CRUD | **404** | ⬜ |
| LVR-U-02 | Batalkan pending | `POST /admin/leave-request/{id}/cancel` | Status cancelled, kuota kembali | ⬜ |
| LVR-U-03 | Batalkan yang sudah disetujui | Cancel pengajuan approved | Perilaku terdefinisi; bila boleh, kuota dikembalikan | ⬜ |
| LVR-U-04 | Batalkan milik orang lain | Kirim id pengajuan karyawan lain | **Ditolak** | ⬜ |

### VALIDASI — aturan bisnis LeaveService

| ID | Skenario | Data | Expected | Status |
|---|---|---|---|---|
| LVR-V-01 | Tanggal akhir < awal | `end_date` sebelum `start_date` | Ditolak | ⬜ |
| LVR-V-02 | **Tumpang tindih** | Rentang yang beririsan dengan pengajuan lain | **Ditolak** | ⬜ |
| LVR-V-03 | Melebihi kuota | Lebih dari `remaining` | Ditolak dengan pesan kuota | ⬜ |
| LVR-V-04 | Melebihi max berturut | Lewati `max_consecutive_days` | Ditolak | ⬜ |
| LVR-V-05 | Lampiran wajib | Jenis `requires_attachment=1`, tanpa berkas | Ditolak | ⬜ |
| LVR-V-06 | Akhir pekan dilewati | Ajukan Jumat–Senin | `total_days = 2` | ⬜ |
| LVR-V-07 | Libur nasional dilewati | Melintasi tanggal libur | Hari libur tidak mengurangi kuota | ⬜ |
| LVR-V-08 | **Kuota dicek dua kali** | Ajukan mendekati batas, lalu setujui | Dicek saat submit (umpan balik cepat) **dan** lagi di bawah row lock saat approval | ⬜ |
| LVR-V-09 | Balapan kuota | Dua pengajuan bersamaan yang totalnya melebihi kuota | Hanya satu lolos | ⬜ |

### DAMPAK PAYROLL

| ID | Skenario | Kondisi | Absen? | Dipotong? | Status |
|---|---|---|:--:|:--:|---|
| LVR-P-01 | Cuti **berbayar** disetujui | `is_paid=1`, approved | tidak | tidak | ⬜ |
| LVR-P-02 | Cuti **tidak berbayar** disetujui | `is_paid=0`, approved | tidak | **ya** | ⬜ |
| LVR-P-03 | Absen tanpa pengajuan | — | **ya** | **ya** | ⬜ |
| LVR-P-04 | Pengajuan masih pending | pending | **ya** | **ya** | ⬜ |
| LVR-P-05 | Pengajuan ditolak | rejected | **ya** | **ya** | ⬜ |
| LVR-P-06 | Pengajuan dibatalkan | cancelled | **ya** | **ya** | ⬜ |

> Rujukan: `tests/Feature/SalaryLeaveIntegrationTest.php`. Data demo memasangkan
> Ahmad (3 hari cuti berbayar disetujui) dengan Dewi (2 hari absen tanpa
> keterangan) — kekurangan kehadiran sama, hasil payroll berlawanan.

---

## 8.4 Kalender Cuti — `/admin/leave-calendar`

| ID | Skenario | Langkah | Expected | Status |
|---|---|---|---|---|
| LVC-R-01 | Halaman termuat | Buka halaman | 200 | ✅ |
| LVC-R-02 | Warna per jenis | Amati kalender | Warna sesuai field `color` jenis cuti | ⬜ |
| LVC-R-03 | Navigasi bulan | Klik bulan berikut / sebelum | Data ikut berpindah | ⬜ |
| LVC-R-04 | Hanya yang disetujui | Bandingkan dengan pengajuan pending | Perilaku terdefinisi soal pending ditampilkan atau tidak | ⬜ |

## 8.5 Rekap Cuti — `/admin/leave-report`

| ID | Skenario | Langkah | Expected | Status |
|---|---|---|---|---|
| LVP-R-01 | Halaman termuat | Buka `/admin/leave-report` | 200 | ✅ |
| LVP-R-02 | Angka konsisten | Bandingkan terpakai vs Saldo Cuti | Cocok | ⬜ |
| LVP-R-03 | Filter periode | Ganti tahun | Angka menyesuaikan | ⬜ |

## AKSES modul

| ID | Role | Expected | Status |
|---|---|---|---|
| LV-A-01 | SA / HR | Punya `leave.*` termasuk `configure` & `manage_balance` | ✅ 200 |
| LV-A-02 | MGR | Punya `leave.view_all`, `leave.approve`, `leave.reject` — boleh menyetujui, ⚠️ tetapi juga bisa mengubah Jenis & Saldo Cuti yang seharusnya HR saja (DEF-03) | ⚠️ |
| LV-A-03 | EMP | Punya `leave.view_own` + `leave.request` — lewat portal `/my/leave` | 🌐 |
