{{-- UM-06 — Halaman Show karyawan bertab: Profil + Foto/QR + Riwayat Pelatihan. --}}
@extends(backpack_view('blank'))

@php
    use App\Models\TrainingEnrollment;

    $statusBadge = match ($user->employment_status) {
        \App\Models\User::STATUS_ACTIVE    => 'bg-green-lt',
        \App\Models\User::STATUS_PROBATION => 'bg-yellow-lt',
        \App\Models\User::STATUS_RESIGNED  => 'bg-secondary',
        \App\Models\User::STATUS_TERMINATED=> 'bg-red-lt',
        default => 'bg-secondary',
    };

    $trainingBadge = fn ($status) => match ($status) {
        TrainingEnrollment::STATUS_PASSED   => 'bg-green-lt',
        TrainingEnrollment::STATUS_ENROLLED => 'bg-yellow-lt',
        TrainingEnrollment::STATUS_FAILED   => 'bg-red-lt',
        TrainingEnrollment::STATUS_LOCKED   => 'bg-secondary',
        default => 'bg-secondary',
    };

    $photoUrl = $user->image ? \Illuminate\Support\Facades\Storage::url('public/' . $user->image) : null;
    $initial  = mb_strtoupper(mb_substr($user->name ?? '?', 0, 1));
    $qrBase64 = $user->qr
        ? base64_encode(\SimpleSoftwareIO\QrCode\Facades\QrCode::size(180)->generate($user->qr))
        : null;
@endphp

@section('header')
  <section class="container-fluid d-flex align-items-center flex-wrap gap-2">
    <div>
      <div class="text-muted small">
        <a href="{{ url('admin/user') }}">Users</a> / {{ $user->name }}
      </div>
      <h2 class="mb-0">{{ $user->name }}</h2>
    </div>
    <div class="ms-auto d-flex gap-2">
      <a href="{{ url('admin/user') }}" class="btn btn-outline-secondary btn-sm"><i class="la la-arrow-left"></i> Kembali</a>
      @if($canEdit)
        <a href="{{ url('admin/user/'.$user->id.'/edit') }}" class="btn btn-primary btn-sm"><i class="la la-edit"></i> Ubah</a>
      @endif
      @if($canDelete)
        <a href="{{ url('admin/user/'.$user->id.'/delete') }}"
           class="btn btn-outline-danger btn-sm"
           data-button-type="delete"
           onclick="event.preventDefault(); if(confirm('Hapus karyawan ini?')){ deleteUser(this); }">
          <i class="la la-trash"></i> Hapus
        </a>
      @endif
    </div>
  </section>
@endsection

