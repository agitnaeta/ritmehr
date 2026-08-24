# Bug List — Modul 13 Pengaturan

Test case: [../test-cases/13-pengaturan.md](../test-cases/13-pengaturan.md)

| Hasil suite | 11 PASS / 1 FAIL |
|---|---|
| Bug modul ini | BUG-005 |
| Bug lintas modul | — **tidak terdampak BUG-003** |

Modul ini adalah **satu-satunya bagian aplikasi yang penegakan hak aksesnya
lengkap**. Keempat halamannya menolak hr_admin dan manager dengan 403, dan
dropdown sidebar-nya tidak dirender untuk non-super_admin.

Justru keempat controller di sinilah pola yang benar berada — pakai sebagai
contoh saat memperbaiki BUG-003 di 19 entity lainnya.

---

## BUG-005 — Alur & Step Persetujuan tanpa validasi → HTTP 500

| | |
|---|---|
| **Severity** | 🔴 Kritis |
| **Status** | Terkonfirmasi keduanya |
| **Test case** | `approval-flow/V-empty`, `AFS-V-01` |

### Reproduksi

Login `siti@demo.test`, buka **Pengaturan → Alur Persetujuan → Add**, langsung
klik Simpan.

### Hasil aktual

| Entity | Galat |
|---|---|
| `approval-flow` | `1364 Field 'name' doesn't have a default value` |
| `approval-flow-step` | `1364 Field 'approval_flow_id' doesn't have a default value` |

### Mengapa ini berdampak lebih jauh dari sekadar layar 500

Konfigurasi yang setengah jadi di modul ini **menghentikan alur persetujuan
seluruh aplikasi**. `ApprovalService` melempar `RuntimeException` ketika sebuah
modul tidak punya flow aktif atau flow-nya tidak punya langkah — sehingga
pengajuan cuti dan kasbon gagal total.

Validasi di sini bukan sekadar kenyamanan; ia mencegah keadaan yang membuat
modul lain berhenti bekerja.

### Perbaikan

```php
// ApprovalFlowCrudController::setupCreateOperation()
CRUD::setValidation([
    'name'      => 'required|string|max:100',
    'module'    => 'required|in:leave,loan,overtime',
    'is_active' => 'boolean',
]);
```

Tambahkan pula aturan **satu flow aktif per modul** — dokumentasi
menjanjikannya, tetapi belum ada yang menegakkan:

```php
'is_active' => [
    'boolean',
    function ($attr, $value, $fail) {
        if (! $value) return;
        $exists = \App\Models\ApprovalFlow::where('module', request('module'))
            ->where('is_active', true)
            ->when(request('id'), fn ($q, $id) => $q->where('id', '!=', $id))
            ->exists();
        if ($exists) $fail('Sudah ada alur aktif untuk modul ini.');
    },
],
```

```php
// ApprovalFlowStepCrudController::setupCreateOperation()
CRUD::setValidation([
    'approval_flow_id' => 'required|exists:approval_flows,id',
    'step_order'       => [
        'required', 'integer', 'min:1',
        Rule::unique('approval_flow_steps')
            ->where(fn ($q) => $q->where('approval_flow_id', request('approval_flow_id'))),
    ],
    'approver_type'    => 'required|in:role,manager,user',
    'approver_role_id' => 'required_if:approver_type,role|nullable|exists:roles,id',
    'approver_user_id' => 'required_if:approver_type,user|nullable|exists:users,id',
]);
```

`required_if` di dua baris terakhir menutup `AFS-V-03` dan `AFS-V-04` sekaligus:
tipe `role` tanpa role, dan tipe `user` tanpa user.

Pasang juga di `setupUpdateOperation()`, dengan `->ignore($id)` pada aturan
unique.

---

## Yang sudah benar di modul ini — dan layak ditiru

| Perilaku | Status |
|---|---|
| `/admin/role` menolak hr_admin & manager | ✅ 403 |
| `/admin/permission` menolak hr_admin & manager | ✅ 403 |
| `/admin/approval-flow` menolak hr_admin & manager | ✅ 403 |
| `/admin/approval-flow-step` menolak hr_admin & manager | ✅ 403 |
| Dropdown **Pengaturan** tidak dirender untuk manager | ✅ |
| Dropdown **Pengaturan** tampil untuk super_admin | ✅ |
| `permission/create` ditutup **404** — read-only | ✅ |
| Alur Persetujuan: create, update, delete data valid | ✅ |
| Employee dialihkan dari `/admin/role` ke `/my` | ✅ |

Pemisahan hak yang halus di sini patut dicatat: **hr_admin boleh bertindak atas
approval tetapi tidak boleh mengubah alurnya**. `approval.configure` hanya
dimiliki super_admin, dan itu ditegakkan sungguhan — bukan sekadar tercatat di
database seperti permission lain yang jadi korban BUG-003.

Pola guard-nya sederhana dan konsisten di keempat controller:

```php
public function setup()
{
    if (! backpack_user()->can('approval.configure')) {
        abort(403, 'Anda tidak berhak mengubah alur persetujuan.');
    }
    // …
}
```

---

## Belum teruji

| Test case | Hal yang perlu dipastikan |
|---|---|
| `ROL-U-04` | super_admin mencabut `role.edit` dari dirinya sendiri — bisa mengunci diri |
| `ROL-D-02` | Hapus role yang masih dipakai — **hati-hati**: `CheckIfAdmin` memperlakukan user tanpa role sebagai admin, jadi mencabut seluruh role justru **memperluas** akses |
| `AFL-V-02` | Dua flow aktif untuk satu modul ditolak |
| `AFL-V-03` | Flow tanpa langkah → `RuntimeException` yang jelas, bukan 500 mentah |
| `AFS-U-02` | Approver diubah saat approval sedang berjalan — otorisasi harus memakai langkah **saat lock diambil** |
| `AFS-V-06` | `approver_type=manager` tetapi pengaju tidak punya `manager_id` |

`ROL-D-02` paling layak diprioritaskan karena akibatnya berlawanan dengan
intuisi: menghapus role bisa **menaikkan** hak akses seseorang, bukan
menurunkannya.
