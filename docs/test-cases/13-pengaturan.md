# Modul 13 — Pengaturan (super_admin saja)

Dropdown **Pengaturan**: Role, Permission, Alur Persetujuan, Step Persetujuan.

Dropdown ini hanya dirender bila `backpack_user()->hasRole('super_admin')`, dan
keempat controller memasang guard permission sungguhan — **satu-satunya bagian
aplikasi yang penegakan hak aksesnya lengkap**.

| Halaman | Guard | hr_admin | manager |
|---|---|:--:|:--:|
| `/admin/role` | `role.edit` | 403 | 403 |
| `/admin/permission` | `permission.view` | 403 | 403 |
| `/admin/approval-flow` | `approval.configure` | 403 | 403 |
| `/admin/approval-flow-step` | `approval.configure` | 403 | 403 |

> Spatie menyimpan role pada guard **`web`**, sedangkan Backpack mengautentikasi
> admin pada guard **`backpack`**. Karena itu `@role` / `@can` di Blade **selalu
> false** untuk admin yang sedang login. Di view admin gunakan
> `backpack_user()->hasRole(...)` / `->can(...)`.

---

## 13.1 Role — `/admin/role`

**Field:** `name`, `guard_name`, `permissions`
**Model:** `App\Models\Role` — subclass Spatie yang menambahkan `CrudTrait`

| ID | Skenario | Data | Expected | Status |
|---|---|---|---|---|
| ROL-C-01 | Buka form | Login `siti@` | 200; 3 field | ✅ |
| ROL-C-02 | Buat role baru | `name=supervisor`, guard `web`, pilih permission | Tersimpan | ⬜ |
| ROL-C-03 | Guard benar | Cek `guard_name` tersimpan | **`web`** — bukan `backpack` | ⬜ |
| ROL-R-01 | List | Buka list | 4 role: super_admin, hr_admin, manager, employee | ⬜ |
| ROL-R-02 | Jumlah permission | Amati tiap role | SA 54 · HR 50 · MGR 14 · EMP 9 | ✅ |
| ROL-R-03 | Detail | Buka detail role | Semua permission tercentang tampil | ⬜ |
| ROL-U-01 | Tambah permission | Centang permission baru → Save | Berlaku setelah user terkait login ulang | ⬜ |
| ROL-U-02 | Cabut permission | Hapus centang → Save | Akses user terkait ikut hilang | ⬜ |
| ROL-U-03 | Ubah nama role | Ganti `name` | Middleware `role:...` yang merujuk nama lama ikut disesuaikan | ⬜ |
| ROL-U-04 | Cabut permission diri sendiri | super_admin mencabut `role.edit` dari super_admin | ⚠️ Bisa mengunci diri — pastikan ada pengaman | ⬜ |
| ROL-D-01 | Hapus role kosong | Delete role tanpa pengguna | Terhapus | ⬜ |
| ROL-D-02 | Hapus role terpakai | Delete role yang punya pengguna | Ditolak, atau pengguna tertangani — **jangan** sampai user kehilangan semua role dan malah dianggap admin (lihat catatan di bawah) | ⬜ |
| ROL-V-01 | Nama duplikat | Buat role dengan nama yang sudah ada | Ditolak | ⬜ |
| ROL-V-02 | Nama kosong | Submit tanpa `name` | Ditolak | ⬜ |
| ROL-A-01 | Akses HR | Login `rina@` → `/admin/role` | **403** | ✅ |
| ROL-A-02 | Akses MGR | Login `budi@` → `/admin/role` | **403** | ✅ |
| ROL-A-03 | Menu tersembunyi | Login `rina@` / `budi@` | Dropdown Pengaturan tidak ada di DOM | 🌐 |
| ROL-A-04 | Menu tampil untuk SA | Login `siti@` | Dropdown Pengaturan tampil | 🌐 |

> **Catatan penting.** `CheckIfAdmin` memperlakukan user **tanpa role sama
> sekali** sebagai admin, agar akun yang ada sebelum upgrade role tidak
> terkunci. Konsekuensinya: mencabut seluruh role dari seseorang justru
> **memperluas** aksesnya, bukan mempersempit. Uji ini di ROL-D-02.

---

## 13.2 Permission — `/admin/permission`

**Operasi:** Read-only (List ✔ · Show ✔; Create/Update/Delete ✖)

| ID | Skenario | Langkah | Expected | Status |
|---|---|---|---|---|
| PRM-C-01 | Create ditutup | `/admin/permission/create` | **404** | ✅ |
| PRM-R-01 | List | Buka `/admin/permission` | 54 permission tampil | ⬜ |
| PRM-R-02 | Pengelompokan | Amati penamaan | Berpola `<modul>.<aksi>` — `user.view`, `leave.approve`, dst. | ⬜ |
| PRM-R-03 | Detail | Buka detail | Nama, guard, tanggal | ⬜ |
| PRM-U-01 | Edit ditutup | Coba edit | 404 | ⬜ |
| PRM-D-01 | Delete ditutup | Coba hapus | 404 | ⬜ |
| PRM-A-01 | Akses HR | Login `rina@` | **403** | ✅ |
| PRM-A-02 | Akses MGR | Login `budi@` | **403** | ✅ |

