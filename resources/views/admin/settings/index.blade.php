@extends(backpack_view('blank'))

@php($first = array_key_first($groups))

@section('header')
    <section class="container-fluid">
        <h2>Pengaturan Sistem
            <small class="text-muted">konfigurasi platform &amp; integrasi pihak ketiga</small>
        </h2>
    </section>
@endsection

@section('content')
<div class="row">
    <div class="col-lg-9">
        {{-- Status koneksi integrasi --}}
        <div class="card mb-3">
            <div class="card-header"><strong>Status Integrasi</strong></div>
            <div class="card-body">
                <div class="row">
                    @foreach(['acc' => 'Akuntansi', 'whatsapp' => 'WhatsApp', 'storage' => 'Penyimpanan'] as $k => $label)
                        <div class="col-md-4 mb-2">
                            <div class="d-flex align-items-center">
                                <span class="badge {{ $status[$k]['ok'] ? 'bg-success' : 'bg-secondary' }} me-2"
                                      id="status-{{ $k }}">
                                    {{ $status[$k]['ok'] ? 'OK' : 'OFF' }}
                                </span>
                                <div>
                                    <div><strong>{{ $label }}</strong></div>
                                    <small class="text-muted">{{ $status[$k]['label'] }}</small>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- M03 — send a real test WhatsApp so admin can verify the gateway. --}}
                <div class="border-top pt-3 mt-2">
                    <form method="POST" action="{{ backpack_url('settings/test-whatsapp') }}" class="row g-2 align-items-end">
                        @csrf
                        <div class="col-auto">
                            <label class="form-label mb-0 small">Tes kirim WhatsApp ke nomor</label>
                            <input type="text" name="phone" class="form-control form-control-sm"
                                   placeholder="08xxxxxxxxxx" style="min-width: 200px;" required>
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-sm btn-outline-success" id="btnTestWa">
                                <i class="la la-paper-plane"></i> Kirim Tes
                            </button>
                        </div>
                        <div class="col-12">
                            <small class="text-muted">Mengirim satu pesan uji memakai token & pengaturan yang tersimpan saat ini.</small>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <form method="POST" action="{{ backpack_url('settings') }}" id="settingsForm">
            @csrf

            <div class="card">
                <div class="card-header p-0">
                    <ul class="nav nav-tabs" role="tablist">
                        @foreach($groups as $gkey => $glabel)
                            <li class="nav-item" role="presentation">
                                <button class="nav-link @if($gkey === $first) active @endif"
                                        id="tab-{{ $gkey }}" data-bs-toggle="tab"
                                        data-bs-target="#pane-{{ $gkey }}" type="button" role="tab">
                                    {{ $glabel }}
                                </button>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div class="card-body">
                    <div class="tab-content">
                        @foreach($groups as $gkey => $glabel)
                            <div class="tab-pane fade @if($gkey === $first) show active @endif"
                                 id="pane-{{ $gkey }}" role="tabpanel">
                                @if($gkey === 'lokasi')
                                    @include('admin.settings.geofence_map')
                                @endif
                                @foreach($defs as $key => $def)
                                    @continue(($def['group'] ?? 'umum') !== $gkey)
                                    @php($type = $def['type'] ?? 'string')
                                    <div class="mb-3" data-setting-row="{{ $key }}">
                                        @if($type === 'bool')
                                            <div class="form-check form-switch">
                                                <input type="checkbox" class="form-check-input" role="switch"
                                                       id="fld-{{ $key }}" name="{{ $key }}" value="1"
                                                       @checked($values[$key])>
                                                <label class="form-check-label" for="fld-{{ $key }}">{{ $def['label'] }}</label>
                                            </div>
                                        @elseif($type === 'select')
                                            <label class="form-label" for="fld-{{ $key }}">{{ $def['label'] }}</label>
                                            <select class="form-control" id="fld-{{ $key }}" name="{{ $key }}">
                                                @foreach(($def['options'] ?? []) as $ov => $ol)
                                                    <option value="{{ $ov }}" @selected($values[$key] == $ov)>{{ $ol }}</option>
                                                @endforeach
                                            </select>
                                        @elseif($type === 'password')
                                            <label class="form-label" for="fld-{{ $key }}">{{ $def['label'] }}</label>
                                            <input type="password" class="form-control" id="fld-{{ $key }}"
                                                   name="{{ $key }}" value="{{ $values[$key] }}"
                                                   autocomplete="new-password"
                                                   placeholder="{{ $values[$key] === '********' ? 'tersimpan — kosongkan untuk tidak mengubah' : '' }}">
                                        @else
                                            <label class="form-label" for="fld-{{ $key }}">{{ $def['label'] }}</label>
                                            <input type="text" class="form-control" id="fld-{{ $key }}"
                                                   name="{{ $key }}" value="{{ $values[$key] }}">
                                        @endif

                                        @if(!empty($def['help']))
                                            <small class="form-text text-muted d-block">{{ $def['help'] }}</small>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="card-footer d-flex gap-2 align-items-center">
                    <button type="submit" class="btn btn-primary" id="saveSettings">
                        <i class="la la-save"></i> Simpan Pengaturan
                    </button>
                    <button type="submit" class="btn btn-outline-secondary" id="btnTestStorage"
                            formaction="{{ backpack_url('settings/test-storage') }}" formmethod="POST">
                        <i class="la la-plug"></i> Tes Koneksi Penyimpanan
                    </button>
                    <small class="text-muted">Simpan dulu sebelum tes agar kredensial terbaru dipakai.</small>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    // M16 — show only the credential fields for the selected provider.
    const provider = document.getElementById('fld-storage_provider');
    if (!provider) return;
    const groups = {
        s3: ['storage_s3_key','storage_s3_secret','storage_s3_region','storage_s3_bucket','storage_s3_endpoint','storage_s3_path_style'],
        google: ['storage_gdrive_client_id','storage_gdrive_client_secret','storage_gdrive_refresh_token','storage_gdrive_folder'],
        webdav: ['storage_webdav_base_uri','storage_webdav_username','storage_webdav_password','storage_webdav_prefix'],
    };
    const all = [...groups.s3, ...groups.google, ...groups.webdav];
    function sync() {
        const active = groups[provider.value] || [];
        all.forEach(k => {
            const row = document.querySelector(`[data-setting-row="${k}"]`);
            if (row) row.style.display = active.includes(k) ? '' : 'none';
        });
    }
    provider.addEventListener('change', sync);
    sync();
})();
</script>
@endsection
