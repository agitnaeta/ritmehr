{{-- UM-10 — Halaman status export karyawan (background, polling). --}}
@extends(backpack_view('blank'))

@section('header')
  <section class="container-fluid d-flex align-items-center">
    <h2 class="mb-0">Export Karyawan</h2>
    <a href="{{ url('admin/user') }}" class="btn btn-link ms-auto">← Kembali ke daftar</a>
  </section>
@endsection

@section('content')
<div class="row">
  <div class="col-lg-8">
    <div class="card"
         id="exportStatusRoot"
         data-status-url="{{ route('user.export.status.json', $job->id) }}"
         data-download-url="{{ route('user.export.download', $job->id) }}"
         data-users-url="{{ url('admin/user') }}"
         data-finished="{{ $job->isFinished() ? '1' : '0' }}">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between">
          <h3 class="mb-0" id="exTitle">Menyiapkan…</h3>
          <span id="exBadge" class="badge bg-blue-lt">Menunggu</span>
        </div>

        <div class="progress mt-3" style="height:14px">
          <div id="exBar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width:100%"></div>
        </div>
        <p class="text-muted small mt-2" id="exMeta">{{ $job->total }} karyawan akan diekspor…</p>

        <p class="text-muted small" id="exHint">
          Anda boleh menutup halaman ini — file dibuat di latar. Kembali kapan saja untuk mengunduh.
        </p>

        <div id="exActions" class="d-flex gap-2 mt-3" style="display:none!important">
          <a id="exDownload" href="#" class="btn btn-primary" style="display:none"><i class="la la-download"></i> Unduh Excel</a>
          <a id="exUsers" href="#" class="btn btn-outline-secondary">Kembali ke daftar</a>
        </div>

        <div id="exFail" class="alert alert-danger mt-3" style="display:none"></div>
      </div>
    </div>
  </div>
</div>
@endsection

@section('after_scripts')
<script>
(function(){
  const root=document.getElementById('exportStatusRoot');
  const bar=document.getElementById('exBar'), title=document.getElementById('exTitle'), badge=document.getElementById('exBadge');
  const actions=document.getElementById('exActions'), hint=document.getElementById('exHint');
  const dl=document.getElementById('exDownload'), fail=document.getElementById('exFail'), meta=document.getElementById('exMeta');
  document.getElementById('exUsers').href=root.dataset.usersUrl;
  dl.href=root.dataset.downloadUrl;
  let timer=null;

  function render(d){
    if(d.status==='queued'){title.textContent='Menunggu antrian…';badge.className='badge bg-blue-lt';badge.textContent='Menunggu';}
    else if(d.status==='processing'){title.textContent='Membuat file Excel…';badge.className='badge bg-blue-lt';badge.textContent='Sedang berjalan';}
    else if(d.status==='done'){title.textContent='File Excel siap';badge.className='badge bg-green-lt';badge.textContent='✓ Selesai';}
    else if(d.status==='failed'){title.textContent='Export gagal';badge.className='badge bg-red-lt';badge.textContent='✕ Gagal';}

    if(d.finished){
      bar.classList.remove('progress-bar-animated','progress-bar-striped');
      bar.classList.add(d.status==='done'?'bg-green':'bg-red');
      actions.style.setProperty('display','flex','important');
      hint.style.display='none';
      if(d.ready){ dl.style.display='inline-flex'; meta.textContent='File siap diunduh (berlaku 24 jam).'; }
      if(d.expired){ meta.textContent='Tautan sudah kadaluarsa — silakan export ulang.'; }
      if(d.status==='failed'){ fail.style.display='block'; fail.textContent=d.message||'Export gagal.'; }
      if(timer){clearInterval(timer);timer=null;}
    }
  }
  function poll(){ fetch(root.dataset.statusUrl,{headers:{'X-Requested-With':'XMLHttpRequest'}}).then(r=>r.json()).then(render).catch(()=>{}); }
  poll();
  if(root.dataset.finished!=='1'){ timer=setInterval(poll,3000); }
})();
</script>
@endsection
