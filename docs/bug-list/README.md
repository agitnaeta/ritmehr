# Bug List — Hasil Eksekusi Test Case

Hasil menjalankan test case CRUD lewat **browser sungguhan** (Chromium/Playwright)
terhadap aplikasi berjalan dengan data demo.

| | |
|---|---|
| **Harness** | [tests/browser/crud-suite.mjs](../../tests/browser/crud-suite.mjs) |
| **Hasil browser** | 114 PASS / 29 FAIL → **143 PASS / 0 FAIL** setelah perbaikan |
| **Hasil PHPUnit** | 149 lulus / 1 gagal → **150 lulus / 0 gagal** |
| **Bug unik** | **10** — 7 dari suite, 3 dari pemeriksaan manual area yang belum teruji |
| **Cara jalankan** | `php artisan serve` lalu `node tests/browser/crud-suite.mjs` |

Test case sumbernya ada di [../test-cases/](../test-cases/README.md).

---

## Status: **seluruh 11 bug sudah diperbaiki** ✅

BUG-008 s/d BUG-011 ditemukan pada putaran kedua dan ketiga, dengan memeriksa
area yang sebelumnya belum pernah diuji — bukan dari kegagalan suite.

| ID | Judul | Severity | Status | Berkas |
|---|---|---|---|---|
| **BUG-010** | Hash password tersimpan di audit log | 🔴 Kritis | ✅ **Diperbaiki** | [12-audit-log.md](12-audit-log.md) |
| **BUG-008** | Nominal kasbon menerima nol dan negatif | 🟠 Tinggi | ✅ **Diperbaiki** | [03-kasbon.md](03-kasbon.md) |
| **BUG-009** | Pembayaran kasbon bisa melebihi sisa tagihan | 🟠 Tinggi | ✅ **Diperbaiki** | [03-kasbon.md](03-kasbon.md) |
| **BUG-011** | Hapus kasbon bercicilan membuat karyawan terjebak | 🟠 Tinggi | ✅ **Diperbaiki** | [03-kasbon.md](03-kasbon.md) |
| **BUG-003** | Manager punya akses tulis penuh tanpa permission | 🔴 Kritis | ✅ **Diperbaiki** | [lintas-modul.md](lintas-modul.md) |
| **BUG-005** | 8 entity tanpa validasi server → HTTP 500 | 🔴 Kritis | ✅ **Diperbaiki** | [lintas-modul.md](lintas-modul.md) |
| **BUG-001** | Cetak slip gaji selalu 500 bila logo kosong | 🔴 Kritis | ✅ **Diperbaiki** | [04-penggajian.md](04-penggajian.md) |
| **BUG-004** | Scoping tim manager tidak diterapkan | 🟠 Tinggi | ✅ **Diperbaiki** | [lintas-modul.md](lintas-modul.md) |
| **BUG-006** | Pelanggaran unique constraint → HTTP 500 | 🟠 Tinggi | ✅ **Diperbaiki** | [lintas-modul.md](lintas-modul.md) |
| **BUG-002** | `set-payment` GET yang mengubah data, 500 tanpa `?method` | 🟡 Sedang | ✅ **Diperbaiki** | [04-penggajian.md](04-penggajian.md) |
| **BUG-007** | Test suite gagal setiap akhir pekan | 🟡 Sedang | ✅ **Diperbaiki** | [lintas-modul.md](lintas-modul.md#bug-007--test-suite-gagal-setiap-akhir-pekan) |

### Ringkasan perbaikan

| Bug | Yang dikerjakan |
|---|---|
| BUG-005 & BUG-006 | Validasi ditambahkan pada 8 entity, lengkap dengan aturan `unique` gabungan dan pesan berbahasa Indonesia. Mengikuti pola `store()`/`update()` → `validatePayload()` yang sudah dipakai `BranchCrudController` |
| BUG-001 | Guard di `SalaryRecapCrudController` kini memeriksa `$company->image` **mentah** sebelum diberi prefix, plus `is_file()` untuk berkas yang hilang |
| BUG-002 | Route jadi **POST**, `method` divalidasi `in:cash,transfer`, penulisan dibungkus `DB::transaction`, pembayaran ganda ditolak. Tombolnya jadi form POST ber-CSRF |
| BUG-003 | 6 permission baru (`branch.*`, `document.*`, `tax.*`) + middleware `permission:` per grup route + `denyAccess` di 9 controller + sidebar disaring per permission |
| BUG-004 | Scope `User::scopeVisibleTo()` — satu definisi tim dipakai daftar karyawan **dan** presensi. Permission baru `user.view_all` memisahkan "boleh buka menu" dari "boleh lihat semua" |
| BUG-007 | `$day` dihitung sebelum baris presensi dibuat, sehingga presensi dan snapshot selalu jatuh di hari yang sama |
| BUG-008 | `min:1` pada `amount` di `LoanRequest` dan `LoanPaymentRequest`, plus `user_id` diperketat jadi `exists:users,id` |
| BUG-009 | Aturan closure membandingkan nominal terhadap sisa sesungguhnya, mengecualikan baris yang sedang diedit |
| BUG-010 | Filter `auditableValues()` di trait `Auditable` memakai `$hidden` model; 5 entri lama dibersihkan tanpa kehilangan baris audit |
| BUG-011 | Guard di `LoanCrudController::destroy()` menolak (422) penghapusan kasbon yang cicilannya melebihi kasbon lain milik karyawan itu |

### Area yang kini sudah teruji dan **bersih**

Diperiksa manual pada putaran ketiga; tidak ada bug ditemukan:

| Area | Yang dibuktikan |
|---|---|
| **Keamanan dokumen** | Disk privat, URL tidak bisa ditebak (404 di 3 variasi jalur), karyawan lain tidak bisa mengunduh, HR bisa, hapus record ikut menghapus berkas fisik |
| **Perhitungan BPJS** | Plafon Kesehatan 12jt & JP 10.042.300 diterapkan, JHT tanpa plafon, JKK & JKM tidak memotong karyawan |
| **Perhitungan PPh 21** | Surcharge tanpa NPWP tepat 1,2×; gaji di bawah PTKP menghasilkan 0, tidak negatif |
| **Perhitungan THR** | Penuh ≥12 bulan, prorata 1–12 bulan, nihil <1 bulan |
| **Injeksi field portal** | `department_id`, `employment_status`, `manager_id`, `salary` dikirim paksa lewat POST → semuanya diabaikan |

## Bug per modul

| Modul | Bug | Berkas |
|---|---|---|
| Lintas modul | BUG-003, BUG-004, BUG-005, BUG-006, BUG-007 | [lintas-modul.md](lintas-modul.md) |
| 01 Users | BUG-003, BUG-004 | [01-users.md](01-users.md) |
| 02 Absensi | BUG-003, BUG-004, BUG-005 | [02-absensi.md](02-absensi.md) |
| 03 Kasbon | BUG-003 | [03-kasbon.md](03-kasbon.md) |
| 04 Penggajian | BUG-001, BUG-002, BUG-003 | [04-penggajian.md](04-penggajian.md) |
| 05 Profil Perusahaan | BUG-001 (akar), BUG-003 | [05-profil-perusahaan.md](05-profil-perusahaan.md) |
| 06 Akuntansi | BUG-003 | [06-akuntansi.md](06-akuntansi.md) |
| 07 Organisasi | BUG-003 | [07-organisasi.md](07-organisasi.md) |
| 08 Cuti & Izin | BUG-003, BUG-005, BUG-006 | [08-cuti.md](08-cuti.md) |
| 09 Dokumen | BUG-003 | [09-dokumen.md](09-dokumen.md) |
| 10 Pajak & BPJS | BUG-003, BUG-005, BUG-006 | [10-pajak-bpjs.md](10-pajak-bpjs.md) |
| 11 Persetujuan | — bersih | [11-persetujuan.md](11-persetujuan.md) |
| 12 Audit Log | BUG-003 (varian serius) | [12-audit-log.md](12-audit-log.md) |
| 13 Pengaturan | BUG-005 | [13-pengaturan.md](13-pengaturan.md) |
| 14 Portal Karyawan | — bersih | [14-portal-karyawan.md](14-portal-karyawan.md) |
| 15 Dashboard & Laporan | BUG-004 | [15-dashboard-laporan.md](15-dashboard-laporan.md) |

---

## Keputusan produk yang diambil saat perbaikan

Dua hal tidak bisa disimpulkan dari kode saja dan diputuskan bersama pemilik
produk:

**Manager tetap boleh membuka menu Users.** Seeder tidak memberi manager
`user.view`, sehingga penegakan permission apa adanya akan menutup menu Users
sepenuhnya — bertentangan dengan "Team visibility" di
[HRIS_SETUP.md](../HRIS_SETUP.md). Yang dipilih: beri `user.view`, lalu sempitkan
daftarnya ke tim. Manager tetap tidak bisa create, edit, maupun delete karyawan.

**"Tim" berarti bawahan langsung.** Karyawan yang `manager_id`-nya menunjuk ke
manager tersebut, plus dirinya sendiri — bukan sub-pohon departemen. Definisi ini
sama dengan yang sudah dipakai modul Persetujuan, dan tinggal satu kolom yang
perlu dibaca.

Konsekuensi yang perlu diketahui: `manager_id` kini menentukan visibilitas data,
bukan sekadar rantai persetujuan. Karyawan tanpa `manager_id` tidak akan muncul
di daftar manager mana pun.

---

## Yang **bukan** bug — sudah diverifikasi

Empat hal ini sempat terlihat seperti bug selama pengujian dan sudah dibuktikan
normal. Jangan dilaporkan ulang.

| Dugaan | Kenyataan |
|---|---|
| **Update tidak tersimpan** pada 8 entity | Harness mengirim PUT dengan payload parsial sehingga validasi menolak. Diuji ulang lewat form UI: **semua tersimpan**. Redirect ke `/edit` setelah simpan adalah perilaku normal Backpack, bukan penolakan |
| **Edit jadwal ditolak** "Kolom hari libur harus diisi" | `day_off` adalah **checkbox**, bukan select. Baris uji yang dibuat lewat POST mentah menyimpan nilai tidak sah. Jadwal yang dibuat lewat UI **bisa diedit normal** |
| **`UserRequest` mewajibkan foto & email unique tanpa ignore id** | Ada `updateRules($userId)` terpisah yang sudah benar — email mengabaikan id sendiri, password dan foto opsional saat edit. Perbedaan create vs update memang disengaja |
| **Cetak ID card dialihkan ke Profil Perusahaan** | Guard yang benar saat background ID card belum diunggah. Justru pola inilah yang seharusnya dipakai BUG-001 |

Selain itu, hal-hal berikut adalah perilaku yang disengaja:
`salary-recap/create` **403**, `leave-request/create` · `approval/create` ·
`audit-log/create` · `permission/create` semuanya **404**, employee dialihkan
dari seluruh `/admin/*` ke `/my`, dan dashboard menampilkan Rp 0 karena data
demo berada di bulan sebelumnya.

---

## Catatan pengujian

Suite ini **mengubah data** (create, update, delete sungguhan). Sebelum
menjalankan, buat cadangan:

```bash
docker exec absensi-mysql mysqldump -uroot -psecret --single-transaction absensi \
  > storage/app/backups/pre-crud-test.sql
```

Pulihkan setelah selesai:

```bash
docker exec -i absensi-mysql mysql -uroot -psecret absensi \
  < storage/app/backups/pre-crud-test.sql
```

Selama penyusunan dokumen ini database dipulihkan tiga kali; kondisi akhir sudah
diverifikasi identik dengan seed awal (users=5, schedule=1, branch=2, dept=4,
pos=4, leave-type=5, document-type=8, loan=1, presence=110, recap=5).
