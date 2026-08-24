# Bug List — Modul 07 Organisasi

Test case: [../test-cases/07-organisasi.md](../test-cases/07-organisasi.md)

| Hasil suite | 23 PASS / 3 FAIL |
|---|---|
| Bug lintas modul | BUG-003 |

Tidak ada bug khusus modul ini. Ketiga entity — Cabang, Departemen, Jabatan —
punya validasi inline yang lengkap dan lulus seluruh siklus CRUD.

---

## BUG-003 — Manager bisa mengubah struktur organisasi

| | |
|---|---|
| **Severity** | 🔴 Kritis |
| **Test case** | `branch/A-mgr-write`, `department/A-mgr-write`, `position/A-mgr-write` |

Ketiga form create memberi **HTTP 200** bagi manager. Role `manager` hanya punya
`org.view` — tidak ada `org.edit`, dan tidak ada permission cabang sama sekali.

Dampak yang menonjol ada pada **Cabang**, karena cabang membawa koordinat
geofence:

- Mengubah `lat`/`lng` atau `radius_meters` mengubah apakah scan absensi dinilai
  di dalam atau di luar radius — untuk seluruh karyawan cabang itu
- Membesarkan radius sampai 100.000 m secara efektif mematikan geofencing
- Menonaktifkan cabang memengaruhi penempatan karyawan baru

Pada **Departemen**, manager dapat memindahkan induk departemen dan mengganti
`head_user_id`, yang mengubah bentuk struktur organisasi dan berpotensi
memengaruhi rantai persetujuan berbasis atasan.

Perbaikan: [lintas-modul.md § BUG-003](lintas-modul.md#bug-003--manager-punya-akses-tulis-penuh-tanpa-permission).

Catatan: **Cabang belum punya permission sendiri** — tidak ada `branch.*` di
daftar 54 permission. Perlu ditambahkan lebih dulu di
[RolesAndPermissionsSeeder](../../database/seeders/RolesAndPermissionsSeeder.php)
sebelum bisa ditegakkan. Departemen dan Jabatan bisa langsung memakai `org.edit`
yang sudah ada.

---

## Yang sudah benar di modul ini

| Perilaku | Status |
|---|---|
| Cabang: create, update, delete | ✅ ketiganya |
| Departemen: create, update, delete | ✅ ketiganya |
| Jabatan: create, update, delete | ✅ ketiganya |
| Form kosong ditolak validasi — ketiga entity | ✅ |
| Kode duplikat ditolak — Cabang & Departemen | ✅ |
| Tabel list dimuat AJAX | ✅ 2 cabang · 4 departemen · 4 jabatan |
| `/admin/org-chart` terbuka | ✅ 200 |
| Employee dialihkan ke `/my` | ✅ |

Validasi inline di
[BranchCrudController](../../app/Http/Controllers/Admin/BranchCrudController.php)
adalah salah satu yang paling menyeluruh di aplikasi — lengkap dengan
`between:-90,90` untuk latitude, `between:-180,180` untuk longitude, dan
`min:10|max:100000` untuk radius. Pakai sebagai contoh saat menambal BUG-005.

---

## Belum teruji

| Test case | Hal yang perlu dipastikan |
|---|---|
| `DEP-U-02` | Induk = departemen itu sendiri → **ditolak** (cycle guard) |
| `DEP-U-03` | Induk = keturunan sendiri → **ditolak** (cycle guard) |
| `DEP-X-01` | Data bersiklus disuntikkan langsung ke DB → org chart **tidak hang** |
| `BR-V-04`…`V-07` | Batas koordinat dan radius ditolak sesuai aturan |
| `BR-X-01`…`X-03` | Urutan resolusi geofence: baris presensi → cabang user → config global |
| `BR-X-02` | Tanpa titik referensi mana pun → scan dianggap **on-site**, bukan menandai semua di luar |
| `BR-X-03` | Transfer karyawan → presensi lama tidak di-atribusi ulang |
| `DEP-D-02`, `POS-D-01` | Hapus entity yang masih dirujuk |

Tiga cycle guard (`DEP-U-02`, `DEP-U-03`, `DEP-X-01`) paling layak diprioritaskan
— dokumentasi mengklaim keduanya ada dan `descendants()` tahan terhadap loop,
tetapi klaim itu belum pernah diuji lewat UI.
