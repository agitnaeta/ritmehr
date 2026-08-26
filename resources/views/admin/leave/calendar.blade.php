@extends(backpack_view('blank'))

@section('header')
    <section class="container-fluid">
        <h2>Kalender Cuti <small>{{ $month->locale('id_ID')->isoFormat('MMMM YYYY') }}</small></h2>
    </section>
@endsection

@php
    $start = $month->copy()->startOfMonth();
    $end = $month->copy()->endOfMonth();
    // Grid starts on Monday.
    $gridStart = $start->copy()->startOfWeek(\Carbon\Carbon::MONDAY);
    $gridEnd = $end->copy()->endOfWeek(\Carbon\Carbon::SUNDAY);
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
                <label class="form-label mb-0">Departemen</label>
                <select name="department_id" class="form-control form-control-sm">
                    <option value="">Semua</option>
                    @foreach($departments as $d)
                        <option value="{{ $d->id }}" @selected($departmentId == $d->id)>{{ $d->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <button class="btn btn-sm btn-primary">Tampilkan</button>
            </div>
            <div class="col-auto">
                <a class="btn btn-sm btn-outline-secondary"
                   href="?month={{ $month->copy()->subMonth()->format('Y-m') }}&department_id={{ $departmentId }}">&laquo; Bulan lalu</a>
                <a class="btn btn-sm btn-outline-secondary"
                   href="?month={{ $month->copy()->addMonth()->format('Y-m') }}&department_id={{ $departmentId }}">Bulan depan &raquo;</a>
            </div>
        </form>
    </div>

    @if($leaveTypes->isNotEmpty())
        <div class="card-body py-2 border-bottom">
            <span class="small text-muted me-2">Keterangan:</span>
            @foreach($leaveTypes as $lt)
                <span class="badge me-2 mb-1" style="background: {{ $lt->color ?? '#3498db' }}; color:#fff;">
                    {{ $lt->name }}{{ $lt->is_paid ? '' : ' (tidak dibayar)' }}
                </span>
            @endforeach
        </div>
    @endif

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
                                @foreach($items as $entry)
                                    <div class="small text-truncate rounded px-1 mb-1"
                                         style="background: {{ $entry->leaveType?->color ?? '#3498db' }}; color:#fff;"
                                         title="{{ $entry->user?->name }} — {{ $entry->leaveType?->name }}">
                                        {{ $entry->user?->name }}
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
