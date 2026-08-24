# Modul 11 — Persetujuan

| | |
|---|---|
| **URL** | `/admin/approval` |
| **Controller** | [ApprovalCrudController](../../app/Http/Controllers/Admin/ApprovalCrudController.php) |
| **Service** | [ApprovalService](../../app/Services/ApprovalService.php) |
| **Tabel** | `approvals`, `approval_actions` (+ `approval_flows`, `approval_flow_steps` di modul 13) |
| **Operasi** | Create ✖ (**404**) · Read ✔ · Update ✖ · Delete ✖ · Approve · Reject · Cancel |

Approval **tidak dibuat manual** — terbentuk otomatis saat sebuah record
approvable diajukan (cuti, kasbon, lembur).

Konfigurasi alur ada di [modul 13 — Pengaturan](13-pengaturan.md).

---

## Jaminan konkurensi

Setiap perubahan status berjalan dalam transaksi dengan `SELECT … FOR UPDATE`
pada baris approval, dan **otorisasi ulang terhadap langkah yang berlaku saat
lock diambil**. Dua approver yang berlomba tidak bisa dua-duanya berhasil.
Satu approval hidup per record dijaga **unique index**, bukan sekadar kode
aplikasi.

## Jenis galat

| Kelas | Kapan |
|---|---|
| `\DomainException` | Kesalahan pemanggil: approver salah, status bukan pending, alasan kosong, submit ganda |
| `\RuntimeException` | Salah konfigurasi: tidak ada flow aktif, flow tanpa langkah |

---

## CREATE

| ID | Skenario | Langkah | Expected | Status |
|---|---|---|---|---|
| APR-C-01 | CRUD create ditutup | Buka `/admin/approval/create` | **404** — memang sengaja | ✅ |
| APR-C-02 | Terbentuk otomatis | Ajukan cuti | Approval baru muncul dengan langkah 1 aktif | ⬜ |
| APR-C-03 | Submit ganda | Ajukan record yang sama dua kali | `DomainException` — unique index menolak approval hidup kedua | ⬜ |
| APR-C-04 | Tanpa flow aktif | Nonaktifkan flow modul → ajukan | `RuntimeException` dengan pesan jelas, **bukan 500 mentah** | ⬜ |
| APR-C-05 | Flow tanpa langkah | Aktifkan flow kosong → ajukan | `RuntimeException` | ⬜ |

## READ

| ID | Skenario | Langkah | Expected | Status |
|---|---|---|---|---|
| APR-R-01 | List | Buka `/admin/approval` | Tabel AJAX **2 dari 2** pada data demo | 🌐 |
| APR-R-02 | Detail | `/admin/approval/1/detail` | 200; riwayat langkah, aktor, waktu | ✅ |
| APR-R-03 | Scoping SA / HR | Login `siti@` / `rina@` | Punya `approval.view_all` — melihat **2 dari 2** | ✅ |
| APR-R-04 | **Scoping MGR** | Login `budi@` | Melihat **1 dari 2** — hanya yang jadi tanggung jawabnya. **Satu-satunya modul yang scoping timnya sudah benar** | 🌐 |
| APR-R-05 | Pending milik saya | `getPendingForUser()` | Hanya approval yang menunggu aksi user tsb | ⬜ |
| APR-R-06 | Approver berikutnya | `getNextApprovers()` | Mengembalikan approver langkah aktif | ⬜ |

## APPROVE

| ID | Skenario | Langkah | Expected | Status |
|---|---|---|---|---|
| APR-U-01 | Setujui langkah tunggal | Approve pada flow 1 langkah | Status approved, `approved_by` terisi | ⬜ |
| APR-U-02 | Setujui langkah berantai | Flow manager → HR, approve langkah 1 | Maju ke langkah 2, belum selesai | ⬜ |
| APR-U-03 | **`approved_by` = approver terakhir** | Budi approve → Rina approve | `approved_by` = **Rina**, bukan Budi | ⬜ |
| APR-U-04 | Callback dijalankan | Approve pengajuan cuti | `onApprovalApproved()` jalan — kuota berkurang | ⬜ |
| APR-U-05 | Bukan approver | User bukan approver langkah aktif | `DomainException` — ditolak | ⬜ |
| APR-U-06 | Approve yang sudah selesai | Approve approval approved | `DomainException` — bukan pending | ⬜ |
| APR-U-07 | Approve lompat langkah | Approver langkah 2 bertindak saat langkah 1 aktif | Ditolak | ⬜ |
| APR-U-08 | **Balapan dua approver** | Dua tab approve serentak | Hanya satu berhasil — row lock | ⬜ |
| APR-U-09 | Approver = manager pengaju | Flow `approver_type=manager` | Atasan langsung pengaju yang berwenang | ⬜ |
| APR-U-10 | Kuota dicek ulang saat approve | Approve cuti yang kuotanya sudah habis terpakai | Ditolak — cek kedua di bawah row lock | ⬜ |

