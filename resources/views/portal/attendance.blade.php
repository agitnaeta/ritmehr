@extends('portal.layout')
@section('title', 'Kehadiran')
@section('heading', 'Riwayat Kehadiran')

@section('content')
@if(setting('attendance_mode', 'qr') === 'camera')
<div class="mb-3 text-end">
    <a href="{{ route('portal.attendance.checkin') }}" class="btn btn-primary">
        <i class="la la-camera"></i> Absen Sekarang
    </a>
</div>
@endif
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label mb-0">Bulan</label>
                <input type="month" name="month" class="form-control form-control-sm"
                       value="{{ $month->format('Y-m') }}">
            </div>
            <div class="col-auto"><button class="btn btn-sm btn-primary">Tampilkan</button></div>
            <div class="col-auto ms-auto">
                <div class="btn-group btn-group-sm" role="group">
                    <button type="button" class="btn btn-outline-secondary active" id="btnViewCalendar">
                        <i class="la la-calendar"></i> Kalender
                    </button>
                    <button type="button" class="btn btn-outline-secondary" id="btnViewTable">
                        <i class="la la-list"></i> Tabel
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="row g-3 mb-3">
    @foreach([
        ['Hadir', $summary['present'], 'text-success'],
        ['Terlambat', $summary['late'], 'text-warning'],
        ['Lembur', $summary['overtime'], 'text-info'],
        ['Di Luar Radius', $summary['outside'], 'text-danger'],
    ] as [$label, $value, $class])
        <div class="col-6 col-md-3">
            <div class="card stat-card">
                <div class="card-body">
                    <div class="text-muted small">{{ $label }}</div>
                    <div class="value {{ $class }}">{{ $value }}</div>
                </div>
            </div>
        </div>
    @endforeach
</div>

@php
    $start = $month->copy()->startOfMonth();
    $end = $month->copy()->endOfMonth();
    $gridStart = $start->copy()->startOfWeek(\Carbon\Carbon::MONDAY);
    $gridEnd = $end->copy()->endOfWeek(\Carbon\Carbon::SUNDAY);
@endphp