---

## 13.3 Alur Persetujuan — `/admin/approval-flow`

**Field:** `name`, `module`, `is_active`, `steps`
Modul yang dikenal: `leave`, `loan`, `overtime`

⚠️ **Tanpa validasi server** — lihat AFL-V-01.

| ID | Skenario | Data | Expected | Status |
|---|---|---|---|---|
| AFL-C-01 | Buka form | Login `siti@` | 200; 4 field | ✅ |
| AFL-C-02 | Buat flow | `module=leave`, aktif, 2 langkah | Tersimpan | ⬜ |
| AFL-R-01 | List | Buka list | 2 flow pada data demo | ⬜ |
| AFL-U-01 | Nonaktifkan flow | `is_active=0` | Pengajuan baru gagal dengan `RuntimeException` yang jelas | ⬜ |
| AFL-U-02 | Ganti modul | leave → loan | Approval berikutnya mengikuti flow baru | ⬜ |
| AFL-D-01 | Hapus flow terpakai | Delete flow yang punya approval berjalan | Approval berjalan tidak jadi yatim | ⬜ |
| AFL-V-01 | **Submit kosong** | Semua kosong | ⚠️ **GAGAL — 500** `Field 'name' doesn't have a default value` | ✅ ⚠️ |
| AFL-V-02 | **Dua flow aktif satu modul** | Aktifkan dua flow `leave` | **Ditolak** — satu flow aktif per modul | ⬜ |
| AFL-V-03 | Flow tanpa langkah | Aktifkan flow kosong → ajukan cuti | `RuntimeException` dengan pesan konfigurasi | ⬜ |
| AFL-V-04 | Modul tidak dikenal | `module=xyz` | Ditolak | ⬜ |
| AFL-A-01 | Akses HR | Login `rina@` | **403** — HR boleh bertindak atas approval, tidak boleh mengubah alurnya | ✅ |
| AFL-A-02 | Akses MGR | Login `budi@` | **403** | ✅ |

---

## 13.4 Step Persetujuan — `/admin/approval-flow-step`

**Field:** `approval_flow_id`, `step_order`, `approver_type`,
`approver_role_id`, `approver_user_id`

`approver_type` menentukan siapa yang menyetujui: berdasarkan **role**,
berdasarkan **manager** pengaju, atau **user tertentu**.

⚠️ **Tanpa validasi server** — lihat AFS-V-01.

| ID | Skenario | Data | Expected | Status |
|---|---|---|---|---|
| AFS-C-01 | Buka form | Login `siti@` | 200; 5 field | ✅ |
| AFS-C-02 | Langkah by role | `approver_type=role`, pilih hr_admin | Tersimpan | ⬜ |
| AFS-C-03 | Langkah by manager | `approver_type=manager` | Atasan langsung pengaju jadi approver | ⬜ |
| AFS-C-04 | Langkah by user | `approver_type=user`, pilih Rina | Hanya Rina yang berwenang | ⬜ |
| AFS-R-01 | List | Buka list | 4 langkah pada data demo | ⬜ |
| AFS-U-01 | Ubah urutan | Tukar `step_order` | Eksekusi mengikuti urutan baru | ⬜ |
| AFS-U-02 | Ubah approver di tengah proses | Ganti approver saat approval sedang berjalan | Otorisasi memakai langkah **saat lock diambil** — perubahan tidak membatalkan aksi yang sedang berlangsung | ⬜ |
| AFS-D-01 | Hapus langkah tengah | Delete langkah 2 dari 3 | Urutan tetap konsisten, tidak ada celah | ⬜ |
| AFS-V-01 | **Submit kosong** | Semua kosong | ⚠️ **GAGAL — 500** `Field 'approval_flow_id' doesn't have a default value` | ✅ ⚠️ |
| AFS-V-02 | `step_order` duplikat | Dua langkah nomor sama dalam satu flow | Ditolak | ⬜ |
| AFS-V-03 | Tipe role tanpa role | `approver_type=role`, `approver_role_id` kosong | Ditolak | ⬜ |
| AFS-V-04 | Tipe user tanpa user | `approver_type=user`, `approver_user_id` kosong | Ditolak | ⬜ |
| AFS-V-05 | Approver = pengaju | Langkah yang menunjuk pengaju sendiri | Ditolak atau perilaku terdefinisi | ⬜ |
| AFS-V-06 | Manager tidak diset | `approver_type=manager` tapi pengaju tanpa `manager_id` | Pesan jelas, bukan 500 | ⬜ |
| AFS-A-01 | Akses HR / MGR | Login `rina@` / `budi@` | **403** | ✅ |
