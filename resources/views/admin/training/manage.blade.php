@extends(backpack_view('blank'))

@php use App\Models\Training; use App\Models\TrainingEnrollment; @endphp

@section('header')
    <section class="container-fluid">
        <h2>
            <i class="la la-graduation-cap"></i> {{ $training->title }}
            @php $badge = ['draft'=>'bg-secondary','published'=>'bg-success','archived'=>'bg-dark'][$training->status] ?? 'bg-secondary'; @endphp
            <span class="badge {{ $badge }}">{{ $training->statusLabel() }}</span>
        </h2>
        <div class="text-muted small">
            Pelatih: {{ $training->trainer?->name ?? '—' }} · KKM: {{ $training->passing_score }} ·
            Maks percobaan: {{ $training->max_attempts }}
            @if($training->category) · {{ $training->category }} @endif
        </div>
    </section>
@endsection

@section('content')
<div class="mb-3 d-flex gap-2 flex-wrap">
    <a href="{{ backpack_url('training') }}" class="btn btn-sm btn-outline-secondary"><i class="la la-arrow-left"></i> Daftar</a>
    @if($canEdit)
        @if($training->status !== Training::STATUS_PUBLISHED)
        <form method="POST" action="{{ backpack_url('training/'.$training->id.'/publish') }}">@csrf
            <button class="btn btn-sm btn-success" type="submit"><i class="la la-check"></i> Terbitkan</button>
        </form>
        @endif
        @if($training->status === Training::STATUS_ARCHIVED)
        <form method="POST" action="{{ backpack_url('training/'.$training->id.'/restore') }}">@csrf
            <button class="btn btn-sm btn-outline-primary" type="submit"><i class="la la-undo"></i> Pulihkan</button>
        </form>
        @else
        <form method="POST" action="{{ backpack_url('training/'.$training->id.'/archive') }}"
              onsubmit="return confirm('Arsipkan pelatihan ini? Data tetap tersimpan.')">@csrf
            <button class="btn btn-sm btn-outline-dark" type="submit"><i class="la la-archive"></i> Arsipkan</button>
        </form>
        @endif
    @endif
</div>

<ul class="nav nav-tabs" role="tablist">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-materi" type="button">📚 Materi ({{ $training->materials->count() }})</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-latihan" type="button">📝 Latihan ({{ $training->questions->count() }})</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-peserta" type="button">👥 Peserta ({{ $enrollments->count() }})</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-detail" type="button">⚙️ Detail</button></li>
</ul>

