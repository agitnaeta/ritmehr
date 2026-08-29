{{-- IMP-05 — Layar import Excel (reusable). Dipakai user.blade & salary.blade.
     Var yang diharapkan:
       $title        judul layar
       $backUrl      link kembali ke list
       $templateUrl  route unduh template
       $previewUrl   route POST pratinjau
       $storeUrl     route POST impor final
       $columns      string daftar kolom (info)
       $preview      null | ['headings'=>[], 'rows'=>[['valid'=>bool,'cells'=>[]]], 'validCount'=>int, 'errorCount'=>int, 'errors'=>[]]
       $result       null | ['imported'=>int, 'skipped'=>int, 'errors'=>[]]
--}}
@extends(backpack_view('blank'))

@section('header')
  <section class="container-fluid d-flex align-items-center">
    <h2 class="mb-0">{{ $title }}</h2>
    <a href="{{ $backUrl }}" class="btn btn-link ms-auto">← Kembali ke daftar</a>
  </section>
@endsection

@section('content')
<div class="row justify-content-center">
  <div class="col-lg-9">

    {{-- FASE 4 — hasil --}}
    @if(!empty($result))
      <div class="card">
        <div class="card-body text-center py-4">
          <div style="font-size:40px">✅</div>
          <h3 class="mt-2">Impor selesai</h3>
          <p class="text-muted">
            {{ $result['imported'] }} baris berhasil masuk &amp; digabung ke database.
            @if($result['skipped']) {{ $result['skipped'] }} baris dilewati karena error. @endif
          </p>
          @if(!empty($result['errors']))
            <div class="text-start mx-auto" style="max-width:520px">
              <div class="text-muted small mb-2">Baris yang dilewati:</div>
              <ul class="list-group mb-3">
                @foreach($result['errors'] as $err)
                  <li class="list-group-item d-flex justify-content-between">
                    <span>{{ $err }}</span><span class="badge bg-red-lt">error</span>
                  </li>
                @endforeach
              </ul>
            </div>
          @endif
          <a href="{{ $backUrl }}" class="btn btn-primary">Lihat daftar</a>
          <a href="{{ url()->current() }}" class="btn">Impor lagi</a>
        </div>
      </div>

    {{-- FASE 3 — pratinjau & validasi --}}
    @elseif(!empty($preview))
      <div class="card">
        <div class="card-header"><h3 class="card-title">Pratinjau &amp; validasi</h3></div>
        <div class="card-body">
          <div class="alert {{ $preview['errorCount'] ? 'alert-warning' : 'alert-success' }}">
            <strong>{{ $preview['totalCount'] ?? ($preview['validCount'] + $preview['errorCount']) }} baris terbaca.</strong>
            {{ $preview['validCount'] }} valid
            @if($preview['errorCount'])· <span class="text-danger">{{ $preview['errorCount'] }} bermasalah</span>@endif
          </div>
          @if(($preview['shownCount'] ?? count($preview['rows'])) < ($preview['totalCount'] ?? 0))
            <div class="text-muted small mb-2">
              Menampilkan {{ $preview['shownCount'] }} baris pertama dari {{ $preview['totalCount'] }} (pratinjau dibatasi demi performa; seluruh baris tetap akan diimpor).
            </div>
          @endif
          <div class="table-responsive">
            <table class="table table-vcenter card-table">
              <thead>
                <tr>
                  <th>Status</th>
                  @foreach($preview['headings'] as $h)<th>{{ $h }}</th>@endforeach
                </tr>
              </thead>
              <tbody>
                @foreach($preview['rows'] as $row)
                  <tr class="{{ $row['valid'] ? '' : 'table-danger' }}">
                    <td>
                      @if($row['valid'])<span class="badge bg-green-lt">ok</span>
                      @else<span class="badge bg-red-lt">error</span>@endif
                    </td>
                    @foreach($row['cells'] as $c)<td>{{ $c === '' || $c === null ? '—' : $c }}</td>@endforeach
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
          <div class="d-flex mt-2">
            <a href="{{ url()->current() }}" class="btn">← Ganti file</a>
            <form action="{{ $storeUrl }}" method="POST" enctype="multipart/form-data" class="ms-auto">
              @csrf
              <input type="hidden" name="token" value="{{ $preview['token'] }}">
              <button class="btn btn-success" {{ $preview['validCount'] ? '' : 'disabled' }}>
                Impor {{ $preview['validCount'] }} baris valid
              </button>
            </form>
          </div>
        </div>
      </div>

    {{-- FASE 1+2 — unduh template + unggah --}}
    @else
      <div class="card">
        <div class="card-header"><h3 class="card-title">{{ $title }}</h3></div>
        <div class="card-body">
          @if($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
          @endif

          <div class="card card-sm bg-azure-lt mb-3">
            <div class="card-body d-flex align-items-center justify-content-between">
              <div>
                <strong>Langkah 1 — Unduh template</strong>
                <div class="text-muted small">Header: {{ $columns }}</div>
              </div>
              <a class="btn btn-outline-primary" href="{{ $templateUrl }}">
                <i class="la la-download"></i> Unduh Template
              </a>
            </div>
          </div>

          <form action="{{ $previewUrl }}" method="POST" enctype="multipart/form-data">
            @csrf
            <label class="form-label"><strong>Langkah 2 — Unggah file terisi</strong></label>
            <input type="file" name="file" class="form-control" accept=".xlsx,.xls,.csv" required>
            <div class="text-muted small mt-2">Format .xlsx / .xls / .csv — maks 2MB</div>
            <div class="text-end mt-3">
              <button class="btn btn-primary">Pratinjau →</button>
            </div>
          </form>
        </div>
      </div>
    @endif

  </div>
</div>
@endsection
