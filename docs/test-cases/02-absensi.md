# Modul 02 — Absensi

Mencakup empat submodul di dropdown **Absen**: Scan, Jadwal, Setting Jadwal,
Kehadiran, dan Libur Nasional.

---

## 2.1 Kehadiran — `/admin/presence`

| | |
|---|---|
| **Controller** | [PresenceCrudController](../../app/Http/Controllers/Admin/PresenceCrudController.php) |
| **Model / tabel** | `App\Models\Presence` / `presences` |
| **Validasi** | [PresenceRequest](../../app/Http/Requests/PresenceRequest.php) |
| **Observer** | [PresenceObserver](../../app/Observers/PresenceObserver.php) — hitung geofence, telat, lembur |
| **Operasi** | Create ✔ · Read ✔ · Update ✔ · Delete ✔ |

**Field:** `user_id`, `in`, `out`, `is_late`, `late_minute`, `is_overtime`,
`extra_time`, `outside`, `branch_id`

**Validasi:** `user_id` required·string · `in` required · `out` required ·
`is_overtime` required·boolean

### CREATE

| ID | Skenario | Data | Expected | Status |
|---|---|---|---|---|
| PRES-C-01 | Buka form | — | Form tampil 9 field | ✅ 200 |
| PRES-C-02 | Input manual lengkap | user + in + out + is_overtime | Tersimpan | ⬜ |
| PRES-C-03 | Geofence dihitung saat create | Simpan baris dengan koordinat di dalam radius | `outside=0`. **Regresi lama:** observer dulu hanya menghitung saat *update*, sehingga baris hasil import/seeder selamanya ditandai di luar radius | ⬜ |
| PRES-C-04 | Telat dihitung otomatis | `in` melewati jam masuk jadwal | `is_late=1`, `late_minute` terisi | ⬜ |
| PRES-C-05 | Lembur dihitung otomatis | `out` melewati `over_in` | `is_overtime=1`, `extra_time` terisi | ⬜ |
| PRES-C-06 | User tanpa jadwal | Pilih user yang `schedule_id` kosong | **Tidak boleh error** — `calculateExtraTime()` pernah crash di sini | ⬜ |
| PRES-C-07 | Cabang melekat | Simpan dengan `branch_id` tertentu | Tersimpan pada baris, tidak diambil ulang dari profil user | ⬜ |

### READ

| ID | Skenario | Langkah | Expected | Status |
|---|---|---|---|---|
| PRES-R-01 | List termuat | Buka `/admin/presence` | "Menampilkan 1 hingga 10 dari **110** masukan." | 🌐 |
| PRES-R-02 | Badge geofence | Amati kolom radius | **Nol** teks "Di Luar Radius"; seluruh 110 baris `outside=0` | 🌐 |
| PRES-R-03 | Detail | `/admin/presence/1/show` | Jam masuk, keluar, telat, lembur, cabang tampil | ⬜ |
| PRES-R-04 | Filter tanggal | Filter bar rentang tanggal | Hasil menyempit | ⬜ |
| PRES-R-05 | Filter karyawan | Filter per user | Hanya baris user tersebut | ⬜ |

### UPDATE

| ID | Skenario | Data | Expected | Status |
|---|---|---|---|---|
| PRES-U-01 | Ubah jam masuk | Geser `in` ke lebih siang | `is_late` & `late_minute` **terhitung ulang** | ⬜ |
| PRES-U-02 | Ubah jam keluar | Geser `out` melewati jam lembur | `is_overtime` & `extra_time` terhitung ulang | ⬜ |
| PRES-U-03 | Koreksi koordinat | Ubah lat/lng ke luar radius | `outside` berubah jadi 1 | ⬜ |
| PRES-U-04 | Ubah cabang | Ganti `branch_id` | Geofence dievaluasi ulang terhadap cabang baru | ⬜ |
| PRES-U-05 | Dampak ke rekap gaji | Edit presensi lalu hitung ulang rekap | Angka gaji ikut berubah | ⬜ |

