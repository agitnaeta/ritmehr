# Modul 07 — Organisasi

Dropdown **Organisasi**: Cabang, Departemen, Jabatan, Struktur Organisasi.

---

## 7.1 Cabang — `/admin/branch`

| | |
|---|---|
| **Controller** | [BranchCrudController](../../app/Http/Controllers/Admin/BranchCrudController.php) |
| **Model / tabel** | `App\Models\Branch` / `branches` |
| **Validasi** | Inline di controller |
| **Operasi** | Create ✔ · Read ✔ · Update ✔ · Delete ✔ |

**Field:** `name`, `code`, `address`, `phone`, `lat`, `lng`, `radius_meters`,
`is_active`, `company_profile_id`

**Validasi:**

| Field | Aturan |
|---|---|
| `name` | `required\|string\|max:100` |
| `code` | `nullable\|string\|max:20\|unique:branches,code` |
| `lat` | `nullable\|numeric\|between:-90,90` |
| `lng` | `nullable\|numeric\|between:-180,180` |
| `radius_meters` | `nullable\|integer\|min:10\|max:100000` |

Cabang menggantikan geofence global: koordinat **dan** radius kini per cabang.

### CREATE / READ / UPDATE / DELETE

| ID | Skenario | Data | Expected | Status |
|---|---|---|---|---|
| BR-C-01 | Buka form | — | Form 9 field | ✅ 200 |
| BR-C-02 | Buat cabang | Nama + koordinat + radius 100 | Tersimpan | ✅ (diuji, data dihapus kembali) |
| BR-C-03 | Cabang tanpa koordinat | Kosongkan `lat`/`lng` | Tersimpan; geofence jatuh ke config global | ⬜ |
| BR-R-01 | List | Buka `/admin/branch` | 2 cabang pada data demo | ⬜ |
| BR-R-02 | Detail | `/admin/branch/1/show` | Koordinat & radius tampil | ⬜ |
| BR-U-01 | Ubah radius | 100 → 500 m | Scan berikutnya memakai radius baru | ⬜ |
| BR-U-02 | Pindahkan koordinat | Geser lat/lng | Presensi **lama tetap** memakai cabang yang tercatat di barisnya | ⬜ |
| BR-U-03 | Nonaktifkan | `is_active=0` | Tidak bisa dipilih untuk karyawan baru | ⬜ |
| BR-D-01 | Hapus cabang kosong | Delete cabang tanpa karyawan | Terhapus | ⬜ |
| BR-D-02 | Hapus cabang berpenghuni | Delete cabang yang punya karyawan & presensi | Ditolak atau relasi tertangani | ⬜ |

### VALIDASI

| ID | Skenario | Data | Expected | Status |
|---|---|---|---|---|
| BR-V-01 | Submit kosong | Semua kosong | ✅ 302 kembali ke form (validasi jalan) | ✅ |
| BR-V-02 | Nama terlalu panjang | 101 karakter | Ditolak — `max:100` | ⬜ |
| BR-V-03 | Kode duplikat | Kode yang sudah ada | Ditolak — `unique` | ⬜ |
| BR-V-04 | Latitude di luar rentang | `lat=95` | Ditolak — `between:-90,90` | ⬜ |
| BR-V-05 | Longitude di luar rentang | `lng=200` | Ditolak — `between:-180,180` | ⬜ |
| BR-V-06 | Radius terlalu kecil | `radius_meters=5` | Ditolak — `min:10` | ⬜ |
| BR-V-07 | Radius terlalu besar | `radius_meters=200000` | Ditolak — `max:100000` | ⬜ |
| BR-V-08 | Koordinat bukan angka | `lat=abc` | Ditolak — `numeric` | ⬜ |

### GEOFENCE

| ID | Skenario | Langkah | Expected | Status |
|---|---|---|---|---|
| BR-X-01 | Urutan resolusi | Cabang di baris presensi → cabang user → config global | Dipakai berurutan; yang pertama tersedia menang | ⬜ |
| BR-X-02 | Tanpa referensi mana pun | Kosongkan semua koordinat | Scan dianggap **on-site** — tidak menandai semua orang di luar radius | ⬜ |
| BR-X-03 | Transfer karyawan | Pindah cabang → hitung ulang presensi lama | Presensi lama tidak di-atribusi ulang ke cabang baru | ⬜ |
| BR-X-04 | Env dengan titik koma | `.env` berisi `LAT=-6.8493328;` | Di-cast sekali di `config/app.php` — tidak menghasilkan NaN | ⬜ |

---

## 7.2 Departemen — `/admin/department`

**Field:** `name`, `code`, `parent_id`, `head_user_id`
**Validasi:** `name` required·string·max:100 · `code` nullable·max:20·unique

