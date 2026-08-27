# IMP-02 — SalaryImport (baru)

**Status:** [ ] TODO — commit: `______`
**File:** `app/Imports/SalaryImport.php` (BARU)
**Referensi desain:** [`../mockup/import.html`](../mockup/import.html) (tab "Import Gaji") · **Pola:** `app/Imports/PresenceImport.php`
**Bagian dari:** Import Gaji (RV1-002, Lensa 4)

## Tanggung jawab
Baca Excel gaji → cocokkan ke `users` by email → **upsert ke `salaries`** langsung. Parse angka currency (buang titik/koma ribuan). `amount` (total) tak diisi mentah — di-recalc observer M20.

## Field wajib `salaries` (dari skema — WAJIB diisi, tanpa default)
`user_id`, `basic_salary`, `overtime_amount`, `overtime_type` (enum flat/hour).
Default aman: `overtime_amount=0`, `overtime_type='flat'`.

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
        if (! $user) return null;   // dikumpulkan sbg error baris di preview

        return Salary::updateOrCreate(
            ['user_id' => $user->id],
            [
                'basic_salary'    => $this->num($row['gaji_pokok']),
                'overtime_amount' => $this->num($row['lembur_1x'] ?? 0),
                'overtime_type'   => 'flat',
                'fine_per_minute' => $this->num($row['denda_per_menit'] ?? 0),
                'unpaid_leave_deduction' => $this->num($row['potongan_absen'] ?? 0),
            ]
        );
    }
    public function rules(): array { return ['email' => 'required|email', 'gaji_pokok' => 'required']; }
    private function num($v): int { return (int) preg_replace('/[^\d]/', '', (string) $v); }
}
```

## Cek per file (verifikasi)
- [ ] Unit test: import → row `salaries` per karyawan, `basic_salary` benar (ribuan ke-parse).
- [ ] `amount` ter-recalc otomatis oleh observer, bukan diisi mentah.
- [ ] Email tak dikenal → error baris, batch lain tetap masuk.
- [ ] `phpunit` hijau.
