@extends('portal.layout')
@section('title', 'Beranda')
@section('heading', 'Halo, ' . $user->name)

@section('content')
{{-- M22 — mode-aware attendance action --}}
@php $attMode = setting('attendance_mode', 'qr'); @endphp
@if($attMode === 'camera')
    <div class="card mb-4 border-0" style="background:linear-gradient(135deg,#2563eb,#1e3a8a);">
        <div class="card-body d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="text-white">
                <div class="fw-bold fs-5"><i class="la la-camera"></i> Absen Mandiri</div>
                <div class="small opacity-75">Ambil foto selfie + lokasi langsung dari HP Anda.</div>
            </div>
            <a href="{{ route('portal.attendance.checkin') }}" class="btn btn-light btn-lg fw-semibold btn-block-mobile">
                <i class="la la-fingerprint"></i> Absen Sekarang
            </a>
        </div>
    </div>
@else
    <div class="alert alert-info d-flex align-items-center gap-2 mb-4">
        <i class="la la-qrcode la-2x"></i>
        <div>
            <b>Mode Absensi: QR</b><br>
            <span class="small">Pindai QR pribadi Anda di perangkat pemindai yang tersedia di pintu masuk.</span>
        </div>
    </div>
@endif

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card stat-card h-100">
            <div class="card-body">
                <div class="text-muted small">Hadir Bulan Ini</div>
                <div class="value">{{ $presentDays }}</div>
                <div class="text-muted small">hari</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card h-100">
            <div class="card-body">
                <div class="text-muted small">Terlambat</div>
                <div class="value text-warning">{{ $lateDays }}</div>
                <div class="text-muted small">hari</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card h-100">
            <div class="card-body">
                <div class="text-muted small">Lembur</div>
                <div class="value text-info">{{ $overtimeDays }}</div>
                <div class="text-muted small">hari</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card h-100">
            <div class="card-body">
                <div class="text-muted small">Sisa Kasbon</div>
                <div class="value text-danger">@rupiah($outstandingLoan)</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between">
                <strong>Saldo Cuti {{ now()->year }}</strong>
                <a href="{{ route('portal.leave.create') }}" class="btn btn-sm btn-primary">Ajukan Cuti</a>
            </div>
            <div class="card-body">
                @forelse($balances as $balance)
                    <div class="d-flex justify-content-between border-bottom py-2">
                        <span>{{ $balance->leaveType->name }}</span>
                        <span>
                            <strong>{{ $balance->remainingDays() }}</strong>
                            <span class="text-muted small">
                                / {{ $balance->totalEntitlement() }} hari
                            </span>
                        </span>
                    </div>
                @empty
                    <p class="text-muted mb-0">Belum ada saldo cuti untuk tahun ini.</p>
                @endforelse

                @if($pendingLeave > 0)
                    <div class="alert alert-info mt-3 mb-0 py-2">
                        {{ $pendingLeave }} pengajuan cuti sedang menunggu persetujuan.
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between">
                <strong>Gaji Terakhir</strong>
                <a href="{{ route('portal.salary.index') }}" class="btn btn-sm btn-outline-secondary">Semua Slip</a>
            </div>
            <div class="card-body">
                @if($latestSalary)
                    <div class="d-flex justify-content-between py-1">
                        <span class="text-muted">Periode</span>
                        <strong>{{ $latestSalary->recap_month }}</strong>
                    </div>
                    <div class="d-flex justify-content-between py-1">
                        <span class="text-muted">Diterima</span>
                        <strong class="text-success">@rupiah($latestSalary->received)</strong>
                    </div>
                    <div class="d-flex justify-content-between py-1">
                        <span class="text-muted">Status</span>
                        <span>
                            @if($latestSalary->paid)
                                <span class="badge bg-success">Sudah dibayar</span>
                            @else
                                <span class="badge bg-secondary">Belum dibayar</span>
                            @endif
                        </span>
                    </div>
                    <a href="{{ route('portal.salary.show', $latestSalary->id) }}"
                       class="btn btn-sm btn-outline-primary mt-2">Lihat Detail</a>
                @else
                    <p class="text-muted mb-0">Belum ada rekap gaji.</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
