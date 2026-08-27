@extends('admin.setup._layout')

@section('wizard')
  <h3 class="mb-1">Akun Admin &amp; HR</h3>
  <p class="text-muted small mb-3">Lengkapi profil Anda, dan (opsional) buat satu akun HR.</p>

  <div class="mb-3">
    <label class="form-label required">Nama Anda</label>
    <input name="name" class="form-control" value="{{ old('name', $me->name ?? '') }}" required>
  </div>
  <div class="row">
    <div class="col-md-6 mb-3">
      <label class="form-label required">Email</label>
      <input name="email" type="email" class="form-control" value="{{ old('email', $me->email ?? '') }}" required>
    </div>
    <div class="col-md-6 mb-3">
      <label class="form-label">Departemen Anda</label>
      <select name="department_id" class="form-select">
        <option value="">—</option>
        @foreach(($departments ?? collect()) as $d)
          <option value="{{ $d->id }}" @selected(old('department_id', $me->department_id ?? null) == $d->id)>{{ $d->name }}</option>
        @endforeach
      </select>
    </div>
  </div>

  <label class="form-check">
    <input class="form-check-input" type="checkbox" name="create_hr" value="1" @checked(old('create_hr'))>
    <span class="form-check-label">Buat juga akun HR terpisah</span>
  </label>
  <div class="row mt-2">
    <div class="col-md-4"><input name="hr_name" class="form-control" placeholder="Nama HR"></div>
    <div class="col-md-4"><input name="hr_email" type="email" class="form-control" placeholder="Email HR"></div>
    <div class="col-md-4"><input name="hr_password" class="form-control" placeholder="Sandi HR (default: password)"></div>
  </div>
@endsection
