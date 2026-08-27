# IMP-02 — SalaryImport (baru)

**Status:** [ ] TODO — commit: `______`
**File:** `app/Imports/SalaryImport.php` (BARU)
**Bagian dari:** Import Excel Gaji (menutup RV1-002, Lensa 4)
**Referensi pola:** `app/Imports/PresenceImport.php`

## Tanggung jawab
Baca Excel struktur gaji → cocokkan ke karyawan (by email/kode) → **upsert ke tabel `salaries`** langsung. Angka currency di-parse (buang titik/koma ribuan).

## Kerangka
```php
namespace App\Imports;

use App\Models\{Salary, User};
use Maatwebsite\Excel\Concerns\{ToModel, WithHeadingRow, WithValidation};

class SalaryImport implements ToModel, WithHeadingRow, WithValidation
{
    public function model(array $row)
    {
        $user = User::where('email', $row['email'])->first();
        if (! $user) return null;   // atau kumpulkan sebagai error

        return Salary::updateOrCreate(
            ['user_id' => $user->id],
            [
                'basic_salary'    => $this->num($row['gaji_pokok']),
                'overtime_amount' => $this->num($row['lembur_1x'] ?? 0),
                'fine_per_minute' => $this->num($row['denda_per_menit'] ?? 0),
                // amount (total) dihitung ulang oleh observer M20 (pokok + Σ tunjangan)
            ]
        );
    }
    public function rules(): array { return ['email' => 'required|email', 'gaji_pokok' => 'required']; }
    private function num($v): int { return (int) preg_replace('/[^\d]/', '', (string) $v); }
}
```

## Verifikasi
1. Unit test: import → row `salaries` per karyawan, `basic_salary` benar.
2. `amount` (total) ter-recalc otomatis oleh observer existing, bukan diisi mentah.
3. Email tak dikenal → dilaporkan sbg error baris, batch lain tetap masuk.
