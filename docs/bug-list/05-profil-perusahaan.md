# Bug List — Modul 05 Profil Perusahaan

Test case: [../test-cases/05-profil-perusahaan.md](../test-cases/05-profil-perusahaan.md)

| Hasil suite | 0 PASS / 1 FAIL |
|---|---|
| Bug lintas modul | BUG-003 |
| Terkait | **BUG-001** — modul ini adalah pemicunya |

---

## BUG-001 — Modul ini adalah pemicu matinya cetak slip gaji

| | |
|---|---|
| **Severity** | 🔴 Kritis |
| **Bug utama** | [04-penggajian.md § BUG-001](04-penggajian.md#bug-001--cetak-slip-gaji-selalu-http-500-bila-logo-perusahaan-kosong) |

Kolom `company_profiles.image` bernilai **NULL** pada data demo, dan itulah yang
membuat seluruh cetak slip gaji berakhir HTTP 500.

Kondisi terverifikasi:

```
image = NULL    id_card = NULL
```

Karena `image` wajib (`required`) saat **create** tetapi opsional saat
**update**, sebuah profil bisa berakhir tanpa logo lewat dua jalan: dibuat oleh
seeder yang melewati validasi, atau logonya dikosongkan lewat edit. Data demo
menempuh jalan pertama.

### Yang perlu diperbaiki di sisi modul ini

Perbaikan utamanya ada di `SalaryRecapCrudController`, tetapi modul ini bisa
membantu mencegah kondisi tersebut:

1. **Jangan izinkan logo dikosongkan lewat update** bila fitur cetak
   membutuhkannya — atau pastikan konsumennya tahan terhadap logo kosong
   (pendekatan yang disarankan).
2. **Beri peringatan di UI** saat `image` atau `id_card` kosong, dengan tautan
   ke halaman yang terdampak. Aplikasi sudah melakukan hal serupa untuk ID card:
   cetak ID card mengalihkan pengguna ke halaman ini alih-alih meledak.

### Kontras yang menarik

Dua fitur cetak, dua guard, satu benar satu salah:

| Fitur | Guard | Hasil saat gambar kosong |
|---|---|---|
| Cetak ID card | Memeriksa `id_card` **mentah** | ✅ 302 ke Profil Perusahaan |
| Cetak slip gaji | Memeriksa path **setelah** diberi prefix | ❌ 500 |

Pola yang benar sudah ada di dalam aplikasi ini. BUG-001 tinggal mengikutinya.

---

## BUG-003 — Manager bisa mengubah profil perusahaan

| | |
|---|---|
| **Severity** | 🔴 Kritis |
| **Test case** | `company-profile/A-mgr-write` |

Login `budi@demo.test` → `/admin/company-profile/create` → **HTTP 200**.

Role `manager` tidak punya `company_profile.view` maupun `company_profile.edit`.
Yang bisa diubah: nama perusahaan, alamat, telepon, logo, dan background ID
card — semuanya muncul di dokumen resmi seperti slip gaji dan kartu karyawan.

Perbaikan: [lintas-modul.md § BUG-003](lintas-modul.md#bug-003--manager-punya-akses-tulis-penuh-tanpa-permission).

---

## Yang sudah benar di modul ini

| Perilaku | Status |
|---|---|
| Halaman list terbuka | ✅ 200 |
| Form kosong ditolak validasi | ✅ |
| `image` wajib saat create, opsional saat update | ✅ pemisahan benar |
| Employee dialihkan ke `/my` | ✅ |

---

## Belum teruji

| Test case | Hambatan |
|---|---|
| `COMP-C-02`…`C-04` create dengan gambar | Butuh unggah berkas |
| `COMP-U-02`…`U-06` ganti/hapus logo | Butuh unggah berkas |
| `COMP-V-03`…`V-05` format berkas salah | Butuh berkas uji `.pdf`, `.gif`, `.svg` |
| `COMP-X-02` cetak slip **dengan** logo | Perlu setelah BUG-001 diperbaiki — untuk memastikan perbaikannya tidak hanya menyembunyikan gejala |
| `COMP-C-04` profil kedua | Aplikasi memakai `CompanyProfile::first()`; perilaku bila ada lebih dari satu belum terdefinisi |
