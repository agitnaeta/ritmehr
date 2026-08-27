@extends('portal.layout')
@section('title', $training->title)
@section('heading', $training->title)

@section('content')
<div class="mb-3">
    <a href="{{ route('portal.training.index') }}" class="btn btn-sm btn-outline-secondary"><i class="la la-arrow-left"></i> Daftar Pelatihan</a>
</div>

@php $badge = ['enrolled'=>'secondary','passed'=>'success','failed'=>'warning','locked'=>'danger'][$enrollment->status] ?? 'secondary'; @endphp
<div class="alert alert-{{ $badge === 'secondary' ? 'light' : $badge }} d-flex justify-content-between align-items-center flex-wrap">
    <div>
        <span class="badge bg-{{ $badge }}">{{ $enrollment->statusLabel() }}</span>
        @if($enrollment->score !== null) · Skor terakhir: <strong>{{ $enrollment->score }}</strong> @endif
        · KKM: {{ $training->passing_score }} · Percobaan: {{ $enrollment->attempts }}/{{ $training->max_attempts }}
    </div>
    <div>
        @if($enrollment->isPassed())
            <a href="{{ route('portal.training.certificate', $training->id) }}" target="_blank" class="btn btn-sm btn-success">
                <i class="la la-certificate"></i> Sertifikat
            </a>
        @elseif($enrollment->isLocked())
            <span class="text-danger small">Kesempatan habis — hubungi HR.</span>
        @else
            <a href="{{ route('portal.training.quiz', $training->id) }}" class="btn btn-sm btn-primary">
                <i class="la la-pencil-alt"></i> {{ $enrollment->attempts > 0 ? 'Ulangi Latihan' : 'Mulai Latihan' }}
            </a>
        @endif
    </div>
</div>

@if($training->description)
    <div class="card mb-3"><div class="card-body">{{ $training->description }}</div></div>
@endif

<h5 class="mb-3">📚 Materi</h5>
@forelse($training->materials as $m)
    <div class="card mb-3">
        <div class="card-header"><strong>{{ $loop->iteration }}. {{ $m->title }}</strong></div>
        <div class="card-body">
            @if($m->content)<div class="mb-2" style="white-space:pre-line;">{{ $m->content }}</div>@endif
            @if($m->youtubeEmbedUrl())
                <div class="ratio ratio-16x9 mb-2" style="max-width:560px;">
                    <iframe src="{{ $m->youtubeEmbedUrl() }}" title="Video" allowfullscreen style="border:0;"></iframe>
                </div>
            @elseif($m->video_url)
                <a href="{{ $m->video_url }}" target="_blank" class="d-block mb-2">▶ Tonton video</a>
            @endif
            @if($m->attachment_path)
                <a href="#" class="text-primary small">📎 {{ basename($m->attachment_path) }}</a>
            @endif
        </div>
    </div>
@empty
    <div class="alert alert-secondary">Belum ada materi.</div>
@endforelse

@if(! $enrollment->isPassed() && ! $enrollment->isLocked() && $training->materials->isNotEmpty())
<div class="text-center my-4">
    <a href="{{ route('portal.training.quiz', $training->id) }}" class="btn btn-lg btn-success">
        Selesai Baca → Mulai Latihan
    </a>
</div>
@endif
@endsection
