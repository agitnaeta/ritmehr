@extends('career.layout')
@section('title', 'Akun Saya')

@php
    $stageLabels = \App\Models\Applicant::STAGE_LABELS;
    $stageColors = [
        'applied' => 'secondary', 'screening' => 'info', 'interview' => 'primary',
        'offer' => 'warning', 'hired' => 'success', 'rejected' => 'danger',
    ];
@endphp

@section('content')
<div class="row">
    <div class="col-md-4 mb-3">
        <div class="card">
            <div class="card-body text-center">
                <div class="display-6"><i class="la la-user-circle"></i></div>
                <h5 class="mb-0">{{ $candidate->name }}</h5>
                <div class="text-muted small">{{ $candidate->email }}</div>
                @if($candidate->phone)<div class="text-muted small">{{ $candidate->phone }}</div>@endif
                <a href="{{ route('career.index') }}" class="btn btn-sm btn-outline-primary mt-3">Cari Lowongan Lain</a>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card">
            <div class="card-header"><strong>Lamaran Saya</strong> ({{ $applications->count() }})</div>
            <div class="card-body p-0">
                <table class="table mb-0" id="apps-table">
                    <thead><tr><th>Posisi</th><th>Tanggal</th><th>Status</th></tr></thead>
                    <tbody>
                        @forelse($applications as $app)
                            <tr data-stage="{{ $app->stage }}">
                                <td>{{ $app->jobOpening?->title ?? '—' }}</td>
                                <td class="small text-muted">{{ $app->created_at->format('d/m/Y') }}</td>
                                <td>
                                    <span class="badge bg-{{ $stageColors[$app->stage] ?? 'secondary' }}">
                                        {{ $stageLabels[$app->stage] ?? $app->stage }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-muted p-4">
                                Belum ada lamaran. <a href="{{ route('career.index') }}">Cari lowongan</a>.
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
