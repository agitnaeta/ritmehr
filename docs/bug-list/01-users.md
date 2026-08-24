# Bug List — Modul 01 Users

Test case: [../test-cases/01-users.md](../test-cases/01-users.md)

| Hasil suite | 1 PASS / 2 FAIL |
|---|---|
| Bug lintas modul | BUG-003, BUG-004 |

Modul ini tidak punya bug sendiri — validasinya justru salah satu yang paling
rapi di aplikasi. Dua kegagalan seluruhnya soal hak akses manager.

---

## BUG-003 — Manager bisa membuat dan mengubah karyawan

| | |
|---|---|
| **Severity** | 🔴 Kritis |
| **Test case** | `user/A-mgr-write` |

Login `budi@demo.test` → `/admin/user/create` → **HTTP 200**.

Role `manager` tidak punya `user.create`, `user.edit`, maupun `user.delete`.
Yang bisa dilakukan manager saat ini: menambah karyawan, mengubah data siapa
pun termasuk **atasan** (`manager_id`) dan **status kepegawaian**, serta
menghapus karyawan.

Mengubah `manager_id` sangat sensitif karena menentukan siapa approver dalam
alur persetujuan — manager dapat mengarahkan approval ke dirinya sendiri.

Perbaikan: [lintas-modul.md § BUG-003](lintas-modul.md#bug-003--manager-punya-akses-tulis-penuh-tanpa-permission).

---

## BUG-004 — Manager melihat seluruh karyawan

| | |
|---|---|
| **Severity** | 🟠 Tinggi |
| **Test case** | `user/A-mgr-scope` |

Manager melihat **5 dari 5** karyawan — sama dengan super_admin, bukan hanya
timnya. Daftar ini memuat kolom yang seharusnya terbatas: departemen, jabatan,
status kepegawaian, dan atasan setiap orang.

Bandingkan dengan modul Persetujuan yang sudah benar (manager melihat 1 dari 2).

Perbaikan: [lintas-modul.md § BUG-004](lintas-modul.md#bug-004--scoping-tim-manager-tidak-diterapkan).

---

## BUKAN bug — sudah diverifikasi

### `UserRequest` terlihat akan merusak fitur edit

Pembacaan sekilas atas
[UserRequest::rules()](../../app/Http/Requests/UserRequest.php) memunculkan dua
kecurigaan:

```php
'email' => 'required|email|unique:users,email',       // tanpa ignore id?
'image' => 'required|file|mimes:jpg,png,webp',        // foto wajib saat edit?
```

Bila aturan ini dipakai untuk update, menyimpan karyawan tanpa mengubah email
akan ditolak, dan setiap edit akan menuntut unggah ulang foto.

**Ternyata tidak.** Ada `updateRules($userId)` terpisah yang sudah benar:

```php
public function updateRules($userId)
{
    return [
        'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($userId)],
        'password' => 'string|nullable',
        'image' => 'file|mimes:jpg,png,webp',
    ];
}
```

Dan [UserCrudController:149-155](../../app/Http/Controllers/Admin/UserCrudController.php#L149-L155)
memang memanggilnya di `setupUpdateOperation()`. Diverifikasi di browser:
membuka edit karyawan lalu klik Simpan tanpa mengubah apa pun **berhasil**.

Pemisahan create vs update ini adalah pola yang benar — pakai sebagai contoh
saat menambal validasi entity lain (BUG-005 dan BUG-006).

### Foto wajib saat create

`image` memang `required` pada create. Diuji: mengirim `name` + `email` +
`password` tanpa foto **ditolak**, karyawan tidak dibuat. Ini pembatasan
produk, bukan bug — tetapi layak dikonfirmasi ke pemilik produk apakah memang
diinginkan, karena berarti karyawan tidak bisa didaftarkan sebelum fotonya ada.

### Cetak ID card mengalihkan ke Profil Perusahaan

`/admin/user/1/print` dan `/admin/user/print-all` memberi 302 ke
`/admin/company-profile` ketika background ID card belum diunggah. Ini **guard
yang benar** — dan justru pola inilah yang seharusnya dipakai
[BUG-001](04-penggajian.md#bug-001--cetak-slip-gaji-selalu-http-500-bila-logo-perusahaan-kosong),
yang malah meledak 500 dalam situasi setara.

---

## Yang sudah benar di modul ini

| Perilaku | Status |
|---|---|
| Tabel list dimuat AJAX — 5 dari 5 | ✅ |
| Form create & edit terbuka | ✅ 200 |
| Update lewat form UI tersimpan | ✅ |
| Simpan tanpa mengubah email — tidak ditolak | ✅ |
| Create tanpa foto ditolak | ✅ |
| Export `.xlsx` | ✅ 200 |
| Cetak ID card — guard saat background kosong | ✅ 302 |
| Employee dialihkan dari `/admin/user` ke `/my` | ✅ |

---

## Belum teruji

| Test case | Hambatan |
|---|---|
| `USER-C-02`…`C-06` create lengkap | Butuh unggah berkas foto |
| `USER-D-03` hapus karyawan berelasi | Perlu keputusan perilaku yang diharapkan |
| `USER-U-07` `manager_id` = diri sendiri | Perlu cek apakah loop approval dicegah |
| `USER-X-05` isi PDF ID card | Butuh background terunggah + pemeriksaan visual |
| `USER-R-05` filter bar | Belum diotomasi |
