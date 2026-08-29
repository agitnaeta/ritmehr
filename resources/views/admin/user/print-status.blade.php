{{-- UM-11 — Halaman status generate PDF kartu ID (background, polling). --}}
@extends(backpack_view('blank'))

@section('header')
  <section class="container-fluid d-flex align-items-center">
    <h2 class="mb-0">Cetak Kartu ID</h2>
    <a href="{{ url('admin/user') }}" class="btn btn-link ms-auto">← Kembali ke daftar</a>
  </section>
@endsection

@section('content')
<div class="row">
  <div class="col-lg-8">
    <div class="card"
         id="printStatusRoot"
         data-status-url="{{ route('user.print.status.json', $job->id) }}"
         data-download-url="{{ route('user.print.download', $job->id) }}"
         data-users-url="{{ url('admin/user') }}"
         data-finished="{{ $job->isFinished() ? '1' : '0' }}">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between">
          <h3 class="mb-0" id="psTitle">Menyiapkan…</h3>
          <span id="psBadge" class="badge bg-blue-lt">Menunggu</span>
        </div>

        <div class="progress mt-3" style="height:14px">
          <div id="psBar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width:0%"></div>
        </div>
        <div class="d-flex justify-content-between small text-muted mt-1">
          <span id="psMeta">0 dari {{ $job->total }} kartu</span>
          <span id="psPct">0%</span>
        </div>

        <p class="text-muted small mt-3" id="psHint">
          Anda boleh menutup halaman ini — proses berjalan di latar. Kembali kapan saja untuk melihat progres.
        </p>

        <div id="psActions" class="d-flex gap-2 mt-3" style="display:none!important">
          <a id="psDownload" href="#" class="btn btn-primary" style="display:none"><i class="la la-download"></i> Unduh PDF</a>
          <a id="psUsers" href="#" class="btn btn-outline-secondary">Kembali ke daftar</a>
        </div>

        <div id="psFail" class="alert alert-danger mt-3" style="display:none"></div>
      </div>
    </div>
  </div>
</div>
@endsection

@section('after_scripts')
<script>
(function(){
  const root=document.getElementById('printStatusRoot');
  const bar=document.getElementById('psBar'), pct=document.getElementById('psPct'), meta=document.getElementById('psMeta');
  const title=document.getElementById('psTitle'), badge=document.getElementById('psBadge');
  const actions=document.getElementById('psActions'), hint=document.getElementById('psHint');
  const dl=document.getElementById('psDownload'), fail=document.getElementById('psFail');
  document.getElementById('psUsers').href=root.dataset.usersUrl;
  dl.href=root.dataset.downloadUrl;
  let timer=null;

  function render(d){
    const p=d.progress||0;
    bar.style.width=p+'%'; pct.textContent=p+'%';
    meta.textContent=(d.processed||0)+' dari '+(d.total||0)+' kartu';
    if(d.status==='queued'){title.textContent='Menunggu antrian…';badge.className='badge bg-blue-lt';badge.textContent='Menunggu';}
    else if(d.status==='processing'){title.textContent='Membuat PDF kartu ID…';badge.className='badge bg-blue-lt';badge.textContent='Sedang berjalan';}
    else if(d.status==='done'){title.textContent='PDF siap';badge.className='badge bg-green-lt';badge.textContent='✓ Selesai';}
    else if(d.status==='failed'){title.textContent='Gagal membuat PDF';badge.className='badge bg-red-lt';badge.textContent='✕ Gagal';}

    if(d.finished){
      bar.classList.remove('progress-bar-animated','progress-bar-striped');
      bar.classList.add(d.status==='done'?'bg-green':'bg-red');
      actions.style.setProperty('display','flex','important');
      hint.style.display='none';
      if(d.ready){ dl.style.display='inline-flex'; }
      if(d.status==='failed'){ fail.style.display='block'; fail.textContent=d.message||'Gagal membuat PDF.'; }
      if(timer){clearInterval(timer);timer=null;}
    }
  }
  function poll(){ fetch(root.dataset.statusUrl,{headers:{'X-Requested-With':'XMLHttpRequest'}}).then(r=>r.json()).then(render).catch(()=>{}); }
  poll();
  if(root.dataset.finished!=='1'){ timer=setInterval(poll,3000); }
})();
</script>
@endsection
