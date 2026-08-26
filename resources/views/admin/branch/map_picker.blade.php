{{-- M07 — Map picker for branch geofence (Leaflet + OpenStreetMap, no API key). --}}
{{-- Reads/writes the sibling lat, lng and radius_meters inputs on the branch form. --}}

@push('after_styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
          integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <style>
        #branch-map { height: 340px; border-radius: .5rem; z-index: 0; }
        .map-picker-search { display:flex; gap:.5rem; margin-bottom:.5rem; }
        .map-picker-hint { font-size:.8rem; color:#6b7280; margin-top:.35rem; }
    </style>
@endpush

<div class="form-group col-md-12" element="div">
    <label>Pilih Lokasi di Peta</label>
    <div class="map-picker-search">
        <input type="text" id="branch-map-search" class="form-control"
               placeholder="Cari alamat / nama tempat, mis. Monas Jakarta…" autocomplete="off">
        <button type="button" id="branch-map-search-btn" class="btn btn-outline-secondary">
            <i class="la la-search"></i> Cari
        </button>
    </div>
    <div id="branch-map"></div>
    <p class="map-picker-hint">
        Klik peta atau geser penanda untuk mengatur titik geofence. Lingkaran biru = radius absensi.
        Koordinat & radius di bawah ikut terisi otomatis.
    </p>
</div>

@push('after_scripts')
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
            integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script>
    (function () {
        function initBranchMap() {
            var mapEl = document.getElementById('branch-map');
            if (!mapEl || mapEl.dataset.ready) return;
            mapEl.dataset.ready = '1';

            var latInput = document.querySelector('[name="lat"]');
            var lngInput = document.querySelector('[name="lng"]');
            var radiusInput = document.querySelector('[name="radius_meters"]');

            // Default centre: Indonesia (Jakarta) if no coordinates yet.
            var startLat = parseFloat(latInput && latInput.value) || -6.2087634;
            var startLng = parseFloat(lngInput && lngInput.value) || 106.845599;
            var hasInitial = !!(latInput && latInput.value && lngInput && lngInput.value);
            var startRadius = parseInt(radiusInput && radiusInput.value) || 100;

            var map = L.map('branch-map').setView([startLat, startLng], hasInitial ? 16 : 11);
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
                var q = document.getElementById('branch-map-search').value.trim();
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

            var btn = document.getElementById('branch-map-search-btn');
            if (btn) btn.addEventListener('click', doSearch);
            var searchInput = document.getElementById('branch-map-search');
            if (searchInput) searchInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') { e.preventDefault(); doSearch(); }
            });

            // Leaflet needs a size recalculation once the container is visible.
            setTimeout(function () { map.invalidateSize(); }, 200);
        }

        if (document.readyState !== 'loading') initBranchMap();
        else document.addEventListener('DOMContentLoaded', initBranchMap);
    })();
    </script>
@endpush
