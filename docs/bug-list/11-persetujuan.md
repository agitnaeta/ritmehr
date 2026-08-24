# Bug List — Modul 11 Persetujuan

Test case: [../test-cases/11-persetujuan.md](../test-cases/11-persetujuan.md)

| Hasil suite | **2 PASS / 0 FAIL — bersih** |
|---|---|
| Bug modul ini | — tidak ada |
| Bug lintas modul | — **tidak terdampak BUG-003 maupun BUG-004** |

Satu-satunya modul operasional yang lolos seluruh pemeriksaan. Bersama
[Pengaturan](13-pengaturan.md) dan [Portal Karyawan](14-portal-karyawan.md),
modul ini menunjukkan bahwa pola yang benar sudah ada di dalam aplikasi —
tinggal disebarkan ke modul lain.

---

## Yang sudah benar

| Perilaku | Status |
|---|---|
| `approval/create` ditutup **404** | ✅ |
| Tabel list dimuat AJAX — 2 dari 2 approval | ✅ |
| `/admin/approval/1/detail` terbuka | ✅ 200 |
| super_admin & hr_admin melihat **2 dari 2** | ✅ |
| **manager melihat 1 dari 2** — ter-scope tim | ✅ |
| `/admin/approval-flow` menolak manager | ✅ 403 |
| Employee dialihkan ke `/my` | ✅ |

---

## Scoping tim di sini sudah benar — jadikan contoh

Modul ini adalah **satu-satunya** tempat "team visibility" milik manager
benar-benar diterapkan:

| Modul | Manager melihat | Status |
|---|---|---|
| **Persetujuan** | **1 dari 2** | ✅ benar |
| Users | 5 dari 5 | ❌ [BUG-004](lintas-modul.md#bug-004--scoping-tim-manager-tidak-diterapkan) |
| Kehadiran | 110 dari 110 | ❌ [BUG-004](lintas-modul.md#bug-004--scoping-tim-manager-tidak-diterapkan) |

Polanya ada di
[ApprovalCrudController](../../app/Http/Controllers/Admin/ApprovalCrudController.php#L30):
periksa `approval.view_all`, dan bila tidak dimiliki, sempitkan query ke apa
yang menjadi tanggung jawab user tersebut. Persis pola inilah yang perlu
disalin ke `UserCrudController` dan `PresenceCrudController`.

Pemisahan haknya juga rapi: manager punya `approval.act` sehingga **boleh
bertindak** atas approval timnya, tetapi tidak punya `approval.configure`
sehingga **tidak boleh** mengubah alurnya. Dua tingkat izin dalam satu modul,
keduanya ditegakkan sungguhan.

---

## Belum teruji — dan di sinilah risiko tersisa

Harness baru memeriksa akses dan tampilan daftar. Seluruh **logika transisi
status** belum tersentuh, padahal itu inti modul ini.

| Test case | Hal yang perlu dipastikan |
|---|---|
| `APR-U-03` | `approved_by` = approver **terakhir**, bukan yang pertama |
| `APR-U-13` | `rejection_reason` dari penolak **terakhir** |
| `APR-U-08` | Dua approver berlomba — hanya **satu** yang berhasil (row lock) |
| `APR-U-11` | Tolak tanpa alasan → `DomainException` |
| `APR-U-05`, `U-06`, `U-07` | Bukan approver / sudah selesai / lompat langkah → ditolak |
| `APR-U-10` | Kuota cuti dicek ulang di bawah row lock saat approve |
| `APR-C-03` | Submit ganda ditolak unique index |
| `APR-C-04`, `C-05` | Tanpa flow aktif / flow tanpa langkah → `RuntimeException` jelas, bukan 500 |

### Kenapa dua yang pertama layak diprioritaskan

`APR-U-03` dan `APR-U-13` adalah **regression test untuk bug yang pernah
terjadi**. Relasi `actions()` membawa `orderBy('step_order')`; menambahkan
`->latest('acted_at')` menghasilkan `ORDER BY step_order ASC, acted_at DESC`,
sehingga langkah 1 tetap menang. Pada rantai manager→HR, **manager keliru
tercatat sebagai penyetuju akhir**. Sudah diperbaiki dengan `->reorder()` dan
sudah punya test PHPUnit — tetapi belum pernah diverifikasi lewat UI.

`APR-U-08` (balapan dua approver) butuh dua sesi browser paralel. Ini bisa
diotomasi dengan dua `browser.newContext()` yang menekan Approve bersamaan —
layak ditambahkan ke harness karena row lock adalah jaminan yang mudah rusak
tanpa disadari saat query di-refactor.
