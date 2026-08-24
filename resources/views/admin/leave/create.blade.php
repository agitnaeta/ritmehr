@extends(backpack_view('blank'))

@section('header')
    <section class="container-fluid">
        <h2>
            Ajukan Cuti
            <small><a href="{{ backpack_url('leave-request') }}" class="font-sm">
                <i class="la la-angle-double-left"></i> Kembali</a></small>
        </h2>
    </section>
@endsection

@section('content')
<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ backpack_url('leave-request/store-form') }}"
                      enctype="multipart/form-data">
                    @csrf

                    <div class="mb-3">
                        <label class="form-label">Karyawan <span class="text-danger">*</span></label>
                        <select name="user_id" class="form-control" required>
                            <option value="">— pilih karyawan —</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}" @selected(old('user_id') == $user->id)>
                                    {{ $user->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('user_id')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Jenis Cuti <span class="text-danger">*</span></label>
                        <select name="leave_type_id" class="form-control" required>
                            <option value="">— pilih jenis —</option>
                            @foreach($leaveTypes as $type)
                                <option value="{{ $type->id }}" @selected(old('leave_type_id') == $type->id)>
                                    {{ $type->name }}
                                    @if(!$type->is_paid) (tidak dibayar) @endif
                                    @if($type->requires_attachment) — wajib lampiran @endif
                                </option>
                            @endforeach
                        </select>
                        @error('leave_type_id')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tanggal Mulai <span class="text-danger">*</span></label>
                            <input type="date" name="start_date" class="form-control"
                                   value="{{ old('start_date') }}" required>
                            @error('start_date')<div class="text-danger small">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tanggal Selesai <span class="text-danger">*</span></label>
                            <input type="date" name="end_date" class="form-control"
                                   value="{{ old('end_date') }}" required>
                            @error('end_date')<div class="text-danger small">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Alasan</label>
                        <textarea name="reason" rows="3" class="form-control">{{ old('reason') }}</textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Lampiran</label>
                        <input type="file" name="attachment" class="form-control"
                               accept=".pdf,.jpg,.jpeg,.png">
                        <small class="text-muted">PDF/JPG/PNG, maks 5 MB. Wajib untuk jenis tertentu.</small>
                        @error('attachment')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="la la-paper-plane"></i> Ajukan
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><strong>Catatan</strong></div>
            <div class="card-body small text-muted">
                <ul class="ps-3 mb-0">
                    <li>Hari libur mingguan dan hari libur nasional otomatis tidak dihitung.</li>
                    <li>Pengajuan langsung masuk ke alur persetujuan.</li>
                    <li>Saldo baru berkurang setelah pengajuan disetujui penuh.</li>
                    <li>Cuti berbayar tidak memotong gaji; cuti tanpa gaji memotong.</li>
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
