@extends('portal.layout')
@section('title', 'Ajukan Kasbon')
@section('heading', 'Ajukan Kasbon')

@section('content')
<div class="row">
    <div class="col-md-7">
        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ route('portal.loan.store') }}">
                    @csrf

                    <div class="mb-3">
                        <label class="form-label">Jumlah <span class="text-danger">*</span></label>
                        <input type="number" name="amount" class="form-control" min="1" step="1"
                               value="{{ old('amount') }}" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Tanggal <span class="text-danger">*</span></label>
                        <input type="date" name="date" class="form-control"
                               value="{{ old('date', now()->toDateString()) }}" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Keperluan</label>
                        <textarea name="reason" rows="3" class="form-control">{{ old('reason') }}</textarea>
                    </div>

                    <button class="btn btn-primary"><i class="la la-paper-plane"></i> Kirim Pengajuan</button>
                    <a href="{{ route('portal.loan.index') }}" class="btn btn-link">Batal</a>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-5">
        <div class="card">
            <div class="card-body">
                <div class="text-muted small">Sisa kasbon Anda saat ini</div>
                <div class="value fs-4 text-danger">@rupiah($outstanding)</div>
                <div class="form-text mt-2">
                    Pengajuan akan diproses lewat alur persetujuan. Kasbon baru tercatat
                    setelah disetujui. Hanya satu pengajuan yang boleh menunggu sekaligus.
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
