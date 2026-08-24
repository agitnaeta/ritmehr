# Bug List — Modul 09 Dokumen

Test case: [../test-cases/09-dokumen.md](../test-cases/09-dokumen.md)

| Hasil suite | 8 PASS / 1 FAIL |
|---|---|
| Bug lintas modul | BUG-003 |

Tidak ada bug khusus modul ini. `DocumentTypeCrudController` punya validasi
inline yang lengkap dan lulus seluruh siklus CRUD.

---

## BUG-003 — Manager bisa mengubah aturan jenis dokumen

| | |
|---|---|
| **Severity** | 🔴 Kritis |
| **Test case** | `document-type/A-mgr-write` |

Login `budi@demo.test` → `/admin/document-type/create` → **HTTP 200**.

Modul ini **tidak punya permission sendiri** — tidak ada `document.*` di daftar
54 permission, sehingga tidak ada satu pun role yang secara eksplisit berhak,
namun semua admin bisa masuk.

Yang bisa diubah manager:

- `allowed_extensions` — melonggarkannya berarti berkas yang sebelumnya ditolak
  jadi bisa diunggah
- `max_file_size_mb` — sampai 100 MB
- `is_required` — mematikannya menghilangkan jenis dokumen dari checklist
  kelengkapan, sehingga karyawan tampak lengkap padahal tidak
- `has_expiry` — mematikannya menghentikan notifikasi kedaluwarsa

Perbaikan: [lintas-modul.md § BUG-003](lintas-modul.md#bug-003--manager-punya-akses-tulis-penuh-tanpa-permission).

Tambahkan `document.view` dan `document.edit` di
[RolesAndPermissionsSeeder](../../database/seeders/RolesAndPermissionsSeeder.php)
lebih dulu, berikan ke `super_admin` dan `hr_admin`.

Perlu diperhatikan: **Dokumen Karyawan** (`/admin/employee-document`) memakai
controller biasa, bukan CRUD Backpack, sehingga tidak ikut tersapu oleh patch
route group. Guard-nya harus dipasang terpisah di
[EmployeeDocumentController](../../app/Http/Controllers/Admin/EmployeeDocumentController.php).

---

## Yang sudah benar di modul ini

| Perilaku | Status |
|---|---|
| Jenis Dokumen: create, update, delete | ✅ ketiganya |
| Form kosong ditolak validasi | ✅ |
| Kode duplikat ditolak validasi | ✅ |
| Tabel list dimuat AJAX — 8 jenis | ✅ |
| `/admin/employee-document` terbuka | ✅ 200 |
| `/admin/employee-document/create` terbuka | ✅ 200 |
| `/admin/employee-document/completeness` terbuka | ✅ 200 |
| Employee dialihkan ke `/my` | ✅ |

`/admin/employee-document/1/download` memberi 404 — itu **benar**, tidak ada
dokumen pada data demo.

---

## ✅ Jaminan keamanan sudah diuji — semuanya utuh

Diuji dengan mengunggah dokumen sungguhan (PDF) untuk Ahmad, lalu mencobanya
dari beberapa arah. Berkas tersimpan sebagai
`employee-documents/4/Ld7NsEzX9tZxPlxCfkKeqvdmkErQmV3jfxA0K8yF.pdf` — nama
di-randomkan, tidak bisa ditebak.

| Test case | Jaminan | Hasil |
|---|---|---|
| `DOC-C-02` | Berkas di disk **`local` (privat)** | ✅ ada di `storage/app/`, **tidak** di `storage/app/public/` maupun `public/storage/` |
| `DOC-R-06` | URL tidak bisa ditebak | ✅ ketiga variasi jalur langsung memberi **404** |
| `DOC-R-05` | Karyawan lain tidak bisa mengunduh | ✅ Dewi mendapat 302, **tidak** menerima isi dokumen Ahmad |
| `DOC-R-03` | HR bisa mengunduh semua | ✅ Rina menerima isi dokumen (200) |
| `DOC-D-02` | Hapus record → berkas fisik ikut terhapus | ✅ record hilang **dan** berkas fisik terhapus, tidak menyisakan sampah |

Jalur yang diuji untuk `DOC-R-06`:

```
/storage/employee-documents/4/Ld7Ns…pdf      → 404
/employee-documents/4/Ld7Ns…pdf             → 404
/storage/app/employee-documents/4/Ld7Ns…pdf → 404
```

---

## ⚠️ Celah fungsional: karyawan tidak bisa mengunduh dokumennya sendiri

| | |
|---|---|
| **Severity** | 🟡 Sedang — fitur kurang, bukan celah keamanan |
| **Status** | Belum diperbaiki — perlu keputusan produk |
| **Test case** | `DOC-R-04` |

Dokumen modul ini menyatakan "HR sees everything, everyone else only their own".
Bagian **"only their own" tidak terjangkau**: tidak ada route dokumen di portal
`/my`, dan satu-satunya route unduh berada di bawah `/admin/*` yang tertutup
bagi employee.

Diuji: Ahmad membuka `/admin/employee-document/1/download` untuk dokumennya
**sendiri** → 302 ke `/my`, tidak menerima berkas.

Ini bukan kebocoran — justru sisi amannya berlebihan. Tetapi berarti karyawan
tidak punya cara melihat dokumen yang diunggah HR atas namanya. Memperbaikinya
berarti **menambah fitur** (route `/my/documents` + kebijakan akses), bukan
menambal bug, jadi saya tidak mengerjakannya tanpa persetujuan.

---

## Belum teruji

| Test case | Hal yang perlu dipastikan |
|---|---|
| `DOC-V-02` | Ekstensi di luar `allowed_extensions` ditolak |
| `DOC-V-03` | Berkas melebihi `max_file_size_mb` ditolak dengan pesan, bukan 500 |
| `DOC-V-04` | Jenis `has_expiry=1` tanpa `expiry_date` ditolak |
| `DOC-X-02`, `DOC-X-03` | Checklist kelengkapan berubah sesuai unggahan |
| `DOC-X-07`, `DOC-X-08` | `documents:notify-expiring --days=30` tepat sasaran |
