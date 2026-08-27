@extends('admin.setup._layout')

@section('wizard')
  <h3 class="mb-1">Import Karyawan</h3>
  <p class="text-muted small mb-3">Bawa data karyawan dari Excel, atau lewati dan tambah manual nanti.</p>

  <div class="card card-sm bg-azure-lt mb-3">
    <div class="card-body d-flex align-items-center justify-content-between">
      <div>
        <strong>Belum punya file?</strong>
        <div class="text-muted small">Unduh template, isi, lalu unggah di sini.</div>
      </div>
      <a class="btn btn-outline-primary" href="{{ route('user.import.template') }}">
        <i class="la la-download"></i> Unduh Template Karyawan
      </a>
    </div>
  </div>

  <div class="mb-2">
    <label class="form-label">File Excel Karyawan</label>
    <input type="file" name="file" class="form-control" accept=".xlsx,.xls,.csv">
  </div>
  <div class="text-muted small">Kolom: nama, email, tgl_bergabung, departemen, cabang, jabatan, password</div>
@endsection
