# IMP-01 — UserImport (baru)

**Status:** [x] DONE — commit: `(uncommitted)` · 5/5 test ExcelImportTest hijau
**File:** `app/Imports/UserImport.php` (BARU)
**Referensi desain:** [`../mockup/import.html`](../mockup/import.html) · **Pola:** `app/Imports/PresenceImport.php` (sudah ada)
**Bagian dari:** Import Karyawan (RV1-002, Lensa 4)

## Tanggung jawab
Baca Excel karyawan → validasi → **upsert langsung ke `users`** (tanpa tabel perantara). Idempoten by email. Dept/cabang dicari-atau-dibuat dari nama.

## Field aktual `users` (pakai ini persis)
`name, email, password, department_id, branch_id, position_id, employee_id, join_date, employment_status`

## Kerangka
```php
namespace App\Imports;

use App\Models\{User, Department, Branch, Position};
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Concerns\{ToModel, WithHeadingRow, WithValidation};

class UserImport implements ToModel, WithHeadingRow, WithValidation
{
    public function model(array $row)
    {
        return User::updateOrCreate(
            ['email' => $row['email']],
            [
                'name'          => $row['nama'],
                'join_date'     => $row['tgl_bergabung'] ?? null,
                'department_id' => $this->firstOrCreateId(Department::class, $row['departemen'] ?? null),
                'branch_id'     => $this->firstOrCreateId(Branch::class,     $row['cabang'] ?? null),
                'position_id'   => $this->firstOrCreateId(Position::class,   $row['jabatan'] ?? null),
                'password'      => Hash::make($row['password'] ?? 'password'),
                'employment_status' => $row['status'] ?? 'active',
            ]
        );
    }
    public function rules(): array { return ['email' => 'required|email', 'nama' => 'required']; }
    // firstOrCreateId: null-safe cari-atau-buat by name → id
}
```

## Cek per file (verifikasi)
- [ ] Unit test: import 3 baris → 3 user, dept/cabang ter-resolve ke id.
- [ ] Import ulang file sama → tetap 3 (idempoten, bukan duplikat).
- [ ] Baris email kosong → ditolak `WithValidation`, batch lain tetap masuk.
- [ ] `phpunit` hijau.
