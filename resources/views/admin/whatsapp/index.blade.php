@extends(backpack_view('blank'))

@section('header')
    <section class="container-fluid">
        <h2>Koneksi WhatsApp <small class="text-muted">hubungkan nomor tanpa buka WAHA</small></h2>
    </section>
@endsection

@section('content')
<div class="row">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header"><strong>Status Koneksi</strong></div>
            <div class="card-body">

                @unless($configured)
                    <div class="alert alert-warning mb-0">
                        WAHA belum dikonfigurasi. Buka <a href="{{ backpack_url('settings') }}">Pengaturan → Notifikasi</a>
                        dan isi <strong>WAHA Base URL</strong> dulu.
                    </div>
                @else
                    @unless($enabled)
                        <div class="alert alert-info">
                            WhatsApp saat ini <strong>nonaktif</strong> (mode log). Aktifkan di
                            <a href="{{ backpack_url('settings') }}">Pengaturan</a> agar pesan benar-benar terkirim.
                        </div>
                    @endunless

                    {{-- Status banner --}}
                    <div id="waStatusBox" class="d-flex align-items-center gap-3 p-3 rounded border mb-3">
                        <span id="waDot" class="badge bg-secondary" style="width:14px;height:14px;border-radius:50%;">&nbsp;</span>
                        <div>
                            <div id="waStateLabel" class="fw-bold">Memeriksa…</div>
                            <small id="waStateDetail" class="text-muted"></small>
                        </div>
                    </div>

                    {{-- QR area (shown only when scanning is needed) --}}
                    <div id="waQrArea" class="text-center mb-3" style="display:none;">
                        <p class="text-muted mb-2">Buka WhatsApp di HP → <strong>Perangkat Tertaut</strong> → <strong>Tautkan Perangkat</strong>, lalu scan:</p>
                        <img id="waQrImg" alt="QR WhatsApp" style="max-width:280px;border:1px solid #e3e6ef;border-radius:.5rem;padding:.5rem;background:#fff;">
                    </div>

                    {{-- Connected info --}}
                    <div id="waConnected" class="mb-3" style="display:none;">
                        <div class="alert alert-success mb-0">
                            <i class="la la-check-circle"></i> Tersambung sebagai <strong id="waMe">—</strong>
                        </div>
                    </div>

                    {{-- Actions --}}
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-success" id="btnConnect">
                            <i class="la la-plug"></i> Sambungkan
                        </button>
                        <button type="button" class="btn btn-outline-danger" id="btnLogout" style="display:none;">
                            <i class="la la-sign-out-alt"></i> Putuskan
                        </button>
                        <button type="button" class="btn btn-outline-secondary" id="btnRefresh">
                            <i class="la la-sync"></i> Segarkan
                        </button>
                    </div>
                @endunless
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card">
            <div class="card-header"><strong>Cara Kerja</strong></div>
            <div class="card-body small text-muted">
                <ol class="mb-0 ps-3">
                    <li>Klik <strong>Sambungkan</strong> untuk memulai sesi.</li>
                    <li>Scan <strong>QR</strong> yang muncul di sini pakai WhatsApp HP.</li>
                    <li>Status berubah otomatis jadi <span class="text-success">Tersambung</span>.</li>
                    <li>Kirim tes dari <a href="{{ backpack_url('settings') }}">Pengaturan → Kirim Tes</a>.</li>
                </ol>
                <hr>
                Semua diproksi lewat server aplikasi — kredensial WAHA tidak pernah dikirim ke browser.
            </div>
        </div>
    </div>
</div>

