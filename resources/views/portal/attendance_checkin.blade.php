@extends('portal.layout')

@section('title', 'Absen Mandiri')
@section('heading', 'Absen Mandiri')

@push('head')
@endpush

@section('content')
@php
    $nextLabel = $nextAction === 'out' ? 'Ambil Foto & Absen Keluar' : 'Ambil Foto & Absen Masuk';
@endphp

<link href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" rel="stylesheet">
<style>
    .cam-wrap { position:relative; aspect-ratio:3/4; max-height:52vh; background:#0b1120; border-radius:16px; overflow:hidden; }
    .cam-wrap video, .cam-wrap canvas { width:100%; height:100%; object-fit:cover; display:block; }
    .cam-wrap canvas { display:none; }
    .cam-reticle { position:absolute; left:50%; top:44%; transform:translate(-50%,-50%); width:44%; aspect-ratio:1; border-radius:50%; box-shadow:0 0 0 3px rgba(255,255,255,.55), 0 0 0 9999px rgba(11,17,32,.28); pointer-events:none; }
    .cam-badge { position:absolute; top:12px; left:12px; background:rgba(239,68,68,.92); color:#fff; font-size:11px; font-weight:600; padding:4px 9px; border-radius:999px; display:flex; align-items:center; gap:5px; }
    .cam-badge .d { width:7px; height:7px; border-radius:50%; background:#fff; animation:blink 1.2s infinite; }
    @keyframes blink { 0%,100%{opacity:1} 50%{opacity:.3} }
    #cimap { height:190px; border-radius:14px; }
    .geo-status { display:flex; align-items:center; gap:10px; padding:12px 14px; border-radius:12px; background:#f1f5f9; }
    .geo-status.in { background:#ecfdf5; }
    .geo-status.out { background:#fef2f2; }
    .geo-status .ic { width:38px; height:38px; border-radius:10px; display:grid; place-items:center; background:#94a3b8; color:#fff; flex:none; font-size:20px; }
    .geo-status.in .ic { background:#22c55e; }
    .geo-status.out .ic { background:#ef4444; }
</style>

<div class="row justify-content-center g-3">
    <div class="col-12 col-lg-5">
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="cam-wrap mb-3">
                    <video id="cam" playsinline muted autoplay></video>
                    <canvas id="shot"></canvas>
                    <div class="cam-reticle"></div>
                    <span class="cam-badge"><span class="d"></span> KAMERA AKTIF</span>
                </div>

                <button id="btn-absen" class="btn btn-primary w-100 btn-lg" disabled>
                    <i class="la la-camera"></i> {{ $nextLabel }}
                </button>
                <p class="text-center text-muted small mt-2 mb-0" id="hint">Menyiapkan kamera & lokasi…</p>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-5">
        <div class="card shadow-sm">
            <div class="card-body">
                <div id="geo" class="geo-status mb-3">
                    <div class="ic"><i class="la la-map-marker"></i></div>
                    <div>
                        <b id="geo-title">Mendeteksi lokasi…</b>
                        <div class="small text-muted" id="geo-sub">Izinkan akses lokasi di perangkat Anda</div>
                    </div>
                </div>

                <div id="cimap" class="mb-3"></div>

                <dl class="row mb-0 small">
                    <dt class="col-5 text-muted fw-normal">Karyawan</dt><dd class="col-7">{{ $user->name }}</dd>
                    <dt class="col-5 text-muted fw-normal">Koordinat</dt><dd class="col-7" id="m-coord">—</dd>
                    <dt class="col-5 text-muted fw-normal">Akurasi GPS</dt><dd class="col-7" id="m-acc">—</dd>
                </dl>
            </div>
        </div>
    </div>
</div>

<div id="result" class="alert d-none mt-3" role="alert"></div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function () {
    'use strict';

    const CFG = {
        storeUrl: @json(route('portal.attendance.checkin.store')),
        csrf: @json(csrf_token()),
        center: @json($center),
        requireSelfie: @json($requireSelfie),
    };

    const video = document.getElementById('cam');
    const canvas = document.getElementById('shot');
    const btn = document.getElementById('btn-absen');
    const hint = document.getElementById('hint');
    const geo = document.getElementById('geo');
    const geoTitle = document.getElementById('geo-title');
    const geoSub = document.getElementById('geo-sub');
    const resultBox = document.getElementById('result');

    let pos = null;        // {lat, lng, accuracy}
    let camReady = false;

    // ── Map ──────────────────────────────────────────────
    const c = CFG.center;
    const map = L.map('cimap', { zoomControl:false, attributionControl:false })
        .setView([c.lat || 0, c.lng || 0], c.lat ? 16 : 2);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

    let radiusCircle = null, meMarker = null;
    if (c.lat && c.lng) {
        radiusCircle = L.circle([c.lat, c.lng], { radius:c.radius, color:'#22c55e', fillColor:'#22c55e', fillOpacity:.12, weight:2 }).addTo(map);
        L.marker([c.lat, c.lng]).addTo(map).bindTooltip(c.label || 'Kantor');
    }
    setTimeout(() => map.invalidateSize(), 250);

    function haversine(lat1, lng1, lat2, lng2) {
        const R = 6371000, toRad = d => d * Math.PI / 180;
        const dLat = toRad(lat2 - lat1), dLng = toRad(lng2 - lng1);
        const a = Math.sin(dLat/2)**2 + Math.cos(toRad(lat1))*Math.cos(toRad(lat2))*Math.sin(dLng/2)**2;
        return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    }

    function paintGeo() {
        if (!pos) return;
        document.getElementById('m-coord').textContent = pos.lat.toFixed(5) + ', ' + pos.lng.toFixed(5);
        document.getElementById('m-acc').textContent = '±' + Math.round(pos.accuracy) + ' m';

        if (!meMarker) {
            meMarker = L.circleMarker([pos.lat, pos.lng], { radius:9, color:'#2563eb', fillColor:'#2563eb', fillOpacity:.9, weight:3 }).addTo(map).bindTooltip('Anda di sini');
        } else {
            meMarker.setLatLng([pos.lat, pos.lng]);
        }
        map.setView([pos.lat, pos.lng], 16);

        if (c.lat && c.lng) {
            const dist = haversine(pos.lat, pos.lng, c.lat, c.lng);
            const inside = dist <= c.radius;
            geo.className = 'geo-status mb-3 ' + (inside ? 'in' : 'out');
            geo.querySelector('.ic i').className = inside ? 'la la-check' : 'la la-exclamation-triangle';
            geoTitle.textContent = inside ? 'Dalam area kantor' : 'Di luar area kantor';
            geoSub.textContent = (inside ? '' : 'Absen tetap tercatat, perlu persetujuan manajer · ')
                + Math.round(dist) + ' m dari titik pusat (radius ' + c.radius + ' m)';
            if (radiusCircle) radiusCircle.setStyle({ color: inside ? '#22c55e' : '#ef4444', fillColor: inside ? '#22c55e' : '#ef4444' });
        } else {
            geo.className = 'geo-status mb-3';
            geoTitle.textContent = 'Geofence belum diatur';
            geoSub.textContent = 'Lokasi tersimpan sebagai bukti';
        }
        maybeEnable();
    }

    function maybeEnable() {
        btn.disabled = !(pos && (camReady || !CFG.requireSelfie));
        if (!btn.disabled) hint.textContent = 'Siap. Tekan tombol untuk absen.';
    }

    // ── Camera ───────────────────────────────────────────
    navigator.mediaDevices?.getUserMedia({ video: { facingMode: 'user' }, audio: false })
        .then(stream => { video.srcObject = stream; camReady = true; maybeEnable(); })
        .catch(() => {
            camReady = false; hint.textContent = 'Kamera tidak tersedia.';
            if (CFG.requireSelfie) { btn.disabled = true; }
            maybeEnable();
        });

    // ── Location ─────────────────────────────────────────
    if (navigator.geolocation) {
        navigator.geolocation.watchPosition(
            p => { pos = { lat:p.coords.latitude, lng:p.coords.longitude, accuracy:p.coords.accuracy }; paintGeo(); },
            () => { geoTitle.textContent = 'Lokasi ditolak'; geoSub.textContent = 'Izinkan akses lokasi lalu muat ulang'; },
            { enableHighAccuracy:true, timeout:10000, maximumAge:15000 }
        );
    } else {
        geoTitle.textContent = 'Geolokasi tidak didukung perangkat';
    }

    // ── Submit ───────────────────────────────────────────
    btn.addEventListener('click', async () => {
        if (!pos) return;
        btn.disabled = true; hint.textContent = 'Menyimpan…';

        let selfie = null;
        if (camReady) {
            canvas.width = video.videoWidth || 480;
            canvas.height = video.videoHeight || 640;
            canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
            selfie = canvas.toDataURL('image/jpeg', 0.85);
        }

        try {
            const res = await fetch(CFG.storeUrl, {
                method: 'POST',
                headers: { 'Content-Type':'application/json', 'Accept':'application/json', 'X-CSRF-TOKEN':CFG.csrf },
                body: JSON.stringify({ lat:pos.lat, lng:pos.lng, accuracy:pos.accuracy, selfie }),
            });
            const data = await res.json().catch(() => ({}));

            if (!res.ok) {
                resultBox.className = 'alert alert-danger mt-3';
                resultBox.textContent = data.message || 'Gagal menyimpan absensi.';
                resultBox.classList.remove('d-none');
                btn.disabled = false;
                return;
            }

            resultBox.className = 'alert ' + (data.outside ? 'alert-warning' : 'alert-success') + ' mt-3';
            resultBox.innerHTML = '<b>' + data.message + '</b>' +
                (data.outside ? '<br>Di luar radius — menunggu persetujuan manajer.' : '');
            resultBox.classList.remove('d-none');
            hint.textContent = 'Selesai.';
        } catch (e) {
            resultBox.className = 'alert alert-danger mt-3';
            resultBox.textContent = 'Gagal terhubung — periksa koneksi.';
            resultBox.classList.remove('d-none');
            btn.disabled = false;
        }
    });
})();
</script>
@endsection
