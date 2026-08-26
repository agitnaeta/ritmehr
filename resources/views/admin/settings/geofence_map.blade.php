{{-- M07/M15 — Map picker for the GLOBAL office geofence (Leaflet + OpenStreetMap, no API key). --}}
{{-- Reads/writes the settings inputs #fld-office_lat, #fld-office_lng, #fld-office_radius. --}}

@push('after_styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
          integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <style>
        #office-map { height: 340px; border-radius: .5rem; z-index: 0; }
        .office-map-search { display:flex; gap:.5rem; margin-bottom:.5rem; }
        .office-map-hint { font-size:.8rem; color:#6b7280; margin-top:.35rem; }
    </style>
@endpush

<div class="mb-3">
    <label class="form-label">Pilih Lokasi Kantor di Peta</label>
    <div class="office-map-search">
        <input type="text" id="office-map-search" class="form-control"
               placeholder="Cari alamat / nama tempat, mis. Monas Jakarta…" autocomplete="off">
        <button type="button" id="office-map-search-btn" class="btn btn-outline-secondary">
            <i class="la la-search"></i> Cari
        </button>
    </div>
    <div id="office-map"></div>
    <p class="office-map-hint">
        Klik peta atau geser penanda untuk mengatur titik geofence global. Lingkaran biru = radius absensi.
        Koordinat &amp; radius di bawah ikut terisi otomatis. Dipakai sebagai fallback bila cabang belum punya koordinat.
    </p>
</div>

@push('after_scripts')
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
            integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script>
    (function () {
        function initOfficeMap() {
            var mapEl = document.getElementById('office-map');
            if (!mapEl || mapEl.dataset.ready) return null;
            mapEl.dataset.ready = '1';

            var latInput = document.getElementById('fld-office_lat');
            var lngInput = document.getElementById('fld-office_lng');
            var radiusInput = document.getElementById('fld-office_radius');

            // Default centre: Indonesia (Jakarta) if no coordinates yet.
            var startLat = parseFloat(latInput && latInput.value) || -6.2087634;
            var startLng = parseFloat(lngInput && lngInput.value) || 106.845599;
            var hasInitial = !!(latInput && latInput.value && lngInput && lngInput.value);
            var startRadius = parseInt(radiusInput && radiusInput.value) || 100;

            var map = L.map('office-map').setView([startLat, startLng], hasInitial ? 16 : 11);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap'
            }).addTo(map);

            var marker = L.marker([startLat, startLng], { draggable: true }).addTo(map);
            var circle = L.circle([startLat, startLng], { radius: startRadius, color: '#2563eb', fillOpacity: 0.1 }).addTo(map);

            function sync(lat, lng) {
                if (latInput) latInput.value = lat.toFixed(7);
                if (lngInput) lngInput.value = lng.toFixed(7);
                marker.setLatLng([lat, lng]);
                circle.setLatLng([lat, lng]);
            }

            map.on('click', function (e) { sync(e.latlng.lat, e.latlng.lng); });
            marker.on('dragend', function () {
                var p = marker.getLatLng();
                sync(p.lat, p.lng);
            });

            if (radiusInput) {
                radiusInput.addEventListener('input', function () {
                    var r = parseInt(radiusInput.value) || 1;
                    circle.setRadius(r);
                });
            }

            // Keep the map in sync if lat/lng typed manually.
            [latInput, lngInput].forEach(function (inp) {
                if (!inp) return;
                inp.addEventListener('change', function () {
                    var la = parseFloat(latInput.value), ln = parseFloat(lngInput.value);
                    if (!isNaN(la) && !isNaN(ln)) {
                        marker.setLatLng([la, ln]); circle.setLatLng([la, ln]);
                        map.setView([la, ln], 16);
                    }
                });
            });

            // Address search via Nominatim (OSM, free, no key).
            function doSearch() {
                var q = document.getElementById('office-map-search').value.trim();
                if (!q) return;
                fetch('https://nominatim.openstreetmap.org/search?format=json&limit=1&q=' + encodeURIComponent(q), {
                    headers: { 'Accept': 'application/json' }
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data && data.length) {
                        var la = parseFloat(data[0].lat), ln = parseFloat(data[0].lon);
                        sync(la, ln);
                        map.setView([la, ln], 16);
                    } else {
                        alert('Lokasi tidak ditemukan. Coba kata kunci lain.');
                    }
                })
                .catch(function () { alert('Gagal mencari lokasi. Coba lagi.'); });
            }

            var btn = document.getElementById('office-map-search-btn');
            if (btn) btn.addEventListener('click', doSearch);
            var searchInput = document.getElementById('office-map-search');
            if (searchInput) searchInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') { e.preventDefault(); doSearch(); }
            });

            // Leaflet needs a size recalculation once the container is visible.
            setTimeout(function () { map.invalidateSize(); }, 200);
            return map;
        }

        function boot() {
            var map = initOfficeMap();
            // The Lokasi tab is hidden until clicked; recalc size when it shows.
            var lokasiTab = document.getElementById('tab-lokasi');
            if (lokasiTab) {
                lokasiTab.addEventListener('shown.bs.tab', function () {
                    if (map) setTimeout(function () { map.invalidateSize(); }, 100);
                });
            }
        }

        if (document.readyState !== 'loading') boot();
        else document.addEventListener('DOMContentLoaded', boot);
    })();
    </script>
@endpush
