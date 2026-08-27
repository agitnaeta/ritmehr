{{-- WIZ-04 — Layout Setup Wizard: stepper + slot konten + footer.
     Var: $step, $steps, $stepIndex, $slot(via @section), $canSkip(bool) --}}
@extends(backpack_view('blank'))

@php
    $labels = ['company' => 'Perusahaan', 'orgunit' => 'Struktur', 'admin' => 'Admin', 'import' => 'Import'];
@endphp

@section('content')
<style>
  .wz-stepper{display:flex;margin:0 0 26px;padding:0;list-style:none}
  .wz-stepper li{flex:1;text-align:center;position:relative;padding-top:34px;font-size:13px;color:#909aad}
  .wz-stepper li .dot{position:absolute;top:0;left:50%;transform:translateX(-50%);width:28px;height:28px;
      border-radius:50%;background:#e6e9f0;color:#7a8398;display:flex;align-items:center;justify-content:center;font-weight:600;z-index:2}
  .wz-stepper li::before{content:'';position:absolute;top:13px;left:0;width:50%;height:2px;background:#e6e9f0}
  .wz-stepper li::after{content:'';position:absolute;top:13px;right:0;width:50%;height:2px;background:#e6e9f0}
  .wz-stepper li:first-child::before,.wz-stepper li:last-child::after{display:none}
  .wz-stepper li.done .dot{background:#2fb344;color:#fff}
  .wz-stepper li.active .dot{background:#206bc4;color:#fff}
  .wz-stepper li.active{color:#206bc4;font-weight:600}
  .wz-stepper li.done::before,.wz-stepper li.done::after,.wz-stepper li.active::before{background:#206bc4}
</style>

<div class="row justify-content-center">
  <div class="col-lg-8">
    <div class="text-center mb-3">
      <span class="h2 text-primary" style="font-weight:700">RitmeHR</span>
      <div class="text-muted small">Penyiapan awal — 4 langkah cepat agar aplikasi siap dipakai</div>
    </div>

    <div class="card">
      <div class="card-body">
        <ul class="wz-stepper">
          @foreach($steps as $i => $s)
            <li class="{{ $i === $stepIndex ? 'active' : ($i < $stepIndex ? 'done' : '') }}">
              <span class="dot">{{ $i < $stepIndex ? '✓' : $i + 1 }}</span>{{ $labels[$s] }}
            </li>
          @endforeach
        </ul>

        @if($errors->any())
          <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        <form action="{{ route('setup.save', ['step' => $step]) }}" method="POST" enctype="multipart/form-data">
          @csrf
          @yield('wizard')

          <div class="d-flex mt-4">
            @if($stepIndex > 0)
              <a href="{{ route('setup.step', ['step' => $steps[$stepIndex - 1]]) }}" class="btn">← Kembali</a>
            @endif
            <div class="ms-auto d-flex gap-2">
              @if($step === 'import')
                <button formaction="{{ route('setup.finish') }}" class="btn btn-link text-muted">Lewati</button>
                <button class="btn btn-success">Selesai &amp; Masuk Dashboard</button>
              @else
                <button class="btn btn-primary">Lanjut →</button>
              @endif
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection
