@extends('admin.setup._layout')

@section('wizard')
  <h3 class="mb-1">Departemen &amp; Cabang</h3>
  <p class="text-muted small mb-3">Minimal satu departemen dan satu cabang agar karyawan bisa ditempatkan.</p>

  <div class="row">
    <div class="col-md-6">
      <strong>Departemen</strong>
      @php $deps = old('departments', ($departments ?? collect())->all() ?: ['']); @endphp
      @foreach(array_pad($deps, max(count($deps), 2), '') as $d)
        <input name="departments[]" class="form-control mb-2" value="{{ $d }}" placeholder="mis. Operasional">
      @endforeach
      <input name="departments[]" class="form-control mb-2" placeholder="Tambah departemen...">
    </div>
    <div class="col-md-6">
      <strong>Cabang</strong>
      @php $brs = old('branches', ($branches ?? collect())->all() ?: ['']); @endphp
      @foreach(array_pad($brs, max(count($brs), 1), '') as $b)
        <input name="branches[]" class="form-control mb-2" value="{{ $b }}" placeholder="mis. Kantor Pusat">
      @endforeach
      <input name="branches[]" class="form-control mb-2" placeholder="Tambah cabang...">
    </div>
  </div>
@endsection