{{-- Calendar view --}}
<div class="card" id="viewCalendar">
    <div class="card-body p-2">
        <div class="mb-2 small text-muted d-flex flex-wrap gap-3">
            <span><span class="badge bg-success">&nbsp;</span> Tepat Waktu</span>
            <span><span class="badge bg-warning text-dark">&nbsp;</span> Terlambat</span>
            <span><span class="badge bg-info">&nbsp;</span> Lembur</span>
            <span><span class="badge bg-danger">&nbsp;</span> Luar Radius</span>
        </div>
        <table class="table table-bordered mb-0" style="table-layout: fixed;">
            <thead>
                <tr class="text-center">
                    @foreach(['Sen','Sel','Rab','Kam','Jum','Sab','Min'] as $d)
                        <th class="small">{{ $d }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @php $cursor = $gridStart->copy(); @endphp
                @while($cursor->lte($gridEnd))
                    <tr>
                        @for($i = 0; $i < 7; $i++)
                            @php
                                $key = $cursor->toDateString();
                                $inMonth = $cursor->month === $month->month;
                                $p = $byDate[$key] ?? null;
                            @endphp
                            <td class="align-top p-1 {{ $inMonth ? '' : 'bg-light text-muted' }}" style="height: 6.5rem;">
                                <div class="small fw-bold">{{ $cursor->day }}</div>
                                @if($p)
                                    <div class="small">
                                        <div>{{ \Carbon\Carbon::parse($p->in)->format('H:i') }}
                                            @if($p->out)&rarr; {{ \Carbon\Carbon::parse($p->out)->format('H:i') }}@endif
                                        </div>
                                        <div class="mt-1">
                                            @if($p->is_late)
                                                <span class="badge bg-warning text-dark" title="Telat {{ $p->late_minute }} mnt">Telat</span>
                                            @else
                                                <span class="badge bg-success">Hadir</span>
                                            @endif
                                            @if($p->is_overtime)<span class="badge bg-info">Lembur</span>@endif
                                            @if($p->outside)<span class="badge bg-danger">Luar</span>@endif
                                            @if($p->source === 'camera')<span class="badge bg-primary" title="Absen kamera"><i class="la la-camera"></i></span>@endif
                                        </div>
                                    </div>
                                @endif
                            </td>
                            @php $cursor->addDay(); @endphp
                        @endfor
                    </tr>
                @endwhile
            </tbody>
        </table>
    </div>
</div>

{{-- Table view (hidden by default) --}}
<div class="card" id="viewTable" style="display:none;">
    <div class="card-body p-0">
        <div class="table-responsive hide-on-mobile">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Masuk</th>
                        <th>Pulang</th>
                        <th>Sumber</th>
                        <th>Status</th>
                        <th class="text-end">Telat (menit)</th>
                        <th class="text-center">Bukti</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($presences as $p)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($p->in)->format('d/m/Y') }}</td>
                            <td>{{ \Carbon\Carbon::parse($p->in)->format('H:i') }}</td>
                            <td>{{ $p->out ? \Carbon\Carbon::parse($p->out)->format('H:i') : '—' }}</td>
                            <td>
                                @if($p->source === 'camera')
                                    <span class="badge bg-primary"><i class="la la-camera"></i> Kamera</span>
                                @else
                                    <span class="badge bg-secondary"><i class="la la-qrcode"></i> QR</span>
                                @endif
                            </td>
                            <td>
                                @if($p->is_late)
                                    <span class="badge bg-warning text-dark">Terlambat</span>
                                @else
                                    <span class="badge bg-success">Tepat Waktu</span>
                                @endif
                                @if($p->is_overtime)<span class="badge bg-info">Lembur</span>@endif
                                @if($p->outside)<span class="badge bg-danger">Luar Radius</span>@endif
                                @if($p->approval_status === 'pending')<span class="badge bg-warning text-dark">Menunggu Persetujuan</span>
                                @elseif($p->approval_status === 'rejected')<span class="badge bg-dark">Ditolak</span>@endif
                            </td>
                            <td class="text-end">{{ $p->late_minute ?: '—' }}</td>
                            <td class="text-center">
                                @if($p->selfie_path || ($p->lat && $p->lng))
                                    <button type="button" class="btn btn-sm btn-outline-primary btn-bukti"
                                            data-selfie="{{ $p->selfieUrl() ?? '' }}"
                                            data-lat="{{ $p->lat }}" data-lng="{{ $p->lng }}"
                                            data-date="{{ \Carbon\Carbon::parse($p->in)->format('d/m/Y H:i') }}"
                                            data-outside="{{ $p->outside ? 1 : 0 }}">
                                        <i class="la la-eye"></i> Lihat
                                    </button>
                                @else
                                    <span class="text-muted small">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted p-4">
                            Belum ada kehadiran tercatat pada bulan ini.
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Versi agenda kartu untuk mobile (tampil <992px via CSS). --}}
        <div class="data-cards p-3">
            @forelse($presences as $p)
                <div class="data-card">
                    <div class="data-card__top">
                        <div>
                            <div class="data-card__title">
                                {{ \Carbon\Carbon::parse($p->in)->format('H:i') }}
                                @if($p->out)&rarr; {{ \Carbon\Carbon::parse($p->out)->format('H:i') }}@endif
                            </div>
                            <div class="data-card__meta">
                                {{ \Carbon\Carbon::parse($p->in)->translatedFormat('l, d M Y') }} ·
                                @if($p->source === 'camera')<i class="la la-camera"></i> Kamera @else<i class="la la-qrcode"></i> QR @endif
                                @if($p->late_minute)· telat {{ $p->late_minute }} mnt @endif
                            </div>
                        </div>
                        @if($p->is_late)
                            <span class="badge bg-warning text-dark">Terlambat</span>
                        @else
                            <span class="badge bg-success">Tepat Waktu</span>
                        @endif
                    </div>
                    @if($p->is_overtime || $p->outside || $p->approval_status === 'pending' || $p->approval_status === 'rejected' || $p->selfie_path || ($p->lat && $p->lng))
                        <div class="data-card__foot">
                            <span class="d-flex flex-wrap gap-1">
                                @if($p->is_overtime)<span class="badge bg-info">Lembur</span>@endif
                                @if($p->outside)<span class="badge bg-danger">Luar Radius</span>@endif
                                @if($p->approval_status === 'pending')<span class="badge bg-warning text-dark">Menunggu</span>
                                @elseif($p->approval_status === 'rejected')<span class="badge bg-dark">Ditolak</span>@endif
                            </span>
                            @if($p->selfie_path || ($p->lat && $p->lng))
                                <button type="button" class="btn btn-sm btn-outline-primary btn-bukti"
                                        data-selfie="{{ $p->selfieUrl() ?? '' }}"
                                        data-lat="{{ $p->lat }}" data-lng="{{ $p->lng }}"
                                        data-date="{{ \Carbon\Carbon::parse($p->in)->format('d/m/Y H:i') }}"
                                        data-outside="{{ $p->outside ? 1 : 0 }}">
                                    <i class="la la-eye"></i> Bukti
                                </button>
                            @endif
                        </div>
                    @endif
                </div>
            @empty
                <div class="empty-state">
                    <i class="la la-calendar-times"></i>
                    Belum ada kehadiran tercatat pada bulan ini.
                </div>
            @endforelse
        </div>
    </div>
