<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * IMP-05 — Template kosong untuk import gaji (IMP-02).
 * Header cocok dgn WithHeadingRow di SalaryImport (snake_case) + satu baris contoh.
 */
class SalaryTemplateExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return ['email', 'gaji_pokok', 'lembur_1x', 'denda_per_menit', 'potongan_absen'];
    }

    public function array(): array
    {
        return [
            ['budi@contoh.test', '15.000.000', '75.000', '1.000', '0'],
        ];
    }
}
