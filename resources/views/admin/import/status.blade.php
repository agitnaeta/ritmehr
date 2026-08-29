{{-- UM-09 — Halaman status import background (polling tiap 3 detik). --}}
@extends(backpack_view('blank'))

@section('header')
  <section class="container-fluid d-flex align-items-center">
    <h2 class="mb-0">Status Import Karyawan</h2>
    <a href="{{ url('admin/user') }}" class="btn btn-link ms-auto">← Kembali ke daftar</a>
  </section>
@endsection

@section('content')
<div class="row">
  <div class="col-lg-9">
    <div class="card"
         id="importStatusRoot"
         data-status-url="{{ route('user.import.status.json', $job->id) }}"
         data-errors-url="{{ route('user.import.errors.csv', $job->id) }}"
         data-users-url="{{ url('admin/user') }}"
         data-import-url="{{ route('user.import.form') }}"
         data-finished="{{ $job->isFinished() ? '1' : '0' }}">
      <div class="card-body">

        <p class="text-muted mb-1">
          File: <strong>{{ $job->original_name ?? '—' }}</strong>
          @if($job->started_at)· mulai {{ $job->started_at->format('d M Y H:i') }}@endif
        </p>

        <div class="d-flex align-items-center justify-content-between">
          <h3 class="mb-0" id="stTitle">Menyiapkan…</h3>
          <span id="stBadge" class="badge bg-blue-lt">Menunggu</span>
        </div>

        {{-- Progress bar --}}
        <div class="progress mt-3" style="height:14px">
          <div id="stBar" class="progress-bar progress-bar-striped progress-bar-animated"
               role="progressbar" style="width:0%"></div>
        </div>
        <div class="d-flex justify-content-between small text-muted mt-1">
          <span id="stMeta">0 dari {{ $job->total_rows }} baris</span>
          <span id="stPct">0%</span>
        </div>

        {{-- Statistik --}}
        <div class="row g-2 mt-3">
          <div class="col"><div class="border rounded p-3 text-center">
            <div class="h2 mb-0 text-blue" id="stTotal">{{ $job->total_rows }}</div>
            <div class="small text-muted text-uppercase">Total Baris</div></div></div>
          <div class="col"><div class="border rounded p-3 text-center">
            <div class="h2 mb-0 text-green" id="stImported">0</div>
            <div class="small text-muted text-uppercase">Berhasil</div></div></div>
          <div class="col"><div class="border rounded p-3 text-center">
            <div class="h2 mb-0 text-orange" id="stSkipped">0</div>
            <div class="small text-muted text-uppercase">Dilewati</div></div></div>
        </div>

        <p class="text-muted small mt-3" id="stHint">
          Anda boleh menutup halaman ini — proses berjalan di latar. Kembali kapan saja untuk melihat progres.
        </p>

        {{-- Aksi (muncul saat selesai) --}}
        <div id="stActions" class="d-flex gap-2 mt-3" style="display:none!important">
          <a id="stDownloadErr" href="#" class="btn btn-outline-warning" style="display:none">
            <i class="la la-download"></i> Unduh baris gagal (CSV)
          </a>
          <a id="stImportAgain" href="#" class="btn btn-outline-secondary">
            <i class="la la-redo"></i> Import file lain
          </a>
          <a id="stGotoUsers" href="#" class="btn btn-primary">Lihat daftar karyawan</a>
        </div>

        {{-- Pesan gagal total --}}
        <div id="stFailMsg" class="alert alert-danger mt-3" style="display:none"></div>

        {{-- Tabel baris gagal --}}
        <div id="stErrWrap" class="mt-4" style="display:none">
          <h4>Rincian baris gagal (<span id="stErrCount">0</span>)</h4>
          <div class="table-responsive">
            <table class="table table-sm table-vcenter">
              <thead><tr><th>Baris</th><th>Kolom</th><th>Nilai</th><th>Alasan</th></tr></thead>
              <tbody id="stErrBody"></tbody>
            </table>
          </div>
          <p class="text-muted small">
            Perbaiki baris gagal di CSV lalu unggah ulang — import idempoten by email, tidak akan dobel.
          </p>
        </div>

      </div>
    </div>
  </div>
