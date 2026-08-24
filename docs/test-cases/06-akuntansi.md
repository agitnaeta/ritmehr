# Modul 06 — Konfigurasi Akuntansi

| | |
|---|---|
| **URL** | `/admin/acc` |
| **Controller** | [AccCrudController](../../app/Http/Controllers/Admin/AccCrudController.php) |
| **Model / tabel** | `App\Models\Acc` / `accs` |
| **Validasi** | [AccRequest](../../app/Http/Requests/AccRequest.php) |
| **Service** | [TransactionService](../../app/Services/TransactionService.php) |
| **Operasi** | Create ✔ · Read ✔ · Update ✔ · Delete ✔ |

**Field:** `code`, `source_id`, `destination_id`

**Validasi:**

| Field | Create | Update |
|---|---|---|
| `code` | `required\|unique:accs` | `required\|unique:accs,code,<id>` |
| `source_id` | `required` | `required` |
| `destination_id` | `required` | `required` |

Modul ini memetakan peristiwa payroll ke akun jurnal eksternal. Integrasi
dikendalikan variabel lingkungan **`ACC_ACTIVE`** — bila tidak diset,
`TransactionService` keluar lebih awal dan tidak mengirim apa pun.

> Pada data demo `ACC_ACTIVE` **tidak diset** dan tabel `accs` **kosong**
> (0 baris). Jadi seluruh test case integrasi di bawah perlu penyiapan dulu.

---

## CREATE

| ID | Skenario | Data | Expected | Status |
|---|---|---|---|---|
| ACC-C-01 | Buka form | — | Form 3 field | ✅ 200 |
| ACC-C-02 | Buat konfigurasi GAJIAN | `code=GAJIAN`, sumber & tujuan terisi | Tersimpan | ⬜ |
| ACC-C-03 | Buat konfigurasi kasbon | `code` lain | Tersimpan berdampingan | ⬜ |

## READ

| ID | Skenario | Langkah | Expected | Status |
|---|---|---|---|---|
| ACC-R-01 | List | Buka `/admin/acc` | Tabel AJAX; kosong pada data demo | ⬜ |
| ACC-R-02 | Detail | `/admin/acc/1/show` | Kode, sumber, tujuan tampil sebagai nama | ⬜ |

## UPDATE

| ID | Skenario | Data | Expected | Status |
|---|---|---|---|---|
| ACC-U-01 | Ubah akun tujuan | Ganti `destination_id` | Transaksi berikutnya memakai akun baru | ⬜ |
| ACC-U-02 | Simpan tanpa ubah kode | Save tanpa menyentuh `code` | **Berhasil** — unique mengabaikan id sendiri | ⬜ |
| ACC-U-03 | Ubah kode | Ganti `code` ke nilai baru | Tersimpan; pastikan kode yang dirujuk service ikut disesuaikan | ⬜ |

## DELETE

| ID | Skenario | Langkah | Expected | Status |
|---|---|---|---|---|
| ACC-D-01 | Hapus konfigurasi | Delete | Terhapus | ⬜ |
| ACC-D-02 | Hapus kode terpakai | Hapus `GAJIAN` lalu bayar rekap gaji dengan `ACC_ACTIVE=true` | Gagal dengan pesan jelas, **pembayaran tidak setengah jalan** | ⬜ |

## VALIDASI

| ID | Skenario | Data | Expected | Status |
|---|---|---|---|---|
| ACC-V-01 | Submit kosong | Semua kosong | ✅ 302 kembali ke form | ✅ |
| ACC-V-02 | Kode duplikat | Buat `GAJIAN` dua kali | Ditolak — `unique:accs` | ⬜ |
| ACC-V-03 | Tanpa sumber | `source_id` kosong | Ditolak | ⬜ |
| ACC-V-04 | Tanpa tujuan | `destination_id` kosong | Ditolak | ⬜ |
| ACC-V-05 | Sumber = tujuan | Pilih akun yang sama | Ditolak atau perilaku terdefinisi | ⬜ |

## AKSES

| ID | Role | Expected | Status |
|---|---|---|---|
| ACC-A-01 | SA / HR | Punya `acc.view` + `acc.edit` | ✅ 200 |
| ACC-A-02 | MGR | ⚠️ Tidak punya permission `acc.*`, tetapi form terbuka (DEF-03) | ⚠️ |
| ACC-A-03 | EMP | Dialihkan ke `/my` | 🌐 |

## INTEGRASI

| ID | Skenario | Langkah | Expected | Status |
|---|---|---|---|---|
| ACC-X-01 | Integrasi nonaktif | `ACC_ACTIVE` tidak diset → bayar rekap gaji | Pembayaran berhasil, **tidak** ada transaksi eksternal terkirim | ✅ |
| ACC-X-02 | Integrasi aktif | `ACC_ACTIVE=true` → bayar rekap gaji | Transaksi `WITHDRAWAL` tercatat sebesar `received` | ⬜ |
| ACC-X-03 | Rekap belum punya `acc_id` | Bayar rekap pertama kali | `recordSalaryToACC()` dipanggil, `acc_id` terisi | ⬜ |
| ACC-X-04 | Rekap sudah punya `acc_id` | Bayar ulang rekap yang sama | Cabang update dijalankan, bukan membuat catatan baru — pastikan tidak dobel | ⬜ |
| ACC-X-05 | Gateway gagal | Matikan endpoint akuntansi → bayar | Pembayaran gaji **tidak boleh rollback** hanya karena akuntansi gagal; kegagalan tercatat di log | ⬜ |
| ACC-X-06 | Nominal terkirim | Bandingkan nominal transaksi vs `received` | Sama persis | ⬜ |
