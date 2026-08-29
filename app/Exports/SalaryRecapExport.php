<?php

namespace App\Exports;

use App\Models\SalaryRecap;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * ST-02 / PERF-7 — Export rekap gaji dengan FromQuery + chunk (memori rata),
 * bukan FromCollection yang memuat seluruh hasil ke RAM.
 *
 * Difilter per-bulan lewat constructor. Eager-load `user.salary` (sejalan QW-01,
 * cegah N+1 saat map()).
 */
class SalaryRecapExport implements FromQuery, WithHeadings, WithMapping, WithColumnFormatting, WithChunkReading
{
    protected ?string $recapMonth;

    public function __construct(?string $recapMonth = null)
    {
        $this->recapMonth = $recapMonth;
    }

    public function query()
    {
        return SalaryRecap::query()
            ->with(['user.salary'])
            ->when($this->recapMonth, fn ($q) => $q->where('recap_month', $this->recapMonth));
    }

    public function headings(): array
    {
        return [
            'Nama Karyawan',
            'Bulan',
            'Jumlah Masuk',
            'Jumlah Absen',
            'Jumlah Telat',
            'Total Telat(menit)',
            'Tipe Potongan Absen',
            'Gaji Bulan',
            'Potongan Absen',
            'Potongan Telat',
            'Potongan Kasbon',
            'Diterima',
            'Status',
            'Keterangan',
            'Metode Bayar',
        ];
    }

    public function map($row): array
    {
        if (! isset($row->user)) {
            return [];
        }

        return [
            $row->user->name,
            $row->recap_month,
            $row->work_day,
            $row->abstain_count,
            $row->late_day,
            $row->late_minute_count,
            $row->user->salary->fine_type ?? '',
            $row->salary_amount,
            $row->abstain_cut,
            $row->late_cut,
            $row->loan_cut,
            $row->received,
            $row->paid ? 'Ya' : 'Tidak',
            $row->desc,
            $row->method,
        ];
    }

    public function columnFormats(): array
    {
        // M14: Excel currency format follows the active currency symbol.
        $symbol = app(\App\Services\CurrencyService::class)->symbol();
        $fmt = '"' . $symbol . ' "#,##0';

        return [
            'H' => $fmt,
            'I' => $fmt,
            'J' => $fmt,
            'K' => $fmt,
            'L' => $fmt,
        ];
    }

    public function chunkSize(): int
    {
        return 1000;
    }
}
