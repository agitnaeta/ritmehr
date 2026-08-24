@extends(backpack_view('blank'))

@section('header')
    <section class="container-fluid">
        <h2>Unggah Dokumen
            <small><a href="{{ backpack_url('employee-document') }}" class="font-sm">
                <i class="la la-angle-double-left"></i> Kembali</a></small>
        </h2>
    </section>
@endsection

@section('content')
<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ backpack_url('employee-document') }}" enctype="multipart/form-data">
                    @csrf

                    <div class="mb-3">
                        <label class="form-label">Karyawan <span class="text-danger">*</span></label>
                        <select name="user_id" class="form-control" required>
                            <option value="">— pilih karyawan —</option>
                            @foreach($users as $u)
                                <option value="{{ $u->id }}" @selected(old('user_id') == $u->id)>{{ $u->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Jenis Dokumen <span class="text-danger">*</span></label>
                        <select name="document_type_id" class="form-control" required>
                            <option value="">— pilih jenis —</option>
                            @foreach($types as $t)
                                <option value="{{ $t->id }}" @selected(old('document_type_id') == $t->id)>
                                    {{ $t->name }} ({{ $t->allowed_extensions }}, maks {{ $t->max_file_size_mb }} MB)
                                    @if($t->has_expiry) — perlu tanggal kedaluwarsa @endif
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Berkas <span class="text-danger">*</span></label>
                        <input type="file" name="file" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Nomor Dokumen</label>
                        <input type="text" name="document_number" class="form-control"
                               value="{{ old('document_number') }}">
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tanggal Terbit</label>
                            <input type="date" name="issued_date" class="form-control" value="{{ old('issued_date') }}">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tanggal Kedaluwarsa</label>
                            <input type="date" name="expiry_date" class="form-control" value="{{ old('expiry_date') }}">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Catatan</label>
                        <textarea name="notes" rows="2" class="form-control">{{ old('notes') }}</textarea>
                    </div>

                    <button class="btn btn-primary"><i class="la la-upload"></i> Unggah</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><strong>Catatan Keamanan</strong></div>
            <div class="card-body small text-muted">
                Dokumen disimpan di penyimpanan privat, bukan folder publik. Unduhan
                hanya bisa dilakukan lewat aplikasi setelah pengecekan hak akses —
                URL berkas tidak bisa ditebak.
            </div>
        </div>
    </div>
</div>
@endsection
