@extends('portal.layout')
@section('title', 'Hasil Latihan')
@section('heading', 'Hasil Latihan')

@section('content')
@php $passed = $enrollment->isPassed(); $locked = $enrollment->isLocked(); @endphp
<div class="card">
    <div class="card-body text-center py-5">
        <div style="font-size:64px;font-weight:800;line-height:1;color:{{ $passed ? '#198754' : '#dc3545' }};">
            {{ $enrollment->score }}
        </div>
        <div class="my-3">
            @if($passed)
                <span class="badge bg-success" style="font-size:18px;padding:8px 22px;">✅ LULUS</span>
            @elseif($locked)
                <span class="badge bg-danger" style="font-size:18px;padding:8px 22px;">🔒 TERKUNCI</span>
            @else
                <span class="badge bg-warning text-dark" style="font-size:18px;padding:8px 22px;">❌ TIDAK LULUS</span>
            @endif
        </div>
        <div class="text-muted">
            {{ $training->title }} · KKM {{ $training->passing_score }} ·
            Percobaan {{ $enrollment->attempts }}/{{ $training->max_attempts }}
        </div>

        <div class="mt-4">
            @if($passed)
                <a href="{{ route('portal.training.certificate', $training->id) }}" target="_blank" class="btn btn-success">
                    <i class="la la-certificate"></i> Lihat / Cetak Sertifikat
                </a>
            @elseif($locked)
                <div class="alert alert-danger d-inline-block mb-0">Kesempatan mengerjakan sudah habis. Hubungi HR untuk reset.</div>
            @else
                <a href="{{ route('portal.training.quiz', $training->id) }}" class="btn btn-primary">
                    <i class="la la-redo"></i> Ulangi Latihan ({{ $training->max_attempts - $enrollment->attempts }}× lagi)
                </a>
            @endif
            <a href="{{ route('portal.training.show', $training->id) }}" class="btn btn-outline-secondary">Kembali ke Materi</a>
        </div>
    </div>
</div>
@endsection
