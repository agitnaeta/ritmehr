# Bug List — Modul 14 Portal Karyawan

Test case: [../test-cases/14-portal-karyawan.md](../test-cases/14-portal-karyawan.md)

| Hasil suite | **5 PASS / 0 FAIL — bersih** |
|---|---|
| Bug modul ini | — tidak ada |

Modul dengan postur keamanan terbaik di aplikasi ini. Diuji khusus terhadap
IDOR dan kebocoran field, keduanya tertutup rapat.

---

## Yang sudah benar

| Perilaku | Status |
|---|---|
| Employee login mendarat di `/my`, bukan panel admin | ✅ |
| Employee paksa buka `/admin/user` → dialihkan ke `/my` | ✅ |
| Employee paksa buka `/admin/salary-recap` → `/my` | ✅ |
| Employee paksa buka `/admin/audit-log` → `/my` | ✅ |
| Employee paksa buka `/admin/bpjs-rate` → `/my` | ✅ |
| Employee paksa buka `/admin/role` → `/my` | ✅ |
| Admin juga punya portal pribadi di `/my` | ✅ 200 |

### Uji IDOR slip gaji

Sebagai Ahmad (user 4), diakses lewat sesi browser sungguhan:

```
/my/salary/1 → 404
/my/salary/2 → 404
/my/salary/3 → 404
/my/salary/4 → 200   ← miliknya sendiri
/my/salary/5 → 404
```

Tidak ada kebocoran. Query di-scope ke user terautentikasi, bukan disaring di
lapisan tampilan.

### Uji kebocoran field

| Pemeriksaan | Hasil |
|---|---|
| Form cuti `/my/leave/create` memuat `[name="user_id"]`? | ✅ **tidak** — nol hasil |
| Form profil `/my/profile` memuat `department_id`, `employment_status`, `position_id`, `salary`, `manager_id`? | ✅ **tidak** — nol hasil |

Dua aturan yang menjadi tulang punggung modul ini terbukti dipegang: setiap
query di-scope ke user login, dan **tidak ada route yang menerima id user dari
request**.

---

## Mengapa modul ini aman sementara panel admin bocor

Perbedaannya bukan pada jumlah pemeriksaan, melainkan pada **cara** otorisasi
diputuskan.

Panel admin bertanya *"apakah user ini punya permission X?"* — pertanyaan yang
mudah lupa ditanyakan, dan itulah yang terjadi pada 19 entity di
[BUG-003](lintas-modul.md#bug-003--manager-punya-akses-tulis-penuh-tanpa-permission).

Portal tidak pernah menanyakan itu. Ia langsung menurunkan data dari user yang
login, sehingga **tidak ada pertanyaan yang bisa terlupakan**. Route `/my/*`
tidak menerima id dari luar, jadi tidak ada yang perlu divalidasi.

Pelajaran untuk perbaikan BUG-004: alih-alih menambahkan pemeriksaan "apakah
manager boleh lihat karyawan ini", lebih tahan salah bila query disempitkan di
sumbernya — persis seperti yang dilakukan portal dan
[modul Persetujuan](11-persetujuan.md).

---

## Belum teruji

Yang sudah lulus adalah pembacaan. Sisi **tulis** portal belum diotomasi.

| Test case | Hal yang perlu dipastikan |
|---|---|
| `MY-C-04` | Kirim `POST /my/leave` dengan `user_id` milik orang lain → **diabaikan**, cuti tetap atas nama user login |
| `MY-U-06` | Kirim `POST /my/profile` dengan `department_id` → **diabaikan** |
| `MY-U-02` | Batalkan cuti milik karyawan lain → **ditolak** |
| `MY-U-13` | Tandai notifikasi milik orang lain sebagai dibaca → **ditolak** |
| `MY-U-08` | Ganti password dengan `current_password` salah → **ditolak** |
| `MY-S-02`…`S-05` | Pola IDOR pada setiap route portal ber-`{id}` |
| `MY-C-05`…`C-07` | Kuota, tumpang tindih, lampiran wajib ditegakkan dari sisi portal |

`MY-C-04` dan `MY-U-06` paling penting. Ketiadaan field di form (yang sudah
diverifikasi) hanya membuktikan **UI** tidak menawarkannya — bukan bahwa
**server** menolaknya. Keduanya perlu diuji dengan mengirim field itu secara
paksa lewat POST, bukan lewat form.

Uji ini paling tepat ditulis sebagai feature test PHPUnit, bukan test browser:
lebih cepat, dan lebih mudah menyusun payload yang tidak mungkin dibuat UI.
