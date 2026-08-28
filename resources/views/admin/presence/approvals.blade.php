@extends(backpack_view('blank'))

@php
    $canDecide = backpack_user()?->can('presence.edit');
@endphp

@section('content')
<div class="container-fluid animated fadeIn" style="max-width: 1000px;">

    <div class="d-flex justify-content-between align-items-center mb-3 mt-2">
        <div>
            <h2 class="mb-0"><i class="la la-user-check text-warning"></i> Persetujuan Absensi</h2>
            <small class="text-muted">Absen mandiri (kamera) di luar radius kantor — menunggu peninjauan.</small>
        </div>
        <a href="{{ url($crud->route) }}" class="btn btn-sm btn-outline-secondary">
            <i class="la la-angle-left"></i> Daftar Kehadiran
        </a>
    </div>

    @if($pending->isEmpty())
        <div class="card">
            <div class="card-body text-center text-muted p-5">
                <i class="la la-check-circle la-3x text-success mb-2 d-block"></i>
                Tidak ada absensi yang menunggu persetujuan.
            </div>
        </div>
    @else
        <div class="row">
            @foreach($pending as $p)
                @php $lat = (float) $p->lat; $lng = (float) $p->lng; @endphp
                <div class="col-lg-6 mb-3">
                    <div class="card shadow-xs h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span class="fw-bold">{{ $p->user->name ?? '—' }}</span>
                            <span class="badge bg-danger">Luar Radius</span>
                        </div>
                        <div class="card-body">
                            <div class="row g-2 mb-2">
                                <div class="col-5">
                                    @if($p->selfie_path)
                                        <img src="{{ $p->selfieUrl() }}" alt="Selfie"
                                             class="img-fluid rounded" style="max-height:150px; width:100%; object-fit:cover;">
                                    @else
                                        <div class="text-muted small text-center p-3 bg-light rounded">
                                            <i class="la la-camera-retro"></i><br>Tanpa foto
                                        </div>
                                    @endif
                                </div>
                                <div class="col-7">
                                    <div id="map-{{ $p->id }}" style="height:150px; border-radius:10px;"></div>
                                </div>
                            </div>

                            <dl class="row small mb-3">
                                <dt class="col-4 text-muted fw-normal">Waktu</dt>
                                <dd class="col-8">{{ \Carbon\Carbon::parse($p->in)->isoFormat('D MMM Y, HH:mm') }}</dd>
                                <dt class="col-4 text-muted fw-normal">Cabang</dt>
                                <dd class="col-8">{{ $p->branch->name ?? '—' }}</dd>
                                <dt class="col-4 text-muted fw-normal">Koordinat</dt>
                                <dd class="col-8">{{ number_format($lat, 5) }}, {{ number_format($lng, 5) }}</dd>
                            </dl>

                            @if($canDecide)
                                <div class="d-flex gap-2">
                                    <form method="POST" action="{{ route('presence.approve', $p->id) }}" class="flex-fill">
                                        @csrf
                                        <button class="btn btn-success w-100 btn-sm">
                                            <i class="la la-check"></i> Setujui
                                        </button>
                                    </form>
                                    <button type="button" class="btn btn-outline-danger btn-sm flex-fill btn-reject"
                                            data-id="{{ $p->id }}" data-name="{{ $p->user->name }}">
                                        <i class="la la-times"></i> Tolak
                                    </button>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>

{{-- Reject modal (asks for a reason) --}}
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" id="rejectForm">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Tolak Absensi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-2">Menolak absensi <b id="reject-name"></b>.</p>
                    <textarea name="note" class="form-control" rows="3" placeholder="Alasan penolakan (opsional)"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger">Tolak Absensi</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@push('after_scripts')
<link href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" rel="stylesheet">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function () {
    @foreach($pending as $p)
    (function () {
        const lat = {{ (float) $p->lat }}, lng = {{ (float) $p->lng }};
        if (isNaN(lat) || isNaN(lng)) return;
        const map = L.map('map-{{ $p->id }}', { zoomControl:false, attributionControl:false }).setView([lat, lng], 15);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
        L.circleMarker([lat, lng], { radius:8, color:'#ef4444', fillColor:'#ef4444', fillOpacity:.9, weight:3 }).addTo(map);
        @if($p->branch && $p->branch->hasGeofence())
        L.circle([{{ (float) $p->branch->lat }}, {{ (float) $p->branch->lng }}], {
            radius: {{ max(1, (int) $p->branch->radius_meters) }}, color:'#22c55e', fillColor:'#22c55e', fillOpacity:.1, weight:2
        }).addTo(map);
        @endif
        setTimeout(() => map.invalidateSize(), 200);
    })();
    @endforeach

    // Reject modal wiring
    const modalEl = document.getElementById('rejectModal');
    const modal = new bootstrap.Modal(modalEl);
    const form = document.getElementById('rejectForm');
    document.querySelectorAll('.btn-reject').forEach(btn => {
        btn.addEventListener('click', () => {
            form.action = @json(url('admin/presence')) + '/' + btn.dataset.id + '/reject';
            document.getElementById('reject-name').textContent = btn.dataset.name;
            modal.show();
        });
    });
})();
</script>
@endpush
