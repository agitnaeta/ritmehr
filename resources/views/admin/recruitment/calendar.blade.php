@extends(backpack_view('blank'))

@section('header')
    <section class="container-fluid">
        <h2>Jadwal Wawancara <small>{{ $month->locale('id_ID')->isoFormat('MMMM YYYY') }}</small></h2>
    </section>
@endsection

@php
    $start = $month->copy()->startOfMonth();
    $end = $month->copy()->endOfMonth();
    $gridStart = $start->copy()->startOfWeek(\Carbon\Carbon::MONDAY);
    $gridEnd = $end->copy()->endOfWeek(\Carbon\Carbon::SUNDAY);
    $modeColor = ['onsite' => '#2563eb', 'online' => '#059669', 'phone' => '#d97706'];
@endphp

@section('content')
<div class="card">
    <div class="card-header">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label mb-0">Bulan</label>
                <input type="month" name="month" class="form-control form-control-sm"
                       value="{{ $month->format('Y-m') }}">
            </div>
            <div class="col-auto">
                <button class="btn btn-sm btn-primary">Tampilkan</button>
            </div>
            <div class="col-auto">
                <a class="btn btn-sm btn-outline-secondary"
                   href="?month={{ $month->copy()->subMonth()->format('Y-m') }}">&laquo; Bulan lalu</a>
                <a class="btn btn-sm btn-outline-secondary"
                   href="?month={{ $month->copy()->addMonth()->format('Y-m') }}">Bulan depan &raquo;</a>
            </div>
            <div class="col-auto ms-auto">
                <a class="btn btn-sm btn-outline-secondary" href="{{ backpack_url('recruitment/pipeline') }}">
                    <i class="la la-columns"></i> Papan Pipeline
                </a>
            </div>
        </form>
    </div>

    <div class="card-body py-2 border-bottom">
        <span class="small text-muted me-2">Mode:</span>
        <span class="badge me-2" style="background:{{ $modeColor['onsite'] }}">Tatap Muka</span>
        <span class="badge me-2" style="background:{{ $modeColor['online'] }}">Online</span>
        <span class="badge me-2" style="background:{{ $modeColor['phone'] }}">Telepon</span>
    </div>

    <div class="card-body p-2">
        <table class="table table-bordered mb-0" style="table-layout: fixed;">
            <thead>
                <tr class="text-center">
                    @foreach(['Sen','Sel','Rab','Kam','Jum','Sab','Min'] as $dayLabel)
                        <th class="small">{{ $dayLabel }}</th>
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
                                $items = $byDate[$key] ?? [];
                            @endphp
                            <td class="align-top p-1 {{ $inMonth ? '' : 'bg-light text-muted' }}"
                                style="height: 7rem;">
                                <div class="small fw-bold">{{ $cursor->day }}</div>
                                @foreach($items as $iv)
                                    <div class="small text-truncate rounded px-1 mb-1"
                                         style="background: {{ $modeColor[$iv->mode] ?? '#6b7280' }}; color:#fff;"
                                         title="{{ $iv->scheduled_at->format('H:i') }} — {{ $iv->applicant?->name }} ({{ ucfirst($iv->mode) }})">
                                        {{ $iv->scheduled_at->format('H:i') }} {{ $iv->applicant?->name }}
                                    </div>
                                @endforeach
                            </td>
                            @php $cursor->addDay(); @endphp
                        @endfor
                    </tr>
                @endwhile
            </tbody>
        </table>
    </div>
</div>
@endsection
