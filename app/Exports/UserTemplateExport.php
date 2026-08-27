<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * IMP-05 — Template kosong untuk import karyawan (IMP-01).
 * Header cocok dgn WithHeadingRow di UserImport (snake_case) + satu baris contoh.
 */
class UserTemplateExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return ['nama', 'email', 'tgl_bergabung', 'departemen', 'cabang', 'jabatan', 'password', 'status'];
    }

    public function array(): array
    {
        return [
            ['Budi Santoso', 'budi@contoh.test', '2024-01-15', 'Operasional', 'Kantor Pusat', 'Staff', 'rahasia123', 'aktif'],
        ];
    }
}
