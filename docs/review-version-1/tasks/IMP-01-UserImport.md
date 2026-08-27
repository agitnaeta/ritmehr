# IMP-01 — UserImport (baru)

**Status:** [ ] TODO — commit: `______`
**File:** `app/Imports/UserImport.php` (BARU)
**Bagian dari:** Import Excel Karyawan (menutup RV1-002, Lensa 4)
**Referensi pola:** `app/Imports/PresenceImport.php` (sudah ada, pakai Maatwebsite Excel)

## Tanggung jawab
Baca Excel karyawan → validasi baris → **merge langsung ke tabel `users`** (tanpa tabel perantara). Upsert by email/kode agar idempoten.

## Kerangka
```php
namespace App\Imports;

use App\Models\User;
use Maatwebsite\Excel\Concerns\{ToModel, WithHeadingRow, WithValidation, SkipsOnError};
use Illuminate\Support\Facades\Hash;

class UserImport implements ToModel, WithHeadingRow, WithValidation
{
    public function model(array $row)
    {
        return User::updateOrCreate(
            ['email' => $row['email']],
            [
                'name'          => $row['nama'],
                'join_date'     => $row['tgl_bergabung'] ?? null,
                'department_id' => $this->resolveDept($row['departemen'] ?? null),
                'branch_id'     => $this->resolveBranch($row['cabang'] ?? null),
                'password'      => Hash::make($row['password'] ?? 'password'),
                // ...map kolom lain
            ]
        );
    }

    public function rules(): array
    {
        return ['email' => 'required|email', 'nama' => 'required'];
    }
    // resolveDept/resolveBranch: cari-atau-buat by name → id
}
```
Kolom template mengikuti IMP-05 (heading Bahasa Indonesia).

## Verifikasi
1. Unit test: import file contoh 3 baris → 3 user di DB dgn dept/cabang benar.
2. Import ulang file sama → tetap 3 (idempoten), bukan duplikat.
3. Baris invalid (email kosong) ditolak & dilaporkan, tak menggagalkan seluruh batch.
