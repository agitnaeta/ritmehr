@extends('portal.layout')
@section('title', 'Detail Slip Gaji')
@section('heading', 'Slip Gaji ' . $recap->recap_month)

@section('content')
<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between">
                <strong>{{ $user->name }}</strong>
                <span class="text-muted">{{ $recap->recap_month }}</span>
            </div>
            <div class="card-body">
                <h6 class="text-success">Pendapatan</h6>
                <table class="table table-sm">
                    <tr><td>Gaji Pokok</td><td class="text-end">@rupiah($recap->salary_amount)</td></tr>
                    <tr><td>Lembur</td><td class="text-end">@rupiah($recap->overtime_amount)</td></tr>
                    <tr><td>Jam Tambahan</td><td class="text-end">@rupiah($recap->extra_time_amount)</td></tr>
                    <tr class="table-light">
                        <td><strong>Total Pendapatan</strong></td>
                        <td class="text-end"><strong>
                            @rupiah($recap->salary_amount + $recap->overtime_amount + $recap->extra_time_amount)
                        </strong></td>
                    </tr>
                </table>

                <h6 class="text-danger mt-4">Potongan</h6>
                <table class="table table-sm">
                    <tr><td>Kasbon</td><td class="text-end">@rupiah($recap->loan_cut)</td></tr>
                    <tr><td>Keterlambatan ({{ $recap->late_day }} hari / {{ $recap->late_minute_count }} menit)</td>
                        <td class="text-end">@rupiah($recap->late_cut)</td></tr>
                    <tr><td>Ketidakhadiran ({{ $recap->abstain_count }} hari)</td>
                        <td class="text-end">@rupiah($recap->abstain_cut)</td></tr>
                    <tr class="table-light">
                        <td><strong>Total Potongan</strong></td>
                        <td class="text-end"><strong>
                            @rupiah($recap->loan_cut + $recap->late_cut + $recap->abstain_cut)
                        </strong></td>
                    </tr>
                </table>

                <div class="d-flex justify-content-between align-items-center border-top pt-3 mt-3">
                    <h5 class="mb-0">Diterima</h5>
                    <h4 class="mb-0 text-success">@rupiah($recap->received)</h4>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><strong>Ringkasan</strong></div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tr><td>Hari Kerja</td><td class="text-end">{{ $recap->work_day }}</td></tr>
                    <tr><td>Hari Telat</td><td class="text-end">{{ $recap->late_day }}</td></tr>
                    <tr><td>Tidak Hadir</td><td class="text-end">{{ $recap->abstain_count }}</td></tr>
                    <tr><td>Metode</td><td class="text-end">{{ ucfirst($recap->method) }}</td></tr>
                    <tr>
                        <td>Status</td>
                        <td class="text-end">
                            @if($recap->paid)
                                <span class="badge bg-success">Dibayar</span>
                            @else
                                <span class="badge bg-secondary">Belum</span>
                            @endif
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        @if($recap->desc)
            <div class="card">
                <div class="card-header"><strong>Catatan</strong></div>
                <div class="card-body">{!! nl2br(e($recap->desc)) !!}</div>
            </div>
        @endif

        <a href="{{ route('portal.salary.index') }}" class="btn btn-outline-secondary w-100">Kembali</a>
    </div>
</div>
@endsection
