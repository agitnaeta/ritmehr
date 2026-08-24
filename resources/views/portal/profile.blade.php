@extends('portal.layout')
@section('title', 'Profil Saya')
@section('heading', 'Profil Saya')

@section('content')
<div class="row g-3">
    <div class="col-md-7">
        <div class="card">
            <div class="card-header"><strong>Data Kontak</strong></div>
            <div class="card-body">
                <form method="POST" action="{{ route('portal.profile.update') }}" enctype="multipart/form-data">
                    @csrf

                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control"
                               value="{{ old('email', $user->email) }}" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">No. Telepon</label>
                        <input type="text" name="phone" class="form-control"
                               value="{{ old('phone', $user->phone) }}" placeholder="08xxxxxxxxxx">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Alamat</label>
                        <textarea name="address" rows="3" class="form-control">{{ old('address', $user->address) }}</textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Foto</label>
                        <input type="file" name="image" class="form-control" accept=".jpg,.jpeg,.png">
                        <div class="form-text">JPG/PNG maks 2 MB.</div>
                    </div>

                    <button class="btn btn-primary">Simpan Perubahan</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><strong>Ganti Password</strong></div>
            <div class="card-body">
                <form method="POST" action="{{ route('portal.password.change') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Password Saat Ini</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password Baru</label>
                        <input type="password" name="password" class="form-control" required minlength="8">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Konfirmasi Password Baru</label>
                        <input type="password" name="password_confirmation" class="form-control" required>
                    </div>
                    <button class="btn btn-outline-primary">Ganti Password</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-5">
        <div class="card">
            <div class="card-header"><strong>Data Kepegawaian</strong></div>
            <div class="card-body">
                @if($user->image)
                    <img src="{{ \Illuminate\Support\Facades\Storage::url($user->image) }}"
                         class="rounded mb-3" width="120" height="120" style="object-fit: cover;"
                         alt="Foto {{ $user->name }}">
                @endif
                <table class="table table-sm mb-0">
                    <tr><th>Nama</th><td>{{ $user->name }}</td></tr>
                    <tr><th>NIK/NIP</th><td>{{ $user->employee_id ?? '—' }}</td></tr>
                    <tr><th>Departemen</th><td>{{ $user->department?->name ?? '—' }}</td></tr>
                    <tr><th>Jabatan</th><td>{{ $user->position?->name ?? '—' }}</td></tr>
                    <tr><th>Atasan</th><td>{{ $user->manager?->name ?? '—' }}</td></tr>
                    <tr><th>Bergabung</th><td>{{ $user->join_date?->format('d/m/Y') ?? '—' }}</td></tr>
                    <tr><th>Status</th><td>{{ $user->employmentStatusLabel() }}</td></tr>
                </table>
                <div class="form-text mt-2">
                    Data kepegawaian hanya dapat diubah oleh HR.
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
