@extends('career.layout')
@section('title', 'Masuk')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-5">
        <div class="card">
            <div class="card-header"><strong>Masuk Akun Kandidat</strong></div>
            <div class="card-body">
                <form method="POST" action="{{ route('career.login.submit') }}" id="login-form">
                    @csrf
                    <div class="mb-2">
                        <label class="form-label small">Email</label>
                        <input type="email" name="email" class="form-control" value="{{ old('email') }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small">Kata Sandi</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="form-check mb-3">
                        <input type="checkbox" name="remember" class="form-check-input" id="remember">
                        <label class="form-check-label small" for="remember">Ingat saya</label>
                    </div>
                    <button class="btn btn-primary w-100" id="btn-login">Masuk</button>
                </form>
                <p class="small text-center mt-3 mb-0">
                    Belum punya akun? <a href="{{ route('career.register') }}">Daftar</a>
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
