@extends('portal.layout')
@section('title', 'Cuti Saya')
@section('heading', 'Cuti & Izin')

@section('content')
<div class="row g-3 mb-3">
    @forelse($balances as $balance)
        <div class="col-6 col-md-3">
            <div class="card stat-card">
                <div class="card-body">
                    <div class="text-muted small">{{ $balance->leaveType->name }}</div>
                    <div class="value">{{ $balance->remainingDays() }}</div>
                    <div class="text-muted small">dari {{ $balance->totalEntitlement() }} hari</div>
                </div>
            </div>
        </div>
    @empty
        <div class="col-12">
            <div class="alert alert-secondary mb-0">Belum ada saldo cuti untuk tahun ini.</div>
        </div>
    @endforelse
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong>Riwayat Pengajuan</strong>
        <a href="{{ route('portal.leave.create') }}" class="btn btn-sm btn-primary">
            <i class="la la-plus"></i> Ajukan Cuti
        </a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>Jenis</th>
                        <th>Periode</th>
                        <th class="text-end">Hari</th>
                        <th>Status</th>
                        <th>Alasan</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($requests as $req)
                        <tr>
                            <td>{{ $req->leaveType?->name ?? '—' }}</td>
                            <td>{{ $req->periodLabel() }}</td>
                            <td class="text-end">{{ (int) $req->total_days }}</td>
                            <td>
                                @php
                                    $badge = match($req->status) {
                                        'approved'  => 'success',
                                        'pending'   => 'warning',
                                        'rejected'  => 'danger',
                                        default     => 'secondary',
                                    };
                                @endphp
                                <span class="badge bg-{{ $badge }}">{{ $req->statusLabel() }}</span>
                            </td>
                            <td class="small text-muted">
                                {{ $req->status === 'rejected' ? $req->rejection_reason : $req->reason }}
                            </td>
                            <td class="text-end">
                                @if(in_array($req->status, ['pending', 'approved'], true))
                                    <form method="POST" action="{{ route('portal.leave.cancel', $req->id) }}"
                                          onsubmit="return confirm('Batalkan pengajuan ini?')">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-danger">Batalkan</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted p-4">Belum ada pengajuan cuti.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mt-3">{{ $requests->links() }}</div>
@endsection
