<?php

namespace App\Exports;

use App\Models\SalaryRecap;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithFormatData;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class SalaryRecapExport implements FromCollection, WithHeadings, WithMapping, WithColumnFormatting
{

    protected $recap;
    public function __construct(Collection $recap)
    {
        $this->recap = $recap;
    }

    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        $sum = [
            'Total',
            '',
            '',
            '',
            '',
            '',
            '',
            $this->recap->sum('salary_amount'),
            $this->recap->sum('abstain_cut'),
            $this->recap->sum('late_cut'),
            $this->recap->sum('loan_cut'),
            $this->recap->sum('received'),
            '',
            '',
            ''
        ];
       return $this->recap->add($sum);
    }

    public function headings(): array
    {
        return[
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
            'Metode Bayar'
        ];
    }

    public function map($row): array
    {
        if(!isset($row->user)){
            return [];
        }
        return[
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
            $row->paid ? 'Ya' :'Tidak',
            $row->desc,
            $row->method,
        ];
    }

    public function columnFormats(): array
    {
        return [
            'H'=>'"Rp "#,##0',
            'I'=>'"Rp "#,##0',
            'J'=>'"Rp "#,##0',
            'K'=>'"Rp "#,##0',
            'L'=>'"Rp "#,##0'
        ];
    }
}
