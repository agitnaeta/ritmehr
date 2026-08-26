<?php

namespace App\Services;

class TransalateService
{

    public function salaryRecapPrefix(){
        $symbol = app(\App\Services\CurrencyService::class)->symbol();
        return [
            'salary_amount'=>$symbol,
            'overtime_amount'=>$symbol,
            'loan_cut'=>$symbol,
            'late_cut'=>$symbol,
            'abstain_cut'=>$symbol,
            'received'=>$symbol,
        ];
    }
    public function salaryRecap(){
        return [
            'recap_month' => 'Bulan Rekap',
            'work_day' => 'Jumlah Hari Kerja',
            'late_day' => 'Jumlah Hari Terlambat',
            'salary_amount' => 'Jumlah Gaji',
            'overtime_amount' => 'Jumlah Overtime',
            'loan_cut' => 'Potongan Pinjaman',
            'late_cut' => 'Potongan Keterlambatan',
            'abstain_cut' => 'Potongan Absen',
            'received' => 'Total Diterima',
            'abstain_count' => 'Jumlah Hari Absen',
            'paid' => 'Dibayar',
            'method' => 'Metode Pembayaran',
            'desc' => 'Keterangan',
        ];
    }
}
