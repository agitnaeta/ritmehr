# Bug List — Modul 08 Cuti & Izin

Test case: [../test-cases/08-cuti.md](../test-cases/08-cuti.md)

| Hasil suite | 15 PASS / 3 FAIL |
|---|---|
| Bug modul ini | BUG-005, BUG-006 (Saldo Cuti) |
| Bug lintas modul | BUG-003 |

---

## BUG-005 — Saldo Cuti tanpa validasi → HTTP 500

| | |
|---|---|
| **Severity** | 🔴 Kritis |
| **Status** | Terkonfirmasi |
| **Test case** | `leave-balance/V-empty`, `LVB-V-01` |

### Reproduksi

Buka **Cuti & Izin → Saldo Cuti → Add**, langsung klik Simpan.

### Hasil aktual

```
HTTP 500
SQLSTATE[HY000]: General error: 1364 Field 'user_id' doesn't have a default value
```

### Perbaikan

```php
// LeaveBalanceCrudController::setupCreateOperation()
CRUD::setValidation([
    'user_id'       => 'required|exists:users,id',
    'leave_type_id' => 'required|exists:leave_types,id',
    'year'          => 'required|integer|min:2000|max:2100',
    'quota'         => 'required|integer|min:0|max:365',
    'carry_over'    => 'nullable|integer|min:0|max:365',
    'used'          => 'nullable|integer|min:0',
]);
```

Catatan: **jangan** memvalidasi `remaining` — kolom itu *generated*
(`quota + carry_over − used`) dan tidak bisa ditulis.

---

## BUG-006 — Saldo cuti duplikat → HTTP 500

| | |
|---|---|
| **Severity** | 🟠 Tinggi |
| **Status** | Terkonfirmasi |

### Reproduksi

Buat saldo cuti untuk kombinasi user + jenis cuti + tahun yang sudah ada
(mis. user 4, jenis 1, tahun 2026).

### Hasil aktual

```
HTTP 500
SQLSTATE[23000]: 1062 Duplicate entry '4-1-2026' for key 'leave_balances…'
```

### Mengapa ini mudah terpicu

Tombol **Generate** membuat saldo untuk semua karyawan. Admin yang kemudian
menambah satu saldo manual — lupa bahwa Generate sudah membuatnya — langsung
mendapat layar 500 alih-alih pesan "saldo untuk karyawan dan tahun ini sudah ada".

### Perbaikan

```php
'user_id' => [
    'required', 'exists:users,id',
    Rule::unique('leave_balances')->where(fn ($q) => $q
        ->where('leave_type_id', request('leave_type_id'))
        ->where('year', request('year'))),
],
```

Pada update tambahkan `->ignore($id)`.

---

## BUG-003 — Manager bisa mengubah konfigurasi cuti dan saldo

| | |
|---|---|
| **Severity** | 🔴 Kritis |
| **Test case** | `leave-type/A-mgr-write`, `leave-balance/A-mgr-write` |

Manager mendapat HTTP 200 pada kedua form create. Role `manager` memang punya
`leave.view_all`, `leave.approve`, dan `leave.reject` — wajar, itu memang
tugasnya. Yang **tidak** dimilikinya adalah `leave.configure` dan
`leave.manage_balance`.

Akibatnya manager dapat:

- Mengubah **Jenis Cuti** — termasuk membalik `is_paid`, yang langsung mengubah
  apakah cuti memotong gaji atau tidak
- Mengubah **Saldo Cuti** siapa pun — menaikkan kuota, mengubah `used`,
  atau menjalankan Generate dan Carry Over

Kombinasi keduanya berbahaya: seorang manager bisa menaikkan kuotanya sendiri,
lalu menyetujui cutinya sendiri bila alur persetujuan menempatkan dirinya
sebagai approver.

Permission yang sudah ada tinggal ditegakkan: `leave.configure`,
`leave.manage_balance`.

Perbaikan: [lintas-modul.md § BUG-003](lintas-modul.md#bug-003--manager-punya-akses-tulis-penuh-tanpa-permission).

---

## Yang sudah benar di modul ini

| Perilaku | Status |
|---|---|
| `leave-request/create` ditutup **404** — pengajuan lewat form khusus | ✅ |
| Jenis Cuti: create, update, delete | ✅ ketiganya |
| Jenis Cuti: kode duplikat ditolak validasi | ✅ |
| Jenis Cuti: form kosong ditolak validasi | ✅ |
| Saldo Cuti: create, update, delete data valid | ✅ |
| Tabel Pengajuan Cuti dimuat AJAX (2 dari 2) | ✅ |
| Kalender Cuti, Rekap Cuti | ✅ 200 |
| Portal `/my/leave` — form tanpa field `user_id` | ✅ |

`LeaveTypeCrudController` adalah salah satu dari sedikit controller HRIS baru
yang **sudah** memasang validasi lengkap — pakai itu sebagai contoh saat
menambal `LeaveBalanceCrudController`.

---

## Belum teruji — aturan bisnis LeaveService

Harness baru menguji CRUD. Seluruh aturan bisnis di
[../test-cases/08-cuti.md § 8.3](../test-cases/08-cuti.md) masih ⬜:

- Tumpang tindih tanggal ditolak
- Akhir pekan dan libur nasional dilewati saat menghitung `total_days`
- Kuota dicek **dua kali** — saat submit dan lagi di bawah row lock saat approval
- `max_consecutive_days` dan `requires_attachment` ditegakkan
- Kuota prorata untuk karyawan yang masuk tengah tahun
- Generate dan Carry Over idempoten

Dampak payroll (`LVR-P-01`…`P-06`) juga belum diuji otomatis — inilah perbaikan
paling penting di modul ini, dan `tests/Feature/SalaryLeaveIntegrationTest.php`
sudah menutupinya di level unit.
