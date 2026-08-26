@extends(backpack_view('blank'))

@section('header')
    <section class="container-fluid">
        <h2>Dashboard <small>{{ now()->locale('id_ID')->isoFormat('dddd, D MMMM YYYY') }}</small></h2>
    </section>
@endsection

@php
    $statCard = function ($label, $value, $class = '', $sub = null) {
        echo '<div class="col-6 col-md-3 mb-3"><div class="card h-100"><div class="card-body">'
           . '<div class="text-muted small">' . e($label) . '</div>'
           . '<div class="h3 mb-0 ' . $class . '">' . $value . '</div>'
           . ($sub ? '<div class="text-muted small">' . e($sub) . '</div>' : '')
           . '</div></div></div>';
    };
@endphp

@section('content')

<h5 class="mb-2">Hari Ini</h5>
<div class="row">
    @php
        $statCard('Hadir', $today['present'], 'text-success', 'dari ' . $today['headcount'] . ' karyawan');
        $statCard('Belum Absen', $today['absent'], 'text-danger');
        $statCard('Terlambat', $today['late'], 'text-warning');
        $statCard('Di Luar Radius', $today['outside'], 'text-danger');
    @endphp
</div>

<h5 class="mb-2">Bulan Ini ({{ $recapMonth }})</h5>
<div class="row">
    @php
        $statCard('Total Gaji', money($month['salary']), 'text-success',
                  $month['recaps'] . ' rekap');
        $statCard('Total Lembur', money($month['overtime']), 'text-info');
        $statCard('Total Potongan', money($month['deductions']), 'text-warning');
        $statCard('Sisa Kasbon', money($month['loan_outstanding']), 'text-danger');
    @endphp
</div>

<div class="row">
    <div class="col-lg-8 mb-3">
        <div class="card h-100">
            <div class="card-header"><strong>Tren Kehadiran 12 Bulan</strong></div>
            <div class="card-body">
                <canvas id="attendanceTrend" height="90"></canvas>
            </div>
        </div>
    </div>

    <div class="col-lg-4 mb-3">
        <div class="card h-100">
            <div class="card-header"><strong>Headcount</strong></div>
            <div class="card-body">
                <div class="d-flex justify-content-between border-bottom py-1">
                    <span>Total Aktif</span><strong>{{ $headcount['total'] }}</strong>
                </div>
                @foreach($headcount['by_department']->take(6) as $dept)
                    <div class="d-flex justify-content-between py-1">
                        <span class="text-muted">{{ $dept['name'] }}</span>
                        <span>{{ $dept['count'] }}</span>
                    </div>
                @endforeach
                @if($headcount['unassigned'] > 0)
                    <div class="d-flex justify-content-between py-1 text-warning">
                        <span>Tanpa Departemen</span><span>{{ $headcount['unassigned'] }}</span>
                    </div>
                @endif
                <a href="{{ backpack_url('report/headcount') }}" class="btn btn-sm btn-link px-0">Lihat semua</a>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-6 mb-3">
        <div class="card h-100">
            <div class="card-header"><strong>Paling Sering Terlambat (bulan ini)</strong></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <tbody>
                        @forelse($latecomers as $i => $row)
                            <tr>
                                <td style="width:2rem">{{ $i + 1 }}.</td>
                                <td>{{ $row['user']->name }}</td>
                                <td class="text-end">
                                    <span class="badge bg-warning text-dark">{{ $row['count'] }}x</span>
                                    <span class="text-muted small">{{ $row['minutes'] }} mnt</span>
                                </td>
                            </tr>
                        @empty
                            <tr><td class="text-center text-muted p-3">Tidak ada keterlambatan bulan ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-6 mb-3">
        <div class="card h-100">
            <div class="card-header"><strong>Cuti Minggu Ini</strong></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <tbody>
                        @forelse($leaveThisWeek as $leave)
                            <tr>
                                <td>{{ $leave->user?->name ?? '—' }}</td>
                                <td>
                                    <span class="badge"
                                          style="background: {{ $leave->leaveType?->color ?? '#3498db' }}">
                                        {{ $leave->leaveType?->name ?? '—' }}
                                    </span>
                                </td>
                                <td class="text-end small text-muted">{{ $leave->periodLabel() }}</td>
                            </tr>
                        @empty
                            <tr><td class="text-center text-muted p-3">Tidak ada yang cuti minggu ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <strong>Laporan:</strong>
        <a href="{{ backpack_url('report/attendance') }}" class="btn btn-sm btn-outline-secondary">Kehadiran</a>
        <a href="{{ backpack_url('report/salary') }}" class="btn btn-sm btn-outline-secondary">Gaji</a>
        <a href="{{ backpack_url('report/loan') }}" class="btn btn-sm btn-outline-secondary">Kasbon</a>
        <a href="{{ backpack_url('leave-report') }}" class="btn btn-sm btn-outline-secondary">Cuti</a>
        <a href="{{ backpack_url('tax-report/annual') }}" class="btn btn-sm btn-outline-secondary">Pajak</a>
        <a href="{{ backpack_url('tax-report/bpjs') }}" class="btn btn-sm btn-outline-secondary">BPJS</a>
    </div>
</div>
@endsection

@section('after_scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
    var el = document.getElementById('attendanceTrend');
    if (!el || typeof Chart === 'undefined') return;

    new Chart(el, {
        type: 'line',
        data: {
            labels: @json($trend->pluck('label')),
            datasets: [
                {
                    label: 'Tingkat Kehadiran (%)',
                    data: @json($trend->pluck('rate')),
                    borderColor: '#2fb344',
                    backgroundColor: 'rgba(47,179,68,.12)',
                    fill: true,
                    tension: .3,
                },
                {
                    label: 'Keterlambatan',
                    data: @json($trend->pluck('late')),
                    borderColor: '#f59f00',
                    backgroundColor: 'rgba(245,159,0,.12)',
                    tension: .3,
                    yAxisID: 'y1',
                }
            ]
        },
        options: {
            responsive: true,
            interaction: { mode: 'index', intersect: false },
            scales: {
                y:  { beginAtZero: true, max: 100, title: { display: true, text: '%' } },
                y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false },
                      title: { display: true, text: 'kali telat' } }
            }
        }
    });
})();
</script>
@endsection
