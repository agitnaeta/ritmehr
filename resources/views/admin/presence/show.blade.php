@extends(backpack_view('blank'))

@php
    $u = $presence->user;
    $inAt = $presence->in ? \Carbon\Carbon::parse($presence->in) : null;
    $outAt = $presence->out ? \Carbon\Carbon::parse($presence->out) : null;
    $isCamera = $presence->source === 'camera';
    $hasCoord = $presence->lat && $presence->lng;
@endphp

@section('content')
<div class="container-fluid animated fadeIn" style="max-width: 960px;">

    <div class="d-flex justify-content-between align-items-center mb-3 mt-2">
        <div>
            <h2 class="mb-0">{{ $u->name ?? '—' }}</h2>
            <small class="text-muted">
                {{ $inAt?->isoFormat('dddd, D MMMM Y') ?? '—' }}
            </small>
        </div>
        <div>
            <a href="{{ url($crud->route) }}" class="btn btn-sm btn-outline-secondary">
                <i class="la la-angle-left"></i> Kembali
            </a>
            @if(backpack_user()?->can('presence.edit'))
                <a href="{{ url($crud->route.'/'.$presence->id.'/edit') }}" class="btn btn-sm btn-primary">
                    <i class="la la-edit"></i> Ubah
                </a>
            @endif
        </div>
    </div>

    <div class="row">
        {{-- Ringkasan kehadiran --}}
        <div class="col-lg-6 mb-3">
            <div class="card shadow-xs h-100">
                <div class="card-header fw-bold">
                    <i class="la la-clock text-primary"></i> Ringkasan Kehadiran
                </div>
                <div class="card-body p-0">
                    <table class="table table-vcenter mb-0">
                        <tbody>
                            <tr><td>Absen Masuk</td><td class="text-end">{{ $inAt?->format('H:i:s') ?? '—' }}</td></tr>
                            <tr><td>Absen Pulang</td><td class="text-end">{{ $outAt?->format('H:i:s') ?? '—' }}</td></tr>
                            <tr>
                                <td>Sumber Absen</td>
                                <td class="text-end">
                                    @if($isCamera)
                                        <span class="badge bg-primary"><i class="la la-camera"></i> Kamera (Mandiri)</span>
                                    @else
                                        <span class="badge bg-secondary"><i class="la la-qrcode"></i> QR</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td>Status</td>
                                <td class="text-end">
                                    @if($presence->is_late)
                                        <span class="badge bg-warning text-dark">Terlambat {{ $presence->late_minute }} mnt</span>
                                    @else
                                        <span class="badge bg-success">Tepat Waktu</span>
                                    @endif
                                    @if($presence->is_overtime)<span class="badge bg-info">Lembur</span>@endif
                                </td>
                            </tr>
                            <tr>
                                <td>Lokasi</td>
                                <td class="text-end">
                                    @if($presence->outside)
                                        <span class="badge bg-danger">Di Luar Radius</span>
                                    @else
                                        <span class="badge bg-success">Dalam Area</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td>Persetujuan</td>
                                <td class="text-end">
                                    @if($presence->approval_status === 'pending')
                                        <span class="badge bg-warning text-dark">Menunggu Persetujuan</span>
                                    @elseif($presence->approval_status === 'rejected')
                                        <span class="badge bg-dark">Ditolak</span>
                                    @else
                                        <span class="badge bg-success">Disetujui</span>
                                    @endif
                                </td>
                            </tr>
                            @if($presence->approval_note)
                                <tr><td>Catatan</td><td class="text-end small text-muted">{{ $presence->approval_note }}</td></tr>
                            @endif
                            @if($presence->approver)
                                <tr><td>Diputuskan oleh</td><td class="text-end">{{ $presence->approver->name }}</td></tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Bukti: selfie + peta --}}
        <div class="col-lg-6 mb-3">
            <div class="card shadow-xs h-100">
                <div class="card-header fw-bold">
                    <i class="la la-map-marked-alt text-danger"></i> Bukti Kehadiran
                </div>
                <div class="card-body">
                    @if($presence->selfie_path)
                        <div class="text-center mb-3">
                            <img src="{{ $presence->selfieUrl() }}" alt="Selfie bukti"
                                 class="img-fluid rounded" style="max-height:260px;">
                        </div>
                    @else
                        <p class="text-muted small mb-3"><i class="la la-camera-retro"></i> Tidak ada foto selfie untuk absen ini.</p>
                    @endif

                    @if($hasCoord)
                        <div id="presence-map" style="height:220px; border-radius:12px;"></div>
                        <div class="small text-muted mt-2">
                            Koordinat: {{ number_format((float) $presence->lat, 5) }}, {{ number_format((float) $presence->lng, 5) }}
                            @if($presence->accuracy) · akurasi ±{{ (int) $presence->accuracy }} m @endif
                        </div>
                    @else
                        <p class="text-muted small mb-0"><i class="la la-map-marker"></i> Koordinat tidak tersedia.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@if($hasCoord)
@push('after_scripts')
<link href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" rel="stylesheet">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function () {
    const lat = {{ (float) $presence->lat }}, lng = {{ (float) $presence->lng }};
    const map = L.map('presence-map', { zoomControl:false, attributionControl:false }).setView([lat, lng], 16);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
    L.circleMarker([lat, lng], { radius:9, color:'#2563eb', fillColor:'#2563eb', fillOpacity:.9, weight:3 })
        .addTo(map).bindTooltip('Lokasi absen');
    @if($presence->branch && $presence->branch->hasGeofence())
        L.circle([{{ (float) $presence->branch->lat }}, {{ (float) $presence->branch->lng }}], {
            radius: {{ max(1, (int) $presence->branch->radius_meters) }},
            color: '{{ $presence->outside ? '#ef4444' : '#22c55e' }}',
            fillColor: '{{ $presence->outside ? '#ef4444' : '#22c55e' }}', fillOpacity:.12, weight:2
        }).addTo(map);
    @endif
    setTimeout(() => map.invalidateSize(), 200);
})();
</script>
@endpush
@endif