@if($configured)
<script>
(function () {
    const csrf = '{{ csrf_token() }}';
    const urls = {
        status: '{{ backpack_url('whatsapp/status') }}',
        start:  '{{ backpack_url('whatsapp/start') }}',
        qr:     '{{ backpack_url('whatsapp/qr') }}',
        logout: '{{ backpack_url('whatsapp/logout') }}',
    };

    const dot = document.getElementById('waDot');
    const stateLabel = document.getElementById('waStateLabel');
    const stateDetail = document.getElementById('waStateDetail');
    const qrArea = document.getElementById('waQrArea');
    const qrImg = document.getElementById('waQrImg');
    const connected = document.getElementById('waConnected');
    const meEl = document.getElementById('waMe');
    const btnConnect = document.getElementById('btnConnect');
    const btnLogout = document.getElementById('btnLogout');
    const btnRefresh = document.getElementById('btnRefresh');

    let timer = null;

    function setDot(color) { dot.style.background = color; }

    function refreshQr() {
        // cache-bust so the browser re-fetches the current QR
        qrImg.src = urls.qr + '?t=' + Date.now();
    }

    function render(s) {
        const state = (s.state || 'UNKNOWN').toUpperCase();

        if (!s.reachable) {
            setDot('#dc3545');
            stateLabel.textContent = 'Tidak dapat terhubung ke WAHA';
            stateDetail.textContent = s.error || 'Periksa apakah container WAHA berjalan.';
            qrArea.style.display = 'none';
            connected.style.display = 'none';
            btnLogout.style.display = 'none';
            btnConnect.style.display = '';
            return;
        }

        if (state === 'WORKING') {
            setDot('#28a745');
            stateLabel.textContent = 'Tersambung';
            stateDetail.textContent = 'Nomor WhatsApp aktif.';
            qrArea.style.display = 'none';
            connected.style.display = '';
            meEl.textContent = (s.me && (s.me.pushName || s.me.id || s.me.number)) ? (s.me.pushName || s.me.id || s.me.number) : 'Terhubung';
            btnConnect.style.display = 'none';
            btnLogout.style.display = '';
            stopPolling();
            return;
        }

        if (state === 'SCAN_QR_CODE') {
            setDot('#ffc107');
            stateLabel.textContent = 'Menunggu Scan QR';
            stateDetail.textContent = 'Scan QR di bawah dengan WhatsApp HP.';
            qrArea.style.display = '';
            refreshQr();
            connected.style.display = 'none';
            btnConnect.style.display = 'none';
            btnLogout.style.display = 'none';
            return;
        }

        if (state === 'STARTING') {
            setDot('#ffc107');
            stateLabel.textContent = 'Memulai sesi…';
            stateDetail.textContent = 'Mohon tunggu.';
            qrArea.style.display = 'none';
            connected.style.display = 'none';
            btnConnect.style.display = 'none';
            btnLogout.style.display = 'none';
            return;
        }

        // STOPPED / UNKNOWN / NOT_CONFIGURED
        setDot('#6c757d');
        stateLabel.textContent = 'Terputus';
        stateDetail.textContent = s.error || 'Klik Sambungkan untuk memulai.';
        qrArea.style.display = 'none';
        connected.style.display = 'none';
        btnConnect.style.display = '';
        btnLogout.style.display = 'none';
    }

    async function poll() {
        try {
            const res = await fetch(urls.status, { headers: { 'Accept': 'application/json' } });
            render(await res.json());
        } catch (e) {
            render({ reachable: false, error: 'Gagal memuat status.' });
        }
    }

    function startPolling() {
        if (timer) return;
        timer = setInterval(poll, 3000);
    }
    function stopPolling() {
        if (timer) { clearInterval(timer); timer = null; }
    }

    btnConnect.addEventListener('click', async () => {
        btnConnect.disabled = true;
        stateLabel.textContent = 'Memulai sesi…';
        try {
            await fetch(urls.start, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' } });
        } catch (e) {}
        btnConnect.disabled = false;
        await poll();
        startPolling();
    });

    btnLogout.addEventListener('click', async () => {
        if (!confirm('Putuskan koneksi WhatsApp?')) return;
        btnLogout.disabled = true;
        try {
            await fetch(urls.logout, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' } });
        } catch (e) {}
        btnLogout.disabled = false;
        await poll();
        startPolling();
    });

    btnRefresh.addEventListener('click', poll);

    // Initial load
    poll().then(startPolling);
})();
</script>
@endif
@endsection