| ID | Skenario | Data | Expected | Status |
|---|---|---|---|---|
| DEP-C-01 | Buka form | — | Form 4 field | ✅ 200 |
| DEP-C-02 | Departemen akar | Tanpa `parent_id` | Tersimpan sebagai level atas | ⬜ |
| DEP-C-03 | Sub-departemen | Set `parent_id` | Tampil bersarang di struktur organisasi | ⬜ |
| DEP-C-04 | Tetapkan kepala | Set `head_user_id` | Tampil di struktur organisasi | ⬜ |
| DEP-R-01 | List | Buka list | 4 departemen pada data demo | ⬜ |
| DEP-U-01 | Pindah induk | Ganti `parent_id` | Hierarki menyesuaikan | ⬜ |
| DEP-U-02 | **Induk = diri sendiri** | `parent_id` = departemen itu | **Ditolak** — cycle guard | ⬜ |
| DEP-U-03 | **Induk = keturunan** | Set induk ke anak / cucunya | **Ditolak** — cycle guard | ⬜ |
| DEP-D-01 | Hapus departemen kosong | Delete | Terhapus | ⬜ |
| DEP-D-02 | Hapus departemen beranak | Delete yang punya sub-departemen | Anak tertangani, tidak yatim | ⬜ |
| DEP-D-03 | Hapus departemen berkaryawan | Delete yang punya karyawan | Ditolak atau `department_id` karyawan dikosongkan | ⬜ |
| DEP-V-01 | Submit kosong | Semua kosong | ✅ 302 kembali ke form | ✅ |
| DEP-V-02 | Kode duplikat | Kode yang sudah ada | Ditolak | ⬜ |
| DEP-V-03 | Nama > 100 karakter | 101 karakter | Ditolak | ⬜ |
| DEP-X-01 | Data bersiklus di DB | Injeksi loop `parent_id` langsung ke DB → buka org chart | **Tidak hang** — `descendants()` tetap berhenti | ⬜ |

---

## 7.3 Jabatan — `/admin/position`

**Field:** `name`, `level`, `department_id`
**Validasi:** `name` required·string·max:100 · `level` nullable·integer·min:0·max:100

| ID | Skenario | Data | Expected | Status |
|---|---|---|---|---|
| POS-C-01 | Buka form | — | Form 3 field | ✅ 200 |
| POS-C-02 | Buat jabatan | "Supervisor", level 3 | Tersimpan | ⬜ |
| POS-C-03 | Jabatan lintas departemen | Kosongkan `department_id` | Tersimpan sebagai jabatan umum | ⬜ |
| POS-R-01 | List | Buka list | 4 jabatan pada data demo | ⬜ |
| POS-U-01 | Ubah level | Naikkan level | Urutan di struktur organisasi berubah | ⬜ |
| POS-D-01 | Hapus jabatan terpakai | Delete yang dipakai karyawan | Ditolak atau `position_id` dikosongkan | ⬜ |
| POS-V-01 | Submit kosong | Semua kosong | ✅ 302 kembali ke form | ✅ |
| POS-V-02 | Level negatif | `level=-1` | Ditolak — `min:0` | ⬜ |
| POS-V-03 | Level > 100 | `level=150` | Ditolak — `max:100` | ⬜ |
| POS-V-04 | Level bukan angka | `level=tinggi` | Ditolak — `integer` | ⬜ |

---

## 7.4 Struktur Organisasi — `/admin/org-chart`

Halaman baca-saja, tidak ada CRUD.

| ID | Skenario | Langkah | Expected | Status |
|---|---|---|---|---|
| ORG-R-01 | Halaman termuat | Buka `/admin/org-chart` | 200 | ✅ |
| ORG-R-02 | Hierarki benar | Amati bagan | Head Office → Teknologi / HRD / Operasional | ⬜ |
| ORG-R-03 | Kepala departemen | Amati tiap simpul | Nama kepala tampil bila `head_user_id` diisi | ⬜ |
| ORG-R-04 | Karyawan resigned | Set satu karyawan `resigned` | Tidak muncul di bagan aktif | ⬜ |
| ORG-R-05 | Departemen kosong | Departemen tanpa karyawan | Tetap tampil, tidak membuat bagan rusak | ⬜ |
| ORG-R-06 | Data bersiklus | Loop `parent_id` di DB | Halaman tetap terbuka, tidak hang | ⬜ |
| ORG-A-01 | Akses MGR | Login `budi@` | Punya `org.view` — boleh melihat | ✅ 200 |
| ORG-A-02 | Akses EMP | Login `ahmad@` | Dialihkan ke `/my` | 🌐 |
