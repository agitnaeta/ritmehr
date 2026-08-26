@extends(backpack_view('blank'))

@section('header')
    <section class="container-fluid">
        <h2>Papan Skor Kinerja <small>{{ $cycle?->name ?? 'pilih siklus' }}</small></h2>
    </section>
@endsection

@php
    $scored = $rows->filter(fn ($r) => $r['score'] !== null);
    $avg = $scored->count() ? round($scored->avg('score'), 2) : null;
@endphp

@section('content')
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label mb-0">Siklus</label>
                <select name="cycle_id" class="form-control form-control-sm" onchange="this.form.submit()">
                    @foreach($cycles as $c)
                        <option value="{{ $c->id }}" @selected($cycle && $cycle->id == $c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
        </form>
    </div>
</div>

@if(!$cycle)
    <div class="alert alert-info">Belum ada siklus penilaian.</div>
@else
    <div class="row">
        <div class="col-lg-8 mb-3">
            <div class="card h-100">
                <div class="card-header"><strong>Skor Akhir per Karyawan</strong></div>
                <div class="card-body">
                    <canvas id="scoreChart" height="120"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-4 mb-3">
            <div class="card h-100">
                <div class="card-body text-center d-flex flex-column justify-content-center">
                    <div class="text-muted">Rata-rata Skor (finalisasi)</div>
                    <div class="display-5">{{ $avg !== null ? number_format($avg, 2) : '—' }}</div>
                    <div class="text-muted small">{{ $scored->count() }} dari {{ $rows->count() }} review difinalisasi</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <table class="table table-sm mb-0">
                <thead><tr><th>Karyawan</th><th>Status</th><th class="text-end">Skor Akhir</th></tr></thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td>{{ $row['user']?->name ?? '—' }}</td>
                            <td><span class="badge bg-secondary">{{ \App\Models\Review::STATUS_LABELS[$row['status']] ?? $row['status'] }}</span></td>
                            <td class="text-end">{{ $row['score'] !== null ? number_format($row['score'], 2) . ' / 5' : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center text-muted p-3">Belum ada penilaian di siklus ini. Buat lewat "Penilaian Saya".</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endif
@endsection

@section('after_scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
    var el = document.getElementById('scoreChart');
    if (!el || typeof Chart === 'undefined') return;
    new Chart(el, {
        type: 'bar',
        data: {
            labels: @json($scored->map(fn ($r) => $r['user']?->name ?? '—')->values()),
            datasets: [{
                label: 'Skor Akhir',
                data: @json($scored->map(fn ($r) => $r['score'])->values()),
                backgroundColor: 'rgba(37,99,235,.6)',
                borderColor: '#2563eb',
                borderWidth: 1,
            }]
        },
        options: {
            responsive: true,
            scales: { y: { beginAtZero: true, max: 5, title: { display: true, text: 'skor (0-5)' } } },
            plugins: { legend: { display: false } }
        }
    });
})();
</script>
@endsection
