@extends(backpack_view('blank'))

@php
    $flow = $approval->approvalFlow;
    $totalSteps = $flow?->totalSteps() ?? 0;
@endphp

@section('header')
    <section class="container-fluid">
        <h2>
            <span class="text-capitalize">Detail Persetujuan</span>
            <small>#{{ $approval->id }} — {{ $approval->statusLabel() }}</small>
            <small><a href="{{ url(config('backpack.base.route_prefix') . '/approval') }}"
                      class="d-print-none font-sm"><i class="la la-angle-double-left"></i> Kembali</a></small>
        </h2>
    </section>
@endsection

@section('content')
<div class="row">
    <div class="col-md-7">
        <div class="card">
            <div class="card-header"><strong>Ringkasan</strong></div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tr><th style="width:35%">Modul</th><td>{{ $flow?->module ?? '—' }}</td></tr>
                    <tr><th>Alur</th><td>{{ $flow?->name ?? '—' }}</td></tr>
                    <tr><th>Dokumen</th><td>{{ class_basename($approval->approvable_type) }} #{{ $approval->approvable_id }}</td></tr>
                    <tr><th>Pemohon</th><td>{{ $approval->requester?->name ?? '—' }}</td></tr>
                    <tr><th>Diajukan</th><td>{{ $approval->created_at }}</td></tr>
                    <tr>
                        <th>Status</th>
                        <td>
                            @php
                                $badge = match($approval->status) {
                                    'pending'   => 'warning',
                                    'approved'  => 'success',
                                    'rejected'  => 'danger',
                                    default     => 'secondary',
                                };
                            @endphp
                            <span class="badge bg-{{ $badge }}">{{ $approval->statusLabel() }}</span>
                            @if($approval->isPending())
                                <small class="text-muted">step {{ $approval->current_step }} dari {{ $totalSteps }}</small>
                            @endif
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><strong>Riwayat Tindakan</strong></div>
            <div class="card-body">
                @forelse($approval->actions as $action)
                    <div class="mb-2 pb-2 border-bottom">
                        <span class="badge bg-{{ $action->action === 'approve' ? 'success' : 'danger' }}">
                            {{ $action->action === 'approve' ? 'Disetujui' : 'Ditolak' }}
                        </span>
                        <strong>Step {{ $action->step_order }}</strong>
                        oleh {{ $action->actor?->name ?? '—' }}
                        <small class="text-muted">{{ $action->acted_at }}</small>
                        @if($action->notes)
                            <div class="text-muted mt-1">{{ $action->notes }}</div>
                        @endif
                    </div>
                @empty
                    <p class="text-muted mb-0">Belum ada tindakan.</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="col-md-5">
        <div class="card">
            <div class="card-header"><strong>Alur Persetujuan</strong></div>
            <div class="card-body">
                <ol class="mb-0 ps-3">
                    @foreach($flow?->flowSteps ?? [] as $step)
                        <li class="mb-1">
                            {{ $step->describe() }}
                            @if($approval->isPending() && $step->step_order === $approval->current_step)
                                <span class="badge bg-warning">sekarang</span>
                            @elseif($step->step_order < $approval->current_step || $approval->isApproved())
                                <span class="badge bg-success">selesai</span>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </div>
        </div>

        @if($canAct)
        <div class="card">
            <div class="card-header"><strong>Tindakan Anda</strong></div>
            <div class="card-body">
                <form method="POST" action="{{ url($crudRoute . '/' . $approval->id . '/approve') }}" class="mb-3">
                    @csrf
                    <div class="mb-2">
                        <label class="form-label">Catatan (opsional)</label>
                        <textarea name="notes" rows="2" class="form-control"></textarea>
                    </div>
                    <button type="submit" class="btn btn-success w-100">
                        <i class="la la-check"></i> Setujui
                    </button>
                </form>

                <form method="POST" action="{{ url($crudRoute . '/' . $approval->id . '/reject') }}">
                    @csrf
                    <div class="mb-2">
                        <label class="form-label">Alasan penolakan <span class="text-danger">*</span></label>
                        <textarea name="reason" rows="2" class="form-control"
                                  required minlength="3">{{ old('reason') }}</textarea>
                        @error('reason')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <button type="submit" class="btn btn-danger w-100">
                        <i class="la la-times"></i> Tolak
                    </button>
                </form>
            </div>
        </div>
        @endif

        @if($canCancel)
        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ url($crudRoute . '/' . $approval->id . '/cancel') }}"
                      onsubmit="return confirm('Batalkan pengajuan ini?')">
                    @csrf
                    <button type="submit" class="btn btn-outline-secondary w-100">
                        <i class="la la-ban"></i> Batalkan Pengajuan
                    </button>
                </form>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