</div>

{{-- Modal bukti kehadiran (selfie + peta) --}}
<link href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" rel="stylesheet">
<div class="modal fade" id="buktiModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="la la-map-marked-alt"></i> Bukti Kehadiran</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="text-muted small mb-2" id="bukti-date"></div>
                <div id="bukti-selfie-wrap" class="mb-3 text-center">
                    <img id="bukti-selfie" src="" alt="Selfie bukti" class="img-fluid rounded" style="max-height:280px;">
                </div>
                <div id="bukti-map" style="height:220px; border-radius:12px;"></div>
                <div id="bukti-outside" class="alert alert-warning small mt-2 mb-0 d-none">
                    <i class="la la-exclamation-triangle"></i> Absen di luar radius kantor — menunggu persetujuan manajer.
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function () {
    let bmap = null, bmarker = null;
    const modalEl = document.getElementById('buktiModal');
    if (!modalEl) return;
    const modal = new bootstrap.Modal(modalEl);

    document.querySelectorAll('.btn-bukti').forEach(btn => {
        btn.addEventListener('click', () => {
            const d = btn.dataset;

            // Selfie
            const wrap = document.getElementById('bukti-selfie-wrap');
            const img = document.getElementById('bukti-selfie');
            if (d.selfie) { img.src = d.selfie; wrap.classList.remove('d-none'); }
            else { wrap.classList.add('d-none'); }

            document.getElementById('bukti-date').textContent = 'Direkam: ' + (d.date || '-');
            document.getElementById('bukti-outside').classList.toggle('d-none', d.outside !== '1');

            modal.show();

            // Map — build after modal is shown so Leaflet sizes correctly.
            setTimeout(() => {
                const lat = parseFloat(d.lat), lng = parseFloat(d.lng);
                const hasCoord = !isNaN(lat) && !isNaN(lng);
                if (!bmap) {
                    bmap = L.map('bukti-map', { zoomControl:false, attributionControl:false });
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(bmap);
                }
                if (hasCoord) {
                    bmap.setView([lat, lng], 16);
                    if (bmarker) bmap.removeLayer(bmarker);
                    bmarker = L.circleMarker([lat, lng], { radius:9, color:'#2563eb', fillColor:'#2563eb', fillOpacity:.9, weight:3 })
                        .addTo(bmap).bindTooltip('Lokasi absen');
                }
                bmap.invalidateSize();
            }, 300);
        });
    });
})();
</script>

<script>
(function () {
    const cal = document.getElementById('viewCalendar');
    const tbl = document.getElementById('viewTable');
    const bCal = document.getElementById('btnViewCalendar');
    const bTbl = document.getElementById('btnViewTable');
    const KEY = 'portalAttendanceView';

    function show(view) {
        const isCal = view !== 'table';
        cal.style.display = isCal ? '' : 'none';
        tbl.style.display = isCal ? 'none' : '';
        bCal.classList.toggle('active', isCal);
        bTbl.classList.toggle('active', !isCal);
        try { localStorage.setItem(KEY, isCal ? 'calendar' : 'table'); } catch (e) {}
    }
    bCal.addEventListener('click', () => show('calendar'));
    bTbl.addEventListener('click', () => show('table'));
    // Di mobile, agenda (tabel) lebih terbaca daripada kalender 7-kolom yang sempit,
    // jadi jadikan default bila pengguna belum pernah memilih.
    let saved = null;
    try { saved = localStorage.getItem(KEY); } catch (e) {}
    const isMobile = window.matchMedia('(max-width: 991.98px)').matches;
    show(saved || (isMobile ? 'table' : 'calendar'));
})();
</script>
@endsection