</div>
@endsection

@section('after_scripts')
<script>
(function(){
  const root = document.getElementById('importStatusRoot');
  const statusUrl = root.dataset.statusUrl;
  const bar = document.getElementById('stBar');
  const pct = document.getElementById('stPct');
  const meta = document.getElementById('stMeta');
  const title = document.getElementById('stTitle');
  const badge = document.getElementById('stBadge');
  const elImported = document.getElementById('stImported');
  const elSkipped = document.getElementById('stSkipped');
  const elTotal = document.getElementById('stTotal');
  const actions = document.getElementById('stActions');
  const hint = document.getElementById('stHint');
  const errWrap = document.getElementById('stErrWrap');
  const errBody = document.getElementById('stErrBody');
  const errCount = document.getElementById('stErrCount');
  const failMsg = document.getElementById('stFailMsg');
  const btnDl = document.getElementById('stDownloadErr');
  const btnAgain = document.getElementById('stImportAgain');
  const btnUsers = document.getElementById('stGotoUsers');

  btnDl.href = root.dataset.errorsUrl;
  btnAgain.href = root.dataset.importUrl;
  btnUsers.href = root.dataset.usersUrl;

  let timer = null;

  function esc(s){ return String(s ?? '').replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c])); }

  function render(d){
    const p = d.progress || 0;
    bar.style.width = p + '%';
    pct.textContent = p + '%';
    meta.textContent = (d.processed||0) + ' dari ' + (d.total||0) + ' baris';
    elImported.textContent = d.imported||0;
    elSkipped.textContent = d.skipped||0;
    elTotal.textContent = d.total||0;

    if(d.status === 'queued'){ title.textContent='Menunggu antrian…'; badge.className='badge bg-blue-lt'; badge.textContent='Menunggu'; }
    else if(d.status === 'processing'){ title.textContent='Memproses import…'; badge.className='badge bg-blue-lt'; badge.textContent='Sedang berjalan'; }
    else if(d.status === 'done'){ title.textContent='Import selesai'; badge.className='badge bg-green-lt'; badge.textContent='✓ Selesai'; }
    else if(d.status === 'failed'){ title.textContent='Import gagal'; badge.className='badge bg-red-lt'; badge.textContent='✕ Gagal'; }

    if(d.finished){
      bar.classList.remove('progress-bar-animated','progress-bar-striped');
      if(d.status==='done'){ bar.classList.add('bg-green'); }
      if(d.status==='failed'){ bar.classList.add('bg-red'); }
      actions.style.setProperty('display','flex','important');
      hint.style.display='none';

      if(d.status==='failed'){
        failMsg.style.display='block';
        failMsg.textContent = d.message || 'Import gagal diproses.';
      }

      if((d.errorTotal||0) > 0){
        btnDl.style.display='inline-flex';
        errWrap.style.display='block';
        errCount.textContent = d.errorTotal;
        errBody.innerHTML = (d.errors||[]).map(e =>
          '<tr class="table-danger"><td>'+esc(e.row)+'</td><td>'+esc(e.column)+
          '</td><td><code>'+ (e.value==='' ? '(kosong)' : esc(e.value)) +'</code></td><td>'+esc(e.reason)+'</td></tr>'
        ).join('');
        if(d.errorTotal > (d.errors||[]).length){
          errBody.innerHTML += '<tr><td colspan="4" class="text-center text-muted">… '+
            (d.errorTotal-(d.errors||[]).length)+' baris gagal lainnya (lihat CSV)</td></tr>';
        }
      }

      if(timer){ clearInterval(timer); timer=null; }
    }
  }

  function poll(){
    fetch(statusUrl, {headers:{'X-Requested-With':'XMLHttpRequest'}})
      .then(r => r.json()).then(render).catch(()=>{});
  }

  poll();
  if(root.dataset.finished !== '1'){
    timer = setInterval(poll, 3000);
  }
})();
</script>
@endsection
