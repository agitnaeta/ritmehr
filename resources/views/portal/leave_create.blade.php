@extends('portal.layout')
@section('title', 'Ajukan Cuti')
@section('heading', 'Ajukan Cuti')

@section('content')
<div class="row">
    <div class="col-md-7">
        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ route('portal.leave.store') }}" enctype="multipart/form-data">
                    @csrf

                    <div class="mb-3">
                        <label class="form-label">Jenis Cuti <span class="text-danger">*</span></label>
                        <select name="leave_type_id" class="form-select" required>
                            <option value="">— pilih jenis —</option>
                            @foreach($leaveTypes as $type)
                                <option value="{{ $type->id }}" @selected(old('leave_type_id') == $type->id)>
                                    {{ $type->name }}@unless($type->is_paid) (tidak dibayar)@endunless
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Mulai <span class="text-danger">*</span></label>
                            <input type="date" name="start_date" class="form-control"
                                   value="{{ old('start_date') }}" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Selesai <span class="text-danger">*</span></label>
                            <input type="date" name="end_date" class="form-control"
                                   value="{{ old('end_date') }}" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Alasan</label>
                        <textarea name="reason" rows="3" class="form-control">{{ old('reason') }}</textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Lampiran</label>
                        <input type="file" name="attachment" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                        <div class="form-text">PDF/JPG/PNG maks 5 MB. Wajib untuk cuti sakit.</div>
                    </div>

                    <button class="btn btn-primary"><i class="la la-paper-plane"></i> Kirim Pengajuan</button>
                    <a href="{{ route('portal.leave.index') }}" class="btn btn-link">Batal</a>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-5">
        <div class="card">
            <div class="card-header"><strong>Saldo Anda ({{ now()->year }})</strong></div>
            <div class="card-body">
                @forelse($balances as $balance)
                    <div class="d-flex justify-content-between border-bottom py-2">
                        <span>{{ $balance->leaveType->name }}</span>
                        <strong>{{ $balance->remainingDays() }} hari</strong>
                    </div>
                @empty
                    <p class="text-muted mb-0">Belum ada saldo cuti.</p>
                @endforelse
                <div class="form-text mt-3">
                    Akhir pekan dan hari libur nasional tidak dihitung sebagai hari cuti.
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
