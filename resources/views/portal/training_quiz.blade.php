@extends('portal.layout')
@section('title', 'Latihan — ' . $training->title)
@section('heading', 'Latihan: ' . $training->title)

@section('content')
<div class="alert alert-info small">
    <i class="la la-info-circle"></i> Jawab semua soal, lalu klik <b>Kumpulkan Jawaban</b>.
    Lulus jika skor ≥ {{ $training->passing_score }}. Percobaan ke-{{ $enrollment->attempts + 1 }} dari {{ $training->max_attempts }}.
</div>

<form method="POST" action="{{ route('portal.training.submit', $training->id) }}" id="quiz-form">
    @csrf
    @foreach($training->questions as $q)
        <div class="card mb-3">
            <div class="card-body">
                <div class="fw-semibold mb-2">{{ $loop->iteration }}. {{ $q->question }}</div>
                @foreach($q->options() as $key => $text)
                    <label class="d-block border rounded p-2 mb-2" style="cursor:pointer;">
                        <input type="radio" name="answers[{{ $q->id }}]" value="{{ $key }}" class="me-2" required>
                        <span class="fw-semibold me-1">{{ strtoupper($key) }}.</span> {{ $text }}
                    </label>
                @endforeach
            </div>
        </div>
    @endforeach

    <div class="text-end mb-4">
        <a href="{{ route('portal.training.show', $training->id) }}" class="btn btn-outline-secondary">Batal</a>
        <button type="submit" class="btn btn-primary" onclick="return confirm('Kumpulkan jawaban sekarang?')">
            Kumpulkan Jawaban
        </button>
    </div>
</form>
@endsection
