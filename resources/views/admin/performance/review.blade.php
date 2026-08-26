@extends(backpack_view('blank'))

@section('header')
    <section class="container-fluid">
        <h2>Penilaian Kinerja
            <small>{{ $review->user?->name }} — {{ $review->cycle?->name }}</small>
        </h2>
    </section>
@endsection

@php($finalized = $review->isFinalized())

@section('content')
<div class="row">
    <div class="col-lg-9">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><strong>Status:</strong> <span class="badge bg-secondary">{{ $review->statusLabel() }}</span></span>
                @if($review->final_score !== null)
                    <span><strong>Skor Akhir:</strong> {{ number_format($review->final_score, 2) }} / 5</span>
                @endif
            </div>
            <div class="card-body">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th style="width:40%">KPI</th>
                            <th class="text-center">Bobot</th>
                            <th class="text-center">Skor Diri (1-5)</th>
                            <th class="text-center">Skor Manajer (1-5)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($review->items as $item)
                            <tr>
                                <td>
                                    <strong>{{ $item->kpi?->name }}</strong>
                                    @if($item->kpi?->description)
                                        <div class="small text-muted">{{ $item->kpi->description }}</div>
                                    @endif
                                </td>
                                <td class="text-center">{{ $item->weight }}</td>
                                <td class="text-center">
                                    {{-- Self score belongs to the self-form below; show read-only here --}}
                                    {{ $item->self_score ?? '—' }}
                                </td>
                                <td class="text-center">
                                    {{ $item->manager_score ?? '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @if($weighted !== null)
                    <p class="text-muted">Rata-rata tertimbang skor manajer saat ini:
                        <strong>{{ number_format($weighted, 2) }} / 5</strong></p>
                @endif
            </div>
        </div>

        {{-- Self-review form (owner) --}}
        @if($isOwner && ! $finalized)
        <div class="card mt-3">
            <div class="card-header"><strong>Isi Self-Review</strong></div>
            <form method="POST" action="{{ backpack_url('performance/review/' . $review->id . '/self') }}" id="selfForm">
                @csrf
                <div class="card-body">
                    @foreach($review->items as $item)
                        <div class="mb-2 row align-items-center">
                            <label class="col-md-6 col-form-label">{{ $item->kpi?->name }}</label>
                            <div class="col-md-3">
                                <select name="scores[{{ $item->kpi_id }}]" class="form-control form-control-sm self-score">
                                    <option value="">—</option>
                                    @for($s = 1; $s <= 5; $s++)
                                        <option value="{{ $s }}" @selected($item->self_score == $s)>{{ $s }}</option>
                                    @endfor
                                </select>
                            </div>
                        </div>
                    @endforeach
                    <div class="mb-2">
                        <label class="form-label">Catatan</label>
                        <textarea name="comment" class="form-control" rows="2">{{ $review->self_comment }}</textarea>
                    </div>
                </div>
                <div class="card-footer">
                    <button class="btn btn-primary" id="submitSelf"><i class="la la-save"></i> Simpan Self-Review</button>
                </div>
            </form>
        </div>
        @endif

        {{-- Manager form --}}
        @if($isManager && ! $finalized)
        <div class="card mt-3">
            <div class="card-header"><strong>Penilaian Manajer</strong></div>
            <form method="POST" action="{{ backpack_url('performance/review/' . $review->id . '/manager') }}" id="managerForm">
                @csrf
                <div class="card-body">
                    @foreach($review->items as $item)
                        <div class="mb-2 row align-items-center">
                            <label class="col-md-6 col-form-label">
                                {{ $item->kpi?->name }}
                                <span class="small text-muted">(diri: {{ $item->self_score ?? '—' }})</span>
                            </label>
                            <div class="col-md-3">
                                <select name="scores[{{ $item->kpi_id }}]" class="form-control form-control-sm manager-score">
                                    <option value="">—</option>
                                    @for($s = 1; $s <= 5; $s++)
                                        <option value="{{ $s }}" @selected($item->manager_score == $s)>{{ $s }}</option>
                                    @endfor
                                </select>
                            </div>
                        </div>
                    @endforeach
                    <div class="mb-2">
                        <label class="form-label">Catatan Manajer</label>
                        <textarea name="comment" class="form-control" rows="2">{{ $review->manager_comment }}</textarea>
                    </div>
                </div>
                <div class="card-footer d-flex gap-2">
                    <button class="btn btn-primary" id="submitManager"><i class="la la-save"></i> Simpan Penilaian</button>
                </div>
            </form>
            {{-- Finalize is a separate deliberate action --}}
            <div class="card-footer border-top">
                <form method="POST" action="{{ backpack_url('performance/review/' . $review->id . '/finalize') }}"
                      onsubmit="return confirm('Finalisasi penilaian? Skor akhir dikunci & karyawan diberi tahu.');">
                    @csrf
                    <button class="btn btn-success" id="finalizeBtn"><i class="la la-lock"></i> Finalisasi Penilaian</button>
                    <small class="text-muted ms-2">Kunci skor akhir (rata-rata tertimbang skor manajer).</small>
                </form>
            </div>
        </div>
        @endif

        @if($finalized)
            <div class="alert alert-success mt-3">
                <i class="la la-check-circle"></i> Penilaian telah difinalisasi pada
                {{ $review->finalized_at?->format('d/m/Y H:i') }}.
                @if($review->manager_comment)<div class="mt-1"><strong>Catatan manajer:</strong> {{ $review->manager_comment }}</div>@endif
            </div>
        @endif

        <a href="{{ backpack_url('performance') }}" class="btn btn-outline-secondary mt-3">&laquo; Kembali</a>
    </div>
</div>
@endsection
