<?php

namespace App\Imports;

use App\Models\Salary;
use App\Models\User;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

/**
 * IMP-02 — Import struktur gaji dari Excel.
 *
 * Header (WithHeadingRow, snake_case): email, gaji_pokok, lembur_1x,
 * denda_per_menit, potongan_absen.
 *
 * Dicocokkan ke user by email lalu upsert ke `salaries`. `amount` (total)
 * TIDAK diisi mentah — di-recalc otomatis oleh Salary::saving() (M20:
 * basic_salary + Σ tunjangan). Email tak dikenal dikumpulkan sebagai error.
 * Baris invalid dilewati (SkipsOnFailure), baris valid tetap masuk.
 */
class SalaryImport implements ToModel, WithHeadingRow, WithValidation, SkipsEmptyRows, SkipsOnFailure
{
    use SkipsFailures;

    /** @var int Baris tersimpan. */
    public int $imported = 0;

    /** @var array<int,string> Email yang tak ketemu (dilaporkan ke user). */
    public array $unmatched = [];

    public function model(array $row)
    {
        $user = User::where('email', trim($row['email']))->first();

        if (! $user) {
            $this->unmatched[] = trim($row['email']);

            return null; // lewati baris; batch lain tetap jalan
        }

        $salary = Salary::updateOrCreate(
            ['user_id' => $user->id],
            [
                'basic_salary'           => $this->num($row['gaji_pokok'] ?? 0),
                'overtime_amount'        => $this->num($row['lembur_1x'] ?? 0),
                'overtime_type'          => 'flat',
                'fine_per_minute'        => $this->num($row['denda_per_menit'] ?? 0),
                'fine_type'              => 'minute',
                'unpaid_leave_deduction' => $this->num($row['potongan_absen'] ?? 0),
            ]
        );

        $this->imported++;

        return $salary;
    }

    public function rules(): array
    {
        return [
            'email'      => ['required', 'email'],
            'gaji_pokok' => ['required'],
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'email.required'      => 'Kolom email wajib diisi.',
            'email.email'         => 'Format email tidak valid.',
            'gaji_pokok.required' => 'Kolom gaji_pokok wajib diisi.',
        ];
    }

    /** Buang pemisah ribuan / simbol → integer rupiah. */
    private function num($value): int
    {
        return (int) preg_replace('/[^\d]/', '', (string) $value);
    }
}
