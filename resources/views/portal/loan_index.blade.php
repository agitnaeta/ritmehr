@extends('portal.layout')
@section('title', 'Kasbon')
@section('heading', 'Kasbon Saya')

@section('content')
<div class="d-flex justify-content-end mb-3">
    @if($hasPending)
        <span class="text-muted small align-self-center">Pengajuan kasbon Anda sedang menunggu persetujuan.</span>
    @else
        <a href="{{ route('portal.loan.create') }}" class="btn btn-primary">
            <i class="la la-plus"></i> Ajukan Kasbon
        </a>
    @endif
</div>

<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="card stat-card">
            <div class="card-body">
                <div class="text-muted small">Total Pinjaman</div>
                <div class="value">@rupiah($loans->where('status', 'approved')->sum('amount'))</div>
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
                    <thead><tr><th>Tanggal</th><th class="text-end">Jumlah</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        @forelse($loans as $loan)
                            <tr>
                                <td>{{ $loan->date }}</td>
                                <td class="text-end">@rupiah($loan->amount)</td>
                                <td>
                                    @include('portal.partials.loan_status', ['loan' => $loan])
                                </td>
                                <td class="text-end">
                                    @if($loan->status === 'pending')
                                        <form method="POST" action="{{ route('portal.loan.cancel', $loan->id) }}"
                                              onsubmit="return confirm('Batalkan pengajuan ini?')">
                                            @csrf
                                            <button class="btn btn-sm btn-outline-danger">Batalkan</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted p-4">Belum ada kasbon.</td></tr>
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
                            <div class="d-flex justify-content-between align-items-center mt-1">
                                @include('portal.partials.loan_status', ['loan' => $loan])
                                @if($loan->status === 'pending')
                                    <form method="POST" action="{{ route('portal.loan.cancel', $loan->id) }}"
                                          onsubmit="return confirm('Batalkan pengajuan ini?')">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-danger">Batalkan</button>
                                    </form>
                                @endif
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