## REJECT

| ID | Skenario | Langkah | Expected | Status |
|---|---|---|---|---|
| APR-U-11 | **Tolak tanpa alasan** | Reject, alasan kosong | `DomainException` — alasan wajib | ⬜ |
| APR-U-12 | Tolak dengan alasan | Reject + alasan | Status rejected, alasan tersimpan | ⬜ |
| APR-U-13 | **`rejection_reason` dari penolak terakhir** | Rantai bertingkat, ditolak di langkah akhir | Alasan milik penolak **terakhir** | ⬜ |
| APR-U-14 | Callback penolakan | Reject pengajuan cuti | `onApprovalRejected()` jalan — kuota tidak terpotong | ⬜ |
| APR-U-15 | Tolak di langkah pertama | Reject langkah 1 | Langsung rejected, langkah 2 tidak pernah aktif | ⬜ |

## CANCEL

| ID | Skenario | Langkah | Expected | Status |
|---|---|---|---|---|
| APR-U-16 | Batalkan pending | Cancel | Status cancelled | ⬜ |
| APR-U-17 | Callback pembatalan | Cancel pengajuan cuti | `onApprovalCancelled()` jalan — efek samping dibatalkan | ⬜ |
| APR-U-18 | Batalkan yang sudah selesai | Cancel approval approved | Perilaku terdefinisi | ⬜ |
| APR-U-19 | Batalkan milik orang lain | Cancel approval karyawan lain | Ditolak | ⬜ |

## DELETE

| ID | Skenario | Langkah | Expected | Status |
|---|---|---|---|---|
| APR-D-01 | Delete ditutup | Coba hapus | **404** — `denyAccess(['create','update','delete'])` | ✅ |

## AKSES

| ID | Role | Permission | Expected | Status |
|---|---|---|---|---|
| APR-A-01 | SA | `approval.view_all`, `approval.act`, `approval.configure` | Akses penuh termasuk konfigurasi alur | ✅ 200 |
| APR-A-02 | HR | `approval.view_all`, `approval.act` — **tanpa** `configure` | Boleh bertindak, **tidak** boleh ubah alur → `/admin/approval-flow` **403** | ✅ |
| APR-A-03 | MGR | `approval.act` saja | Melihat 1 dari 2, boleh bertindak atas timnya; `/admin/approval-flow` **403** | 🌐 |
| APR-A-04 | EMP | — | Dialihkan ke `/my` | 🌐 |

## AUDIT

| ID | Skenario | Langkah | Expected | Status |
|---|---|---|---|---|
| APR-X-01 | Aksi tercatat | Approve lalu buka `/admin/audit-log` | Entri berisi aktor, waktu, aksi | ⬜ |
| APR-X-02 | Riwayat langkah | Buka detail approval | Semua `approval_actions` tampil berurutan | ⬜ |
| APR-X-03 | Digest mingguan | `php artisan notify:approval-digest` | Ringkasan approval tertunda terkirim; terjadwal Senin 08:00 | ⬜ |

> **Regresi yang pernah terjadi.** Relasi `actions()` membawa
> `orderBy('step_order')`. Menambahkan `->latest('acted_at')` menghasilkan
> `ORDER BY step_order ASC, acted_at DESC`, sehingga langkah 1 tetap menang dan
> pada rantai manager→HR **manager keliru dicatat sebagai penyetuju akhir**.
> Diperbaiki dengan `->reorder()`; bug yang sama juga memengaruhi
> `rejection_reason`. Keduanya kini punya regression test — APR-U-03 dan
> APR-U-13 adalah versi manualnya.
