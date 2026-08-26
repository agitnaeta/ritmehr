@extends('career.layout')
@section('title', 'Lowongan')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="mb-0">Lowongan Tersedia</h3>
    <span class="text-muted">{{ $openings->count() }} posisi</span>
</div>

<div class="row g-3" id="job-list">
    @forelse($openings as $opening)
        <div class="col-md-6">
            <div class="card job-card h-100" data-slug="{{ $opening->slug }}">
                <div class="card-body">
                    <h5 class="card-title">{{ $opening->title }}</h5>
                    <div class="text-muted small mb-2">
                        <i class="la la-building"></i> {{ $opening->department?->name ?? 'Umum' }}
                        @if($opening->branch)· <i class="la la-map-marker"></i> {{ $opening->branch->name }}@endif
                    </div>
                    <p class="card-text small text-truncate-2">{{ \Illuminate\Support\Str::limit(strip_tags($opening->description), 120) }}</p>
                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <span class="small text-success fw-semibold">{{ $opening->salaryRangeLabel() }}</span>
                        <a href="{{ route('career.show', $opening->slug) }}" class="btn btn-sm btn-primary">
                            Lihat &amp; Lamar
                        </a>
                    </div>
                </div>
            </div>
        </div>
    @empty
        <div class="col-12">
            <div class="alert alert-info">Belum ada lowongan yang dibuka saat ini. Silakan cek kembali nanti.</div>
        </div>
    @endforelse
</div>
@endsection
