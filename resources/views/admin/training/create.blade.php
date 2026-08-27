@extends(backpack_view('blank'))

@section('header')
    <section class="container-fluid">
        <h2><i class="la la-plus"></i> Buat Pelatihan Baru</h2>
    </section>
@endsection

@section('content')
<div class="row">
  <div class="col-md-8">
    <div class="card"><div class="card-body">
        <form method="POST" action="{{ backpack_url('training') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label fw-semibold">Judul Pelatihan <span class="text-danger">*</span></label>
                <input type="text" name="title" class="form-control" value="{{ old('title') }}" required
                       placeholder="mis. Pelatihan Keselamatan Kerja (K3)">
                @error('title')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Deskripsi</label>
                <textarea name="description" class="form-control" rows="3">{{ old('description') }}</textarea>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-semibold">Pelatih</label>
                    <select name="trainer_id" class="form-control">
                        <option value="">— pilih pelatih —</option>
                        @foreach($trainers as $tr)
                            <option value="{{ $tr->id }}" @selected(old('trainer_id')==$tr->id)>{{ $tr->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-semibold">Kategori</label>
                    <input type="text" name="category" class="form-control" value="{{ old('category') }}"
                           placeholder="mis. Wajib / Teknis / Soft Skill">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-semibold">KKM / Nilai Lulus (%) <span class="text-danger">*</span></label>
                    <input type="number" name="passing_score" class="form-control" value="{{ old('passing_score', 70) }}"
                           min="1" max="100" required>
                    <div class="form-text">Skor peserta ≥ nilai ini → LULUS.</div>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-semibold">Batas Percobaan <span class="text-danger">*</span></label>
                    <input type="number" name="max_attempts" class="form-control" value="{{ old('max_attempts', 3) }}"
                           min="1" max="10" required>
                    <div class="form-text">Gagal sebanyak ini → terkunci (perlu reset HR).</div>
                </div>
            </div>
            <div class="d-flex gap-2 justify-content-end border-top pt-3">
                <a href="{{ backpack_url('training') }}" class="btn btn-outline-secondary">Batal</a>
                <button type="submit" class="btn btn-primary">Simpan & Lanjut Isi Materi</button>
            </div>
        </form>
    </div></div>
  </div>
</div>
@endsection
