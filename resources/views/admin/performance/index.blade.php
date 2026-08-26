@extends(backpack_view('blank'))

@section('header')
    <section class="container-fluid">
        <h2>Penilaian Kinerja <small>penilaian saya &amp; tim</small></h2>
    </section>
@endsection

@section('content')
{{-- Generate reviews per cycle (manager/HR only) --}}
@if($canManage)
<div class="card mb-3">
    <div class="card-header"><strong>Buat Penilaian per Siklus</strong></div>
    <div class="card-body">
        @forelse($cycles as $cycle)
            <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                <div>
                    <strong>{{ $cycle->name }}</strong>
                    <span class="badge bg-secondary">{{ ucfirst($cycle->status) }}</span>
                    <div class="small text-muted">
                        {{ $cycle->start_date?->format('d/m/Y') }} – {{ $cycle->end_date?->format('d/m/Y') }}
                        · {{ $cycle->reviews()->count() }} review
                    </div>
                </div>
                <div>
                    <a href="{{ backpack_url('performance/scoreboard?cycle_id=' . $cycle->id) }}"
                       class="btn btn-sm btn-outline-secondary">Papan Skor</a>
                    <form method="POST" action="{{ backpack_url('performance/cycle/' . $cycle->id . '/generate') }}"
                          class="d-inline" onsubmit="return confirm('Buat penilaian untuk semua karyawan di siklus ini?');">
                        @csrf
                        <button class="btn btn-sm btn-primary" id="gen-{{ $cycle->id }}">
                            <i class="la la-plus"></i> Buat Penilaian
                        </button>
                    </form>
                </div>
            </div>
        @empty
            <p class="text-muted mb-0">Belum ada siklus. Buat di menu <a href="{{ backpack_url('review-cycle') }}">Siklus Penilaian</a>.</p>
        @endforelse
    </div>
</div>
@endif

{{-- Reviews I must score as manager --}}
@if($canManage && $toReview->isNotEmpty())
<div class="card mb-3">
    <div class="card-header"><strong>Menunggu Penilaian Anda (sebagai Manajer)</strong></div>
    <div class="card-body p-0">
        <table class="table table-sm mb-0">
            <thead><tr><th>Karyawan</th><th>Siklus</th><th>Status</th><th></th></tr></thead>
            <tbody>
                @foreach($toReview as $r)
                    <tr>
                        <td>{{ $r->user?->name }}</td>
                        <td>{{ $r->cycle?->name }}</td>
                        <td><span class="badge bg-info text-dark">{{ $r->statusLabel() }}</span></td>
                        <td class="text-end">
                            <a href="{{ backpack_url('performance/review/' . $r->id) }}"
                               class="btn btn-sm btn-primary">Nilai</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- My own reviews --}}
<div class="card">
    <div class="card-header"><strong>Penilaian Saya</strong></div>
    <div class="card-body p-0">
        <table class="table table-sm mb-0">
            <thead><tr><th>Siklus</th><th>Status</th><th>Skor Akhir</th><th></th></tr></thead>
            <tbody>
                @forelse($mine as $r)
                    <tr>
                        <td>{{ $r->cycle?->name }}</td>
                        <td><span class="badge bg-secondary">{{ $r->statusLabel() }}</span></td>
                        <td>{{ $r->final_score !== null ? number_format($r->final_score, 2) . ' / 5' : '—' }}</td>
                        <td class="text-end">
                            <a href="{{ backpack_url('performance/review/' . $r->id) }}"
                               class="btn btn-sm btn-outline-primary">
                                {{ $r->isFinalized() ? 'Lihat' : 'Isi Self-Review' }}
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted p-3">Belum ada penilaian untuk Anda.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
