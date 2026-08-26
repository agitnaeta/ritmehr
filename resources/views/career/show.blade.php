@extends('career.layout')
@section('title', $opening->title)

@section('content')
<a href="{{ route('career.index') }}" class="btn btn-sm btn-outline-secondary mb-3">&laquo; Semua Lowongan</a>

<div class="row">
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-body">
                <h3>{{ $opening->title }}</h3>
                <div class="text-muted mb-3">
                    <i class="la la-building"></i> {{ $opening->department?->name ?? 'Umum' }}
                    @if($opening->branch)· <i class="la la-map-marker"></i> {{ $opening->branch->name }}@endif
                    · <span class="text-success">{{ $opening->salaryRangeLabel() }}</span>
                </div>

                @if($opening->description)
                    <h6>Deskripsi</h6>
                    <div class="mb-3">{!! nl2br(e($opening->description)) !!}</div>
                @endif

                @if(!empty($opening->required_skills))
                    <h6>Keahlian yang Dibutuhkan</h6>
                    <div class="mb-3">
                        @foreach($opening->required_skills as $skill)
                            <span class="badge bg-secondary">{{ $skill }}</span>
                        @endforeach
                    </div>
                @endif

                @if($opening->min_experience_years)
                    <p class="small text-muted mb-0">Minimal pengalaman: {{ $opening->min_experience_years }} tahun
                        @if($opening->education_min)· Pendidikan min: {{ $opening->education_min }}@endif
                    </p>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><strong>Lamar Posisi Ini</strong></div>
            <div class="card-body">
                @guest('candidate')
                    <p class="small text-muted">Masuk atau daftar akun kandidat untuk melamar.</p>
                    <a href="{{ route('career.login') }}" class="btn btn-primary w-100 mb-2" id="btn-login-to-apply">Masuk untuk Melamar</a>
                    <a href="{{ route('career.register') }}" class="btn btn-outline-primary w-100">Daftar Akun</a>
                @else
                    @if($alreadyApplied)
                        <div class="alert alert-success mb-2" id="already-applied">
                            <i class="la la-check-circle"></i> Anda sudah melamar posisi ini.
                        </div>
                        <a href="{{ route('career.dashboard') }}" class="btn btn-outline-secondary w-100">Lihat Status Lamaran</a>
                    @elseif(! $opening->isOpenForApplication())
                        <div class="alert alert-warning mb-0">Lowongan ini sudah ditutup.</div>
                    @else
                        <form method="POST" action="{{ route('career.apply', $opening->slug) }}"
                              enctype="multipart/form-data" id="apply-form">
                            @csrf
                            <div class="mb-2">
                                <label class="form-label small">Unggah CV (PDF/DOC, maks 5MB)</label>
                                <input type="file" name="cv" class="form-control form-control-sm" accept=".pdf,.doc,.docx" required>
                            </div>
                            <div class="mb-2">
                                <label class="form-label small">Ekspektasi Gaji (opsional)</label>
                                <input type="number" name="expected_salary" class="form-control form-control-sm">
                            </div>
                            <div class="mb-2">
                                <label class="form-label small">Catatan / Surat Lamaran (opsional)</label>
                                <textarea name="cover_note" class="form-control form-control-sm" rows="3"></textarea>
                            </div>
                            <button class="btn btn-primary w-100" id="btn-submit-apply">
                                <i class="la la-paper-plane"></i> Kirim Lamaran
                            </button>
                        </form>
                    @endif
                @endguest
            </div>
        </div>
    </div>
</div>
@endsection
