@extends('portal.layout')
@section('title', 'Kehadiran')
@section('heading', 'Riwayat Kehadiran')

@section('content')
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label mb-0">Bulan</label>
                <input type="month" name="month" class="form-control form-control-sm"
                       value="{{ $month->format('Y-m') }}">
            </div>
            <div class="col-auto"><button class="btn btn-sm btn-primary">Tampilkan</button></div>
        </form>
    </div>
</div>

<div class="row g-3 mb-3">
    @foreach([
        ['Hadir', $summary['present'], 'text-success'],
        ['Terlambat', $summary['late'], 'text-warning'],
        ['Lembur', $summary['overtime'], 'text-info'],
        ['Di Luar Radius', $summary['outside'], 'text-danger'],
    ] as [$label, $value, $class])
        <div class="col-6 col-md-3">
            <div class="card stat-card">
                <div class="card-body">
                    <div class="text-muted small">{{ $label }}</div>
                    <div class="value {{ $class }}">{{ $value }}</div>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Masuk</th>
                        <th>Pulang</th>
                        <th>Status</th>
                        <th class="text-end">Telat (menit)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($presences as $p)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($p->in)->format('d/m/Y') }}</td>
                            <td>{{ \Carbon\Carbon::parse($p->in)->format('H:i') }}</td>
                            <td>{{ $p->out ? \Carbon\Carbon::parse($p->out)->format('H:i') : '—' }}</td>
                            <td>
                                @if($p->is_late)
                                    <span class="badge bg-warning text-dark">Terlambat</span>
                                @else
                                    <span class="badge bg-success">Tepat Waktu</span>
                                @endif
                                @if($p->is_overtime)<span class="badge bg-info">Lembur</span>@endif
                                @if($p->outside)<span class="badge bg-danger">Luar Radius</span>@endif
                            </td>
                            <td class="text-end">{{ $p->late_minute ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted p-4">
                            Belum ada kehadiran tercatat pada bulan ini.
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
