@extends('portal.layout')
@section('title', 'Pelatihan Saya')
@section('heading', 'Pelatihan Saya')

@section('content')
<div class="card">
    <div class="card-header"><strong>Pelatihan yang Ditugaskan</strong></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped mb-0 align-middle">
                <thead>
                    <tr><th>Pelatihan</th><th>Pelatih</th><th>Status</th><th class="text-end">Skor</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse($enrollments as $e)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $e->training->title }}</div>
                                @if($e->training->category)<span class="text-muted small">{{ $e->training->category }}</span>@endif
                            </td>
                            <td>{{ $e->training->trainer?->name ?? '—' }}</td>
                            <td>
                                @php $badge = ['enrolled'=>'secondary','passed'=>'success','failed'=>'warning','locked'=>'danger'][$e->status] ?? 'secondary'; @endphp
                                <span class="badge bg-{{ $badge }}">{{ $e->statusLabel() }}</span>
                            </td>
                            <td class="text-end">{{ $e->score !== null ? $e->score : '—' }}</td>
                            <td class="text-end">
                                <a href="{{ route('portal.training.show', $e->training_id) }}" class="btn btn-sm btn-primary">
                                    {{ $e->isPassed() ? 'Lihat' : 'Buka' }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted p-4">Belum ada pelatihan ditugaskan untuk Anda.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
