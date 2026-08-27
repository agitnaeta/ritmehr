@extends('admin.setup._layout')

@section('wizard')
  <h3 class="mb-1">Profil Perusahaan</h3>
  <p class="text-muted small mb-3">Identitas ini muncul di slip gaji, laporan, dan portal karyawan.</p>

  <div class="mb-3">
    <label class="form-label required">Nama Perusahaan</label>
    <input name="name" class="form-control" value="{{ old('name', $company->name ?? '') }}" placeholder="PT Contoh Nusantara" required>
  </div>
  <div class="row">
    <div class="col-md-8 mb-3">
      <label class="form-label">Alamat</label>
      <input name="address" class="form-control" value="{{ old('address', $company->address ?? '') }}" placeholder="Jl. ...">
    </div>
    <div class="col-md-4 mb-3">
      <label class="form-label">Telepon</label>
      <input name="phone" class="form-control" value="{{ old('phone', $company->phone ?? '') }}" placeholder="021-...">
    </div>
  </div>
@endsection
