@extends(backpack_view('blank'))

@php
    use App\Models\Applicant;
    $stagePills = [
        Applicant::STAGE_APPLIED   => 'bg-secondary',
        Applicant::STAGE_SCREENING => 'bg-primary',
        Applicant::STAGE_INTERVIEW => 'bg-success',
        Applicant::STAGE_OFFER     => 'bg-warning text-dark',
        Applicant::STAGE_HIRED     => 'bg-dark',
    ];
    $sorts = [
        'ai_score'     => 'Skor AI',
        'vector_score' => 'Vektor',
        'created_at'   => 'Tanggal',
        'name'         => 'Nama',
    ];
@endphp

@section('header')
    <section class="container-fluid">
        <h2><i class="la la-trophy"></i> Peringkat Kandidat
            <small>urutan pelamar berdasarkan skor AI</small></h2>
    </section>
@endsection

@section('content')

{{-- Controls --}}
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end" id="ranking-controls">
            <div class="col-auto">
                <label class="form-label mb-0 small text-muted">Lowongan</label>
                <select name="job_opening_id" class="form-control form-control-sm" id="sel-opening"
                        onchange="this.form.submit()">
                    @foreach($openings as $o)
                        <option value="{{ $o->id }}" @selected($openingId == $o->id)>
                            {{ $o->title }} ({{ $applicantCounts[$o->id] ?? 0 }} pelamar)
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-auto">
                <label class="form-label mb-0 small text-muted">Urut berdasarkan</label>
                <div class="btn-group d-block" role="group">
                    @foreach($sorts as $key => $label)
                        <a href="{{ backpack_url('recruitment/ranking') }}?job_opening_id={{ $openingId }}&order_by={{ $key }}"
                           class="btn btn-sm {{ $orderBy === $key ? 'btn-primary' : 'btn-outline-secondary' }}">
                            {{ $label }}
                        </a>
                    @endforeach
                </div>
            </div>

            <div class="col-auto ms-auto d-flex gap-2">
                @if($canEdit && $openingId)
                <button type="button" class="btn btn-sm btn-primary" id="btn-rank-ai" data-opening="{{ $openingId }}">
                    <i class="la la-robot"></i> Ranking dengan AI
                </button>
                @endif
                <a href="{{ backpack_url('recruitment/pipeline') }}?job_opening_id={{ $openingId }}"
                   class="btn btn-sm btn-outline-secondary">
                    <i class="la la-columns"></i> Papan Pipeline
                </a>
            </div>
        </form>
    </div>
</div>

@if(! $openingId)
    <div class="alert alert-info">Pilih lowongan untuk melihat peringkat kandidat.</div>
@else

    {{-- Stat cards --}}
    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3">
            <div class="card h-100"><div class="card-body py-3">
                <div class="h3 mb-0 text-primary">{{ $stats['total'] }}</div>
                <div class="small text-muted">Pelamar masuk</div>
            </div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card h-100"><div class="card-body py-3">
                <div class="h3 mb-0 text-success">{{ $stats['ai_scored'] }}</div>
                <div class="small text-muted">Sudah dinilai AI</div>
            </div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card h-100"><div class="card-body py-3">
                <div class="h3 mb-0 text-warning">{{ $stats['unscored'] + $stats['vector_only'] }}</div>
                <div class="small text-muted">Belum dinilai</div>
            </div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card h-100"><div class="card-body py-3">
                <div class="h3 mb-0">{{ $stats['top_score'] !== null ? number_format($stats['top_score'], 0) : '—' }}</div>
                <div class="small text-muted">Skor tertinggi</div>
            </div></div>
        </div>
    </div>

    {{-- Ranking table --}}
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:56px;" class="text-center">#</th>
                        <th>Kandidat</th>
                        <th style="width:180px;">Skor AI</th>
                        <th>Ringkasan Penilaian AI</th>
                        <th style="width:130px;">Tahap</th>
                        <th style="width:90px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($applicants as $a)
                        @php
                            $rank = $rankMap[$a->id] ?? null;
                            $medal = ['','🥇','🥈','🥉'][$rank] ?? null;
                            $summary = $a->ai_reasoning['summary'] ?? null;
                        @endphp
                        <tr>
                            <td class="text-center">
                                @if($medal)
                                    <span style="font-size:1.3rem;" title="Peringkat {{ $rank }}">{{ $medal }}</span>
                                @else
                                    <span class="badge bg-light text-dark rounded-pill">{{ $rank }}</span>
                                @endif
                            </td>
                            <td>
                                <div class="fw-semibold">{{ $a->name }}</div>
                                <div class="small text-muted">{{ $a->email }}</div>
                            </td>
                            <td>
                                @if($a->ai_score !== null)
                                    <div class="fw-bold">{{ number_format($a->ai_score, 0) }}<span class="text-muted small">/100</span></div>
                                    <div style="height:6px;border-radius:4px;background:#eef1f5;overflow:hidden;width:120px;margin-top:4px;">
                                        <div style="height:100%;border-radius:4px;width:{{ min(100, max(0, $a->ai_score)) }}%;background:linear-gradient(90deg,#0d6efd,#0dcaf0);"></div>
                                    </div>
                                @elseif($a->vector_score !== null)
                                    <div class="fw-semibold text-muted">~{{ number_format($a->vector_score, 0) }} <span class="small">vektor</span></div>
                                    <span class="badge bg-warning-subtle text-warning border border-warning" style="font-size:.65rem;">belum dinilai AI</span>
                                @else
                                    <span class="badge bg-light text-muted">belum dinilai</span>
                                @endif
                            </td>
                            <td class="small text-body" style="max-width:340px;">
                                {{ $summary ?: ($a->vector_score !== null ? 'Baru shortlist Qdrant — klik “Ranking dengan AI” untuk skor penuh.' : '—') }}
                            </td>
                            <td>
                                <span class="badge {{ $stagePills[$a->stage] ?? 'bg-secondary' }}">{{ $a->stageLabel() }}</span>
                            </td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-primary btn-detail" data-id="{{ $a->id }}">Detail</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">Belum ada pelamar untuk lowongan ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center small text-muted flex-wrap gap-2">
            <span>Menampilkan {{ $applicants->count() }} pelamar · {{ $stats['ai_scored'] }} dinilai AI · {{ $stats['vector_only'] }} shortlist vektor</span>
            <span><i class="la la-info-circle"></i> AI = asisten pengurut, keputusan tetap di HR</span>
        </div>
    </div>

@endif

{{-- Shared detail drawer (M18/M21) --}}
@include('admin.recruitment.partials.detail-drawer')
@endsection

@section('after_scripts')
    @include('admin.recruitment.partials.detail-drawer-js')
    <script>
    (function () {
        var csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        var rankBtn = document.getElementById('btn-rank-ai');
        if (rankBtn) {
            rankBtn.addEventListener('click', function () {
                var openingId = rankBtn.dataset.opening;
                rankBtn.disabled = true;
                rankBtn.innerHTML = '<i class="la la-spinner la-spin"></i> Menilai...';
                fetch("{{ backpack_url('recruitment/opening') }}/" + openingId + '/rank', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                }).then(function (r) { return r.json(); })
                  .then(function (d) { alert(d.message || 'Selesai.'); location.reload(); })
                  .catch(function () {
                      alert('Gagal menjalankan ranking AI.');
                      rankBtn.disabled = false;
                      rankBtn.innerHTML = '<i class="la la-robot"></i> Ranking dengan AI';
                  });
            });
        }
    })();
    </script>
@endsection