<div class="tab-content border border-top-0 rounded-bottom p-3 bg-white">
    {{-- ═══ MATERI ═══ --}}
    <div class="tab-pane fade show active" id="tab-materi">
        @forelse($training->materials as $m)
            <div class="border rounded p-3 mb-2 bg-light">
                <div class="d-flex align-items-center gap-2 mb-1">
                    <span class="badge bg-primary rounded-pill">{{ $loop->iteration }}</span>
                    <span class="fw-semibold flex-fill">{{ $m->title }}</span>
                    @if($canEdit)
                        <form method="POST" action="{{ backpack_url('training/'.$training->id.'/material/'.$m->id.'/move') }}" class="d-inline">@csrf
                            <input type="hidden" name="dir" value="up"><button class="btn btn-sm btn-link p-0 px-1" title="Naik">↑</button></form>
                        <form method="POST" action="{{ backpack_url('training/'.$training->id.'/material/'.$m->id.'/move') }}" class="d-inline">@csrf
                            <input type="hidden" name="dir" value="down"><button class="btn btn-sm btn-link p-0 px-1" title="Turun">↓</button></form>
                        <form method="POST" action="{{ backpack_url('training/'.$training->id.'/material/'.$m->id.'/delete') }}" class="d-inline"
                              onsubmit="return confirm('Hapus materi ini?')">@csrf
                            <button class="btn btn-sm btn-link text-danger p-0 px-1" title="Hapus"><i class="la la-trash"></i></button></form>
                    @endif
                </div>
                @if($m->content)<div class="small text-body mb-1">{{ \Illuminate\Support\Str::limit(strip_tags($m->content), 200) }}</div>@endif
                @if($m->attachment_path)<div class="small text-primary">📎 {{ basename($m->attachment_path) }}</div>@endif
                @if($m->video_url)<div class="small text-danger">▶ {{ $m->video_url }}</div>@endif
            </div>
        @empty
            <div class="text-muted mb-3">Belum ada materi. Tambahkan bab pertama di bawah.</div>
        @endforelse

        @if($canEdit)
        <form method="POST" action="{{ backpack_url('training/'.$training->id.'/material') }}" enctype="multipart/form-data"
              class="border-2 border-dashed rounded p-3 mt-3" style="border:2px dashed #ccc;">
            @csrf
            <div class="fw-semibold mb-2">➕ Tambah Materi Baru</div>
            <div class="mb-2"><label class="form-label small mb-0">Judul Materi *</label>
                <input type="text" name="title" class="form-control form-control-sm" required placeholder="mis. Alat Pelindung Diri (APD)"></div>
            <div class="mb-2"><label class="form-label small mb-0">Isi Materi</label>
                <textarea name="content" class="form-control form-control-sm" rows="3" placeholder="Tulis penjelasan materi…"></textarea></div>
            <div class="row">
                <div class="col-md-6 mb-2"><label class="form-label small mb-0">Lampiran (PDF/gambar, ≤10MB)</label>
                    <input type="file" name="attachment" class="form-control form-control-sm"></div>
                <div class="col-md-6 mb-2"><label class="form-label small mb-0">URL Video YouTube</label>
                    <input type="url" name="video_url" class="form-control form-control-sm" placeholder="https://youtu.be/..."></div>
            </div>
            <button class="btn btn-sm btn-primary" type="submit"><i class="la la-plus"></i> Tambah Materi</button>
        </form>
        @endif
    </div>

    {{-- ═══ LATIHAN ═══ --}}
    <div class="tab-pane fade" id="tab-latihan">
        <div class="alert alert-info small py-2"><i class="la la-info-circle"></i>
            Nilai otomatis = jawaban benar × (100 ÷ jumlah soal). Skor ≥ {{ $training->passing_score }} → LULUS.</div>

        @forelse($training->questions as $q)
            <div class="border rounded p-3 mb-2">
                <div class="d-flex gap-2 mb-2">
                    <span class="badge bg-primary rounded-pill">{{ $loop->iteration }}</span>
                    <span class="fw-semibold flex-fill">{{ $q->question }}</span>
                    @if($canEdit)
                        <form method="POST" action="{{ backpack_url('training/'.$training->id.'/question/'.$q->id.'/delete') }}"
                              onsubmit="return confirm('Hapus soal ini?')">@csrf
                            <button class="btn btn-sm btn-link text-danger p-0"><i class="la la-trash"></i></button></form>
                    @endif
                </div>
                @foreach(['a'=>$q->option_a,'b'=>$q->option_b,'c'=>$q->option_c,'d'=>$q->option_d] as $k=>$opt)
                    @if($opt)
                    <div class="small {{ $q->correct_option===$k ? 'text-success fw-semibold' : '' }}">
                        <span class="badge {{ $q->correct_option===$k ? 'bg-success' : 'bg-light text-dark' }}">{{ strtoupper($k) }}</span>
                        {{ $opt }} {{ $q->correct_option===$k ? '✓' : '' }}
                    </div>
                    @endif
                @endforeach
            </div>
        @empty
            <div class="text-muted mb-3">Belum ada soal latihan.</div>
        @endforelse

        @if($canEdit)
        <form method="POST" action="{{ backpack_url('training/'.$training->id.'/question') }}"
              class="rounded p-3 mt-3" style="border:2px dashed #ccc;">
            @csrf
            <div class="fw-semibold mb-2">➕ Tambah Soal Pilihan Ganda</div>
            <div class="mb-2"><label class="form-label small mb-0">Pertanyaan *</label>
                <textarea name="question" class="form-control form-control-sm" rows="2" required></textarea></div>
            <div class="row">
                @foreach(['a','b','c','d'] as $k)
                <div class="col-md-6 mb-2">
                    <label class="form-label small mb-0">Pilihan {{ strtoupper($k) }} {{ in_array($k,['a','b']) ? '*' : '(opsional)' }}</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><input type="radio" name="correct_option" value="{{ $k }}" {{ $k==='a'?'checked':'' }} title="Kunci"></span>
                        <input type="text" name="option_{{ $k }}" class="form-control" {{ in_array($k,['a','b'])?'required':'' }}>
                    </div>
                </div>
                @endforeach
            </div>
            <div class="form-text mb-2">Klik radio di kiri pilihan untuk menandai <b>kunci jawaban</b>.</div>
            <button class="btn btn-sm btn-primary" type="submit"><i class="la la-plus"></i> Tambah Soal</button>
        </form>
        @endif
    </div>

    {{-- ═══ PESERTA ═══ --}}
    <div class="tab-pane fade" id="tab-peserta">
        @if($canEdit)
        <form method="POST" action="{{ backpack_url('training/'.$training->id.'/enroll') }}" class="border rounded p-3 mb-3 bg-light">
            @csrf
            <div class="fw-semibold mb-2">Tugaskan Peserta</div>
            <div class="row g-2 align-items-end">
                <div class="col-md-9">
                    <select name="user_ids[]" class="form-control" multiple size="4" id="enroll-select">
                        @foreach($employees as $emp)
                            <option value="{{ $emp->id }}">{{ $emp->name }}</option>
                        @endforeach
                    </select>
                    <div class="form-text">Ctrl/Cmd+klik untuk pilih banyak, atau <a href="#" id="select-all-emp">Pilih Semua</a>.</div>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-primary w-100" type="submit"><i class="la la-user-plus"></i> Tugaskan</button>
                </div>
            </div>
        </form>
        @endif

        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle">
                <thead class="table-light"><tr>
                    <th>Peserta</th><th style="width:110px;">Status</th><th style="width:80px;">Skor</th>
                    <th style="width:90px;">Percobaan</th><th style="width:130px;">Sertifikat</th><th style="width:120px;"></th>
                </tr></thead>
                <tbody>
                    @forelse($enrollments as $e)
                        <tr>
                            <td>{{ $e->user?->name ?? '—' }}</td>
                            <td>
                                @php $sb = ['enrolled'=>'bg-secondary','passed'=>'bg-success','failed'=>'bg-warning text-dark','locked'=>'bg-danger'][$e->status] ?? 'bg-secondary'; @endphp
                                <span class="badge {{ $sb }}">{{ $e->statusLabel() }}</span>
                            </td>
                            <td>{{ $e->score !== null ? $e->score : '—' }}</td>
                            <td>{{ $e->attempts }} / {{ $training->max_attempts }}</td>
                            <td>@if($e->certificate_no)<span class="small text-success">{{ $e->certificate_no }}</span>@else <span class="text-muted">—</span>@endif</td>
                            <td class="text-end">
                                @if($canEdit)
                                    @if($e->status === TrainingEnrollment::STATUS_LOCKED)
                                    <form method="POST" action="{{ backpack_url('training/'.$training->id.'/enrollment/'.$e->id.'/reset') }}" class="d-inline">@csrf
                                        <button class="btn btn-sm btn-outline-primary py-0" title="Reset percobaan">Reset</button></form>
                                    @endif
                                    <form method="POST" action="{{ backpack_url('training/'.$training->id.'/enrollment/'.$e->id.'/remove') }}" class="d-inline"
                                          onsubmit="return confirm('Keluarkan peserta ini?')">@csrf
                                        <button class="btn btn-sm btn-link text-danger py-0"><i class="la la-times"></i></button></form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-3">Belum ada peserta ditugaskan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ═══ DETAIL ═══ --}}
    <div class="tab-pane fade" id="tab-detail">
        @if($canEdit)
        <form method="POST" action="{{ backpack_url('training/'.$training->id) }}" style="max-width:640px;">
            @csrf
            <div class="mb-3"><label class="form-label fw-semibold">Judul *</label>
                <input type="text" name="title" class="form-control" value="{{ $training->title }}" required></div>
            <div class="mb-3"><label class="form-label fw-semibold">Deskripsi</label>
                <textarea name="description" class="form-control" rows="3">{{ $training->description }}</textarea></div>
            <div class="row">
                <div class="col-md-6 mb-3"><label class="form-label fw-semibold">Pelatih</label>
                    <select name="trainer_id" class="form-control"><option value="">—</option>
                        @foreach($trainers as $tr)<option value="{{ $tr->id }}" @selected($training->trainer_id==$tr->id)>{{ $tr->name }}</option>@endforeach
                    </select></div>
                <div class="col-md-6 mb-3"><label class="form-label fw-semibold">Kategori</label>
                    <input type="text" name="category" class="form-control" value="{{ $training->category }}"></div>
                <div class="col-md-6 mb-3"><label class="form-label fw-semibold">KKM (%)</label>
                    <input type="number" name="passing_score" class="form-control" value="{{ $training->passing_score }}" min="1" max="100" required></div>
                <div class="col-md-6 mb-3"><label class="form-label fw-semibold">Batas Percobaan</label>
                    <input type="number" name="max_attempts" class="form-control" value="{{ $training->max_attempts }}" min="1" max="10" required></div>
            </div>
            <button class="btn btn-primary" type="submit">Simpan Detail</button>
        </form>
        @else
            <div class="text-muted">Anda tidak punya izin mengubah detail.</div>
        @endif
    </div>
</div>

@if($canEdit)
<script>
document.getElementById('select-all-emp')?.addEventListener('click', function (e) {
    e.preventDefault();
    document.querySelectorAll('#enroll-select option').forEach(o => o.selected = true);
});
</script>
@endif
@endsection
