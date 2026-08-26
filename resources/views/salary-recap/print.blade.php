
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Cetak Gaji</title>
    <style>
        body{
            margin-top: 10px;
        }
        table,
        th,
        td {
            border: 1px solid #ddd;
        }
        table{
            width: 100%;
            font-size: smaller;
            border: 1px solid #73818f;
        }
        .mt{
            margin-bottom: 32px;
        }
        .page-break {
            page-break-before: always;
        }
        .logo{
            justify-content: center;
            margin-left: auto;
            margin-right: auto;
            display: block;
            text-align: center;
            overflow: hidden;
            width: 80px;
            height: 50px;
        }
        .logo > .company-logo{
            height: auto;
            width: 80px;
        }
        .text-heading{
            text-align: left;
            font-weight:bolder ;
        }
    </style>
</head>
<body>
    @foreach($recaps as $row)
            <div class="logo">
               @if($isCompanyImage)
                    <img class="company-logo" src="{{$company->image}}"/>
                @endif
                <p>{{$company->name}}</p>
            </div>
        <table border="1">
            <tr>
                <td>Nama Karyawan</td>
                <td>{{ $row->user->name }}</td>
            </tr>
            <tr>
                <td>Bulan Ringkasan</td>
                <td>{{ $row->recap_month }}</td>
            </tr>
            <tr>
                <td>Hari Kerja</td>
                <td>{{ $row->work_day }} Hari, Dari {{$row->work_in_month}} </td>
            </tr>
            <tr>
                <td>Jumlah Absen</td>
                <td>{{ $row->abstain_count }} Hari</td>
            </tr>
            <tr>
                <td>Hari Terlambat</td>
                <td>{{ $row->late_day }}</td>
            </tr>
            <tr>
                <td>Total Menit Terlambat</td>
                <td>{{ $row->late_minute_count }}</td>
            </tr>
            @if($row->user->salary->extra_time_rule == 1)
            <tr>
                <td>Total Menit Lebih</td>
                <td>{{ $row->extra_time }}</td>
            </tr>
            @endif
            <tr>
                <td>Jenis Denda</td>
                <td>{{ isset($row->user->salary->fine_type) ? $row->user->salary->fine_type : '' }}</td>
            </tr>
            <tr>
                <td colspan="2" class="text-heading">
                    Gaji & Tambahan
                </td>
            </tr>
            @if($row->allowanceLines && $row->allowanceLines->count())
            <tr>
                <td>Gaji Pokok</td>
                <td>@rupiah($row->basic_salary)</td>
            </tr>
            @foreach($row->allowanceLines as $line)
            <tr>
                <td>{{ $line->label }}</td>
                <td>@rupiah($line->amount)</td>
            </tr>
            @endforeach
            <tr>
                <td><strong>Jumlah Gaji</strong></td>
                <td><strong>@rupiah($row->salary_amount)</strong></td>
            </tr>
            @else
            <tr>
                <td>Jumlah Gaji</td>
                <td>@rupiah($row->salary_amount)</td>
            </tr>
            @endif
            <tr>
                <td>Lembur</td>
                <td>@rupiah($row->overtime_amount)</td>
            </tr>
            @if($row->user->salary->extra_time_rule == 1)
            <tr>
                <td>Tambahan Waktu</td>
                <td>@rupiah($row->extra_time_amount)</td>
            </tr>
            @endif
            <tr>
                <td class="text-heading" colspan="2">
                    Potongan & Denda
                </td>
            </tr>
            <tr>
                <td>Potongan Absen</td>
                <td>@rupiah($row->abstain_cut)</td>
            </tr>
            <tr>
                <td>Potongan Terlambat</td>
                <td>@rupiah($row->late_cut)</td>
            </tr>
            <tr>
                <td>Potongan Pinjaman</td>
                <td>@rupiah($row->loan_cut)</td>
            </tr>
            <tr>
                <td colspan="2" class="text-heading">
                    Besaran Final & Status
                </td>
            </tr>
            <tr>
                <td>Diterima</td>
                <td>@rupiah($row->received)</td>
            </tr>
            <tr>
                <td>Status Dibayar</td>
                <td>{{ $row->paid ? 'Sudah Dibayar' : 'Belum' }}</td>
            </tr>
            <tr>
                <td>Keterangan</td>
                <td>{{ $row->desc }}</td>
            </tr>
            <tr>
                <td>Metode Pembayaran</td>
                <td>{{ $row->method }}</td>
            </tr>
        </table>
            @if($loop->index == $recaps->count())
                <div class="page-break"></div>
            @endif
    @endforeach
</body>