@section('content')
<div class="row">
  <div class="col-12">

    <ul class="nav nav-tabs" role="tablist">
      <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-profil" type="button">Profil</button></li>
      <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-foto" type="button">Foto &amp; QR</button></li>
      <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-pelatihan" type="button">Riwayat Pelatihan</button></li>
    </ul>

    <div class="tab-content border border-top-0 rounded-bottom bg-white p-4">

      {{-- ===== TAB PROFIL ===== --}}
      <div class="tab-pane fade show active" id="tab-profil" role="tabpanel">
        <div class="row g-4">
          <div class="col-md-3 text-center">
            @if($photoUrl)
              <img src="{{ $photoUrl }}" alt="Foto {{ $user->name }}" class="rounded" style="width:160px;height:160px;object-fit:cover">
            @else
              <div class="rounded d-inline-flex align-items-center justify-content-center text-white"
                   style="width:160px;height:160px;font-size:3.5rem;font-weight:800;background:linear-gradient(135deg,#2563eb,#1e3a8a)">{{ $initial }}</div>
            @endif
            <div class="mt-2"><span class="badge {{ $statusBadge }}">{{ $user->employmentStatusLabel() }}</span></div>
          </div>
          <div class="col-md-9">
            <table class="table table-sm mb-0">
              <tbody>
                <tr><td class="text-muted" style="width:200px">Nama</td><td class="fw-semibold">{{ $user->name }}</td></tr>
                <tr><td class="text-muted">Email</td><td class="fw-semibold">{{ $user->email }}</td></tr>
                <tr><td class="text-muted">NIK / NIP</td><td class="fw-semibold">{{ $user->employee_id ?? '—' }}</td></tr>
                <tr><td class="text-muted">Departemen</td><td class="fw-semibold">{{ $user->department->name ?? '—' }}</td></tr>
                <tr><td class="text-muted">Jabatan</td><td class="fw-semibold">{{ $user->position->name ?? '—' }}</td></tr>
                <tr><td class="text-muted">Cabang</td><td class="fw-semibold">{{ $user->branch->name ?? '—' }}</td></tr>
                <tr><td class="text-muted">Atasan Langsung</td><td class="fw-semibold">{{ $user->manager->name ?? '—' }}</td></tr>
                <tr><td class="text-muted">Jadwal</td><td class="fw-semibold">{{ $user->schedule->name ?? '—' }}</td></tr>
                <tr><td class="text-muted">Bahasa</td><td class="fw-semibold">{{ $user->locale === 'en' ? 'English' : 'Indonesia' }}</td></tr>
                <tr><td class="text-muted">Tanggal Bergabung</td><td class="fw-semibold">{{ optional($user->join_date)->format('d M Y') ?? '—' }}</td></tr>
                <tr><td class="text-muted">No. Telepon</td><td class="fw-semibold">{{ $user->phone ?? '—' }}</td></tr>
                <tr><td class="text-muted">Alamat</td><td class="fw-semibold">{{ $user->address ?? '—' }}</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      {{-- ===== TAB FOTO & QR ===== --}}
      <div class="tab-pane fade" id="tab-foto" role="tabpanel">
        <div class="d-flex flex-wrap gap-4">
          <div>
            <div class="text-muted small text-uppercase mb-2">Foto Karyawan</div>
            @if($photoUrl)
              <img src="{{ $photoUrl }}" alt="Foto {{ $user->name }}" class="rounded" style="width:240px;height:240px;object-fit:cover">
            @else
              <div class="rounded d-inline-flex align-items-center justify-content-center text-white"
                   style="width:240px;height:240px;font-size:5rem;font-weight:800;background:linear-gradient(135deg,#2563eb,#1e3a8a)">{{ $initial }}</div>
            @endif
          </div>
          <div>
            <div class="text-muted small text-uppercase mb-2">QR Code Absensi</div>
            @if($qrBase64)
              <img src="data:image/svg+xml;base64,{{ $qrBase64 }}" alt="QR {{ $user->name }}" class="border rounded p-2 bg-white" style="width:200px;height:200px">
            @else
              <div class="border rounded d-flex align-items-center justify-content-center text-muted" style="width:200px;height:200px">Tidak ada QR</div>
            @endif
          </div>
        </div>
      </div>

      {{-- ===== TAB RIWAYAT PELATIHAN ===== --}}
      <div class="tab-pane fade" id="tab-pelatihan" role="tabpanel">
        @if($user->trainingEnrollments->isEmpty())
          <div class="text-center text-muted py-5">
            <div style="font-size:2.5rem;opacity:.4">🎓</div>
            <p class="mb-0">Karyawan ini belum mengikuti pelatihan apa pun.</p>
          </div>
        @else
          <div class="table-responsive">
            <table class="table table-vcenter">
              <thead>
                <tr><th>Pelatihan</th><th>Status</th><th>Skor</th><th>No. Sertifikat</th><th>Tgl Lulus</th></tr>
              </thead>
              <tbody>
                @foreach($user->trainingEnrollments as $en)
                  <tr>
                    <td>{{ $en->training->title ?? '—' }}</td>
                    <td><span class="badge {{ $trainingBadge($en->status) }}">{{ $en->statusLabel() }}</span></td>
                    <td>{{ $en->score ?? '—' }}</td>
                    <td>{{ $en->certificate_no ?? '—' }}</td>
                    <td>{{ optional($en->passed_at)->format('d M Y') ?? '—' }}</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @endif
      </div>

    </div>
  </div>
</div>
@endsection

@section('after_scripts')
<script>
  function deleteUser(el){
    // Reuse Backpack delete via simple redirect fallback.
    window.location.href = el.getAttribute('href');
  }
</script>
@endsection
