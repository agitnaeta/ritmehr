<?php

namespace App\Exports;

use App\Repositories\LoanRepository;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * ST-03 / PERF-7 — Export rekap kasbon dengan FromQuery + chunk (memori rata),
 * bukan FromCollection yang memuat seluruh hasil ke RAM.
 *
 * `selisih` dihitung di map() (bukan subquery SQL ke-3, sejalan dgn ST-04).
 */
class LoanExport implements FromQuery, WithHeadings, WithMapping, WithChunkReading
{
    public function query()
    {
        return LoanRepository::recapQuery();
    }

    public function map($row): array
    {
        return [
            $row->id,
            $row->name,
            (int) $row->kasbon,
            (int) $row->terbayar,
            (int) $row->kasbon - (int) $row->terbayar, // selisih (sama dgn recap())
        ];
    }

    public function headings(): array
    {
        return [
            'UserId',
            'Karyawan',
            'Jumlah Kasbon',
            'Terbayar',
            'Sisa',
        ];
    }

    public function chunkSize(): int
    {
        return 1000;
    }
}
