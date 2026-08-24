# Bug List — Modul 06 Konfigurasi Akuntansi

Test case: [../test-cases/06-akuntansi.md](../test-cases/06-akuntansi.md)

| Hasil suite | 0 PASS / 1 FAIL |
|---|---|
| Bug lintas modul | BUG-003 |

Tidak ada bug khusus modul ini. `AccRequest` sudah benar, termasuk pola
`unique:accs,code,<id>` untuk update.

---

## BUG-003 — Manager bisa mengubah pemetaan akun jurnal

| | |
|---|---|
| **Severity** | 🔴 Kritis |
| **Test case** | `acc/A-mgr-write` |

Login `budi@demo.test` → `/admin/acc/create` → **HTTP 200**.

Role `manager` tidak punya `acc.view` maupun `acc.edit`.

Modul ini memetakan peristiwa payroll ke akun jurnal eksternal. Mengubah
`source_id` atau `destination_id` berarti **mengalihkan ke mana uang gaji
dibukukan**. Saat `ACC_ACTIVE` aktif, setiap pembayaran rekap gaji mengirim
transaksi `WITHDRAWAL` mengikuti pemetaan ini.

Saat ini dampaknya tertahan karena `ACC_ACTIVE` tidak diset di `.env` dan tabel
`accs` kosong — tetapi itu kondisi lingkungan, bukan pengaman.

Perbaikan: [lintas-modul.md § BUG-003](lintas-modul.md#bug-003--manager-punya-akses-tulis-penuh-tanpa-permission).

---

## Yang sudah benar di modul ini

| Perilaku | Status |
|---|---|
| Form create terbuka | ✅ 200 |
| Form kosong ditolak validasi | ✅ |
| `code` unique dengan ignore id saat update | ✅ pola benar |
| Tanpa `ACC_ACTIVE`, pembayaran gaji tidak kirim transaksi eksternal | ✅ terverifikasi |
| Employee dialihkan ke `/my` | ✅ |

---

## Belum teruji — integrasi

Tabel `accs` kosong dan `ACC_ACTIVE` tidak diset, sehingga seluruh jalur
integrasi belum tersentuh. Untuk mengujinya perlu penyiapan lebih dulu:

```bash
# .env
ACC_ACTIVE=true
```

lalu buat konfigurasi dengan `code=GAJIAN`.

| Test case | Hal yang perlu dipastikan |
|---|---|
| `ACC-X-02` | Transaksi `WITHDRAWAL` tercatat sebesar `received` |
| `ACC-X-03` | Rekap tanpa `acc_id` → `recordSalaryToACC()` dipanggil, `acc_id` terisi |
| `ACC-X-04` | Rekap yang sudah punya `acc_id` → **tidak** membuat catatan ganda |
| `ACC-X-05` | Gateway gagal → pembayaran gaji **tidak boleh rollback**, kegagalan tercatat di log |
| `ACC-D-02` | Kode `GAJIAN` dihapus lalu bayar → gagal dengan pesan jelas, tidak setengah jalan |

`ACC-X-04` dan `ACC-X-05` paling perlu perhatian. Keduanya menyangkut integritas
keuangan, dan
[TransactionService::updateRecordSalaryToACC()](../../app/Services/TransactionService.php#L59)
memang punya percabangan `acc_id == null` yang belum pernah dijalankan dalam
pengujian mana pun.
