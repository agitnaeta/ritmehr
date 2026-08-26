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
            <div class="col-auto ms-auto">
                <div class="btn-group btn-group-sm" role="group">
                    <button type="button" class="btn btn-outline-secondary active" id="btnViewCalendar">
                        <i class="la la-calendar"></i> Kalender
                    </button>
                    <button type="button" class="btn btn-outline-secondary" id="btnViewTable">
                        <i class="la la-list"></i> Tabel
                    </button>
                </div>
            </div>
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

@php
    $start = $month->copy()->startOfMonth();
    $end = $month->copy()->endOfMonth();
    $gridStart = $start->copy()->startOfWeek(\Carbon\Carbon::MONDAY);
    $gridEnd = $end->copy()->endOfWeek(\Carbon\Carbon::SUNDAY);
@endphp

{{-- Calendar view --}}
<div class="card" id="viewCalendar">
    <div class="card-body p-2">
        <div class="mb-2 small text-muted d-flex flex-wrap gap-3">
            <span><span class="badge bg-success">&nbsp;</span> Tepat Waktu</span>
            <span><span class="badge bg-warning text-dark">&nbsp;</span> Terlambat</span>
            <span><span class="badge bg-info">&nbsp;</span> Lembur</span>
            <span><span class="badge bg-danger">&nbsp;</span> Luar Radius</span>
        </div>
        <table class="table table-bordered mb-0" style="table-layout: fixed;">
            <thead>
                <tr class="text-center">
                    @foreach(['Sen','Sel','Rab','Kam','Jum','Sab','Min'] as $d)
                        <th class="small">{{ $d }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @php $cursor = $gridStart->copy(); @endphp
                @while($cursor->lte($gridEnd))
                    <tr>
                        @for($i = 0; $i < 7; $i++)
                            @php
                                $key = $cursor->toDateString();
                                $inMonth = $cursor->month === $month->month;
                                $p = $byDate[$key] ?? null;
                            @endphp
                            <td class="align-top p-1 {{ $inMonth ? '' : 'bg-light text-muted' }}" style="height: 6.5rem;">
                                <div class="small fw-bold">{{ $cursor->day }}</div>
                                @if($p)
                                    <div class="small">
                                        <div>{{ \Carbon\Carbon::parse($p->in)->format('H:i') }}
                                            @if($p->out)&rarr; {{ \Carbon\Carbon::parse($p->out)->format('H:i') }}@endif
                                        </div>
                                        <div class="mt-1">
                                            @if($p->is_late)
                                                <span class="badge bg-warning text-dark" title="Telat {{ $p->late_minute }} mnt">Telat</span>
                                            @else
                                                <span class="badge bg-success">Hadir</span>
                                            @endif
                                            @if($p->is_overtime)<span class="badge bg-info">Lembur</span>@endif
                                            @if($p->outside)<span class="badge bg-danger">Luar</span>@endif
                                        </div>
                                    </div>
                                @endif
                            </td>
                            @php $cursor->addDay(); @endphp
                        @endfor
                    </tr>
                @endwhile
            </tbody>
        </table>
    </div>
</div>

{{-- Table view (hidden by default) --}}
<div class="card" id="viewTable" style="display:none;">
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

<script>
(function () {
    const cal = document.getElementById('viewCalendar');
    const tbl = document.getElementById('viewTable');
    const bCal = document.getElementById('btnViewCalendar');
    const bTbl = document.getElementById('btnViewTable');
    const KEY = 'portalAttendanceView';

    function show(view) {
        const isCal = view !== 'table';
        cal.style.display = isCal ? '' : 'none';
        tbl.style.display = isCal ? 'none' : '';
        bCal.classList.toggle('active', isCal);
        bTbl.classList.toggle('active', !isCal);
        try { localStorage.setItem(KEY, isCal ? 'calendar' : 'table'); } catch (e) {}
    }
    bCal.addEventListener('click', () => show('calendar'));
    bTbl.addEventListener('click', () => show('table'));
    try { show(localStorage.getItem(KEY) || 'calendar'); } catch (e) { show('calendar'); }
})();
</script>
@endsection