### DELETE

| ID | Skenario | Langkah | Expected | Status |
|---|---|---|---|---|
| PRES-D-01 | Hapus satu baris | Delete | Terhapus; rekap gaji bulan itu perlu dihitung ulang | ⬜ |
| PRES-D-02 | Konfirmasi | Klik Delete | Dialog konfirmasi muncul | ⬜ |

### VALIDASI

| ID | Skenario | Data | Expected | Status |
|---|---|---|---|---|
| PRES-V-01 | Submit kosong | Semua kosong | ✅ 302 kembali ke form, tidak tersimpan | ✅ |
| PRES-V-02 | Tanpa `user_id` | Hanya jam | Ditolak | ⬜ |
| PRES-V-03 | Tanpa jam keluar | `out` kosong | Ditolak — `out` wajib | ⬜ |
| PRES-V-04 | `out` lebih awal dari `in` | `in=17:00`, `out=08:00` | Perilaku terdefinisi, durasi tidak negatif | ⬜ |
| PRES-V-05 | Presensi ganda | Dua baris user & tanggal sama | Ditolak atau perilaku terdefinisi | ⬜ |

### AKSES

| ID | Role | Expected | Status |
|---|---|---|---|
| PRES-A-01 | SA / HR | Akses penuh | ✅ 200 |
| PRES-A-02 | MGR | ⚠️ Melihat **110 dari 110** baris — belum ter-scope tim (DEF-04) | ⚠️ |
| PRES-A-03 | EMP | Dialihkan ke `/my`; lihat riwayat sendiri di `/my/attendance` | 🌐 |

---

## 2.2 Scan QR — `/admin/presence/scan` dan `/scan` (publik)

| ID | Skenario | Langkah | Expected | Status |
|---|---|---|---|---|
| SCAN-R-01 | Halaman scan admin | Buka `/admin/presence/scan` | 200 | ✅ |
| SCAN-R-02 | Halaman scan publik | Buka `/scan` tanpa login | 200 — memang publik | ✅ |
| SCAN-R-03 | Root redirect | Buka `/` | 302 → `/scan` | ✅ |
| SCAN-R-04 | Elemen scanner | Inspeksi DOM | `#preview`, `#audioPlayer`, `#audioPlayerFailed` ada; kamera ditolak tidak membuat halaman crash | 🌐 |
| SCAN-C-01 | Scan QR valid | Arahkan QR karyawan | Presensi tercatat, audio sukses berbunyi, jam tampil di `#time` | ⬜ |
| SCAN-C-02 | Scan QR asing | QR tidak dikenal | Ditolak, audio gagal berbunyi | ⬜ |
| SCAN-C-03 | Scan kedua = check-out | Scan ulang orang yang sama | Mengisi kolom `out`, **bukan** membuat baris baru | ⬜ |
| SCAN-C-04 | Scan ketiga | Scan lagi setelah check-out | Perilaku terdefinisi (ditolak atau memperbarui) | ⬜ |
| SCAN-C-05 | Di dalam radius | Mock GPS di dalam radius cabang | `outside=0` | ⬜ |
| SCAN-C-06 | Di luar radius | Mock GPS jauh | `outside=1`, ditandai "Di Luar Radius" | ⬜ |
| SCAN-C-07 | Radius per cabang | Cabang A 50 m, Cabang B 500 m | Tiap karyawan diukur dengan radius cabangnya sendiri | ⬜ |
| SCAN-C-08 | Tanpa titik referensi | Kosongkan koordinat cabang **dan** config global | Scan dianggap **on-site** — tidak menandai semua orang di luar | ⬜ |
| SCAN-C-09 | Izin GPS ditolak | Tolak izin lokasi | Pesan jelas, tidak crash | ⬜ |

---

## 2.3 Jadwal — `/admin/schedule`

**Field:** `name`, `in`, `out`, `over_in`, `over_out`, `day_off`
**Validasi:** keenam field `required`

