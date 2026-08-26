@extends('career.layout')
@section('title', 'Daftar Akun')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-5">
        <div class="card">
            <div class="card-header"><strong>Daftar Akun Kandidat</strong></div>
            <div class="card-body">
                <form method="POST" action="{{ route('career.register.submit') }}" id="register-form">
                    @csrf
                    <div class="mb-2">
                        <label class="form-label small">Nama Lengkap</label>
                        <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Email</label>
                        <input type="email" name="email" class="form-control" value="{{ old('email') }}" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">No. HP (opsional)</label>
                        <input type="text" name="phone" class="form-control" value="{{ old('phone') }}">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Kata Sandi (min 8 karakter)</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small">Konfirmasi Kata Sandi</label>
                        <input type="password" name="password_confirmation" class="form-control" required>
                    </div>
                    <button class="btn btn-primary w-100" id="btn-register">Daftar</button>
                </form>
                <p class="small text-center mt-3 mb-0">
                    Sudah punya akun? <a href="{{ route('career.login') }}">Masuk</a>
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
