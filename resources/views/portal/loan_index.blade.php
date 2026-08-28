@extends('portal.layout')
@section('title', 'Kasbon')
@section('heading', 'Kasbon Saya')

@section('content')
<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="card stat-card">
            <div class="card-body">
                <div class="text-muted small">Total Pinjaman</div>
                <div class="value">@rupiah($loans->sum('amount'))</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card">
            <div class="card-body">
                <div class="text-muted small">Sudah Dibayar</div>
                <div class="value text-success">@rupiah($payments->sum('amount'))</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card">
            <div class="card-body">
                <div class="text-muted small">Sisa</div>
                <div class="value text-danger">@rupiah($outstanding)</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><strong>Riwayat Pinjaman</strong></div>
            <div class="card-body p-0">
                <table class="table table-striped mb-0 hide-on-mobile">
                    <thead><tr><th>Tanggal</th><th class="text-end">Jumlah</th></tr></thead>
                    <tbody>
                        @forelse($loans as $loan)
                            <tr>
                                <td>{{ $loan->date }}</td>
                                <td class="text-end">@rupiah($loan->amount)</td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="text-center text-muted p-4">Belum ada kasbon.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="data-cards p-3">
                    @forelse($loans as $loan)
                        <div class="data-card">
                            <div class="data-card__top">
                                <div class="data-card__title">{{ $loan->date }}</div>
                                <div class="data-card__amt">@rupiah($loan->amount)</div>
                            </div>
                        </div>
                    @empty
                        <div class="empty-state"><i class="la la-hand-holding-usd"></i>Belum ada kasbon.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><strong>Riwayat Pembayaran</strong></div>
            <div class="card-body p-0">
                <table class="table table-striped mb-0 hide-on-mobile">
                    <thead><tr><th>Tanggal</th><th class="text-end">Jumlah</th></tr></thead>
                    <tbody>
                        @forelse($payments as $payment)
                            <tr>
                                <td>{{ $payment->date }}</td>
                                <td class="text-end text-success">@rupiah($payment->amount)</td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="text-center text-muted p-4">Belum ada pembayaran.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="data-cards p-3">
                    @forelse($payments as $payment)
                        <div class="data-card">
                            <div class="data-card__top">
                                <div class="data-card__title">{{ $payment->date }}</div>
                                <div class="data-card__amt text-success">@rupiah($payment->amount)</div>
                            </div>
                        </div>
                    @empty
                        <div class="empty-state"><i class="la la-money-bill-wave"></i>Belum ada pembayaran.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