| ID | Skenario | Data | Expected | Status |
|---|---|---|---|---|
| SCH-C-01 | Buka form | — | Form 6 field | ✅ 200 |
| SCH-C-02 | Tambah jadwal | "Reguler 08-17" | Tersimpan | ⬜ |
| SCH-C-03 | Shift malam | `in=22:00`, `out=06:00` | Tersimpan; durasi lintas hari benar | ⬜ |
| SCH-R-01 | List | Buka list | Semua jadwal tampil | ⬜ |
| SCH-U-01 | Ubah jam | Geser `in` | Presensi **baru** memakai jadwal baru; presensi lama tidak berubah retroaktif kecuali dihitung ulang | ⬜ |
| SCH-U-02 | Ubah `day_off` | Set hari libur | Hari tsb tidak dihitung absen di rekap gaji | ⬜ |
| SCH-D-01 | Hapus jadwal terpakai | Hapus jadwal yang dipakai karyawan | Ditolak atau karyawan tidak jadi error | ⬜ |
| SCH-V-01 | Submit kosong | Semua kosong | ✅ 302 kembali ke form | ✅ |
| SCH-V-02 | Format jam salah | `in=25:00` | Ditolak | ⬜ |

---

## 2.4 Setting Jadwal (mass update) — `/admin/schedule/view-update`

| ID | Skenario | Langkah | Expected | Status |
|---|---|---|---|---|
| SCHM-R-01 | Halaman termuat | Buka halaman | 200 | ✅ |
| SCHM-U-01 | Mass update | Pilih beberapa karyawan → set jadwal → Simpan | `POST /admin/schedule/mass-update`; semua terpilih berubah | ⬜ |
| SCHM-U-02 | Tanpa memilih | Submit tanpa pilihan | Tidak error, tidak ada perubahan | ⬜ |
| SCHM-U-03 | Pilih semua | Centang semua karyawan | Semua berubah dalam satu transaksi | ⬜ |
| SCHM-A-01 | Hak akses | Login MGR | ⚠️ Terbuka meski tidak punya `schedule.mass_update` (DEF-03) | ⬜ |

---

## 2.5 Libur Nasional — `/admin/national-holiday`

**Field:** `date`, `info`

| ID | Skenario | Data | Expected | Status |
|---|---|---|---|---|
| HOL-C-01 | Buka form | — | Form 2 field | ✅ 200 |
| HOL-C-02 | Tambah libur | `date=2026-08-17`, `info=HUT RI` | Tersimpan | ⬜ |
| HOL-R-01 | List | Buka list | Daftar tanggal libur | ⬜ |
| HOL-U-01 | Ubah tanggal | Geser tanggal | Perhitungan cuti & gaji ikut menyesuaikan | ⬜ |
| HOL-D-01 | Hapus libur | Delete | Hari itu kembali dihitung hari kerja | ⬜ |
| HOL-V-01 | **Submit kosong** | `date` & `info` kosong | ⚠️ **GAGAL — HTTP 500**: `SQLSTATE[HY000] 1364 Field 'date' doesn't have a default value`. Seharusnya pesan validasi | ✅ ⚠️ |
| HOL-V-02 | Tanggal duplikat | Tanggal sama dua kali | Ditolak atau tidak menggandakan efek | ⬜ |
| HOL-V-03 | Format tanggal salah | `date=abc` | Ditolak dengan pesan, bukan 500 | ⬜ |
| HOL-X-01 | Tidak dihitung absen | Rekap gaji bulan berisi libur | Hari libur bukan potongan | ⬜ |
| HOL-X-02 | Tidak mengurangi kuota cuti | Ajukan cuti melintasi libur | Hari libur tidak mengurangi kuota | ⬜ |

> **Akar masalah HOL-V-01:** seluruh aturan di
> [NationalHolidayRequest.php](../../app/Http/Requests/NationalHolidayRequest.php)
> dikomentari, sehingga `rules()` mengembalikan array kosong. Aturan yang
> dikomentari pun menyebut `name`, padahal form memakai `date` dan `info`.
