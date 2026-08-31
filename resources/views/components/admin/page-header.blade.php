{{--
  Komponen header 2-baris untuk halaman admin NON-CRUD (Pelatihan, Notifikasi,
  Dokumen Karyawan, dll). Menyamakan tampilan dengan pola header CRUD Backpack
  (resources/views/vendor/backpack/crud/list.blade.php) — reuse token CSS `.um-*`
  di resources/css/admin.css. Lihat RV5-01-C.

  Props:
    - $breadcrumb : array [label => url|false]  (item terakhir url=false = aktif)
    - $heading    : string
    - $subheading : string|null

  Slots:
    - actions : tombol aksi utama (baris 1, kanan)
    - tools   : tab / filter / search (baris 2, kanan)

  Contoh:
    <x-admin.page-header
        :breadcrumb="['Admin' => backpack_url('dashboard'), 'Pelatihan' => false]"
        heading="Pelatihan"
        subheading="Kelola materi & latihan untuk karyawan">
        <x-slot:actions>
            <a href="..." class="btn btn-success">Buat Pelatihan</a>
        </x-slot:actions>
        <x-slot:tools>
            <div class="btn-group">...</div>
        </x-slot:tools>
    </x-admin.page-header>
--}}
@props([
    'breadcrumb' => [],
    'heading' => '',
    'subheading' => null,
    'actions' => null,
    'tools' => null,
])

<div class="um-page-header container-fluid animated fadeIn d-print-none" bp-section="page-header">

    {{-- Baris 1: breadcrumb (kiri) + tombol aksi (kanan) --}}
    <div class="um-header-top d-flex align-items-center flex-wrap mb-3">
        @if (!empty($breadcrumb))
            <nav aria-label="breadcrumb" class="d-none d-lg-block">
                <ol class="breadcrumb bg-transparent p-0 m-0">
                    @foreach ($breadcrumb as $label => $link)
                        @if ($link)
                            <li class="breadcrumb-item text-capitalize"><a href="{{ $link }}">{{ $label }}</a></li>
                        @else
                            <li class="breadcrumb-item text-capitalize active" aria-current="page">{{ $label }}</li>
                        @endif
                    @endforeach
                </ol>
            </nav>
        @endif

        @if ($actions)
            <div class="um-header-actions ms-auto d-flex align-items-center gap-2" bp-section="page-header-actions">
                {{ $actions }}
            </div>
        @endif
    </div>

    {{-- Baris 2: judul + subjudul (kiri) + tools/tab/filter (kanan) --}}
    <div class="um-header-bottom d-flex align-items-end flex-wrap gap-3 mb-2">
        <div class="um-header-title">
            <h1 class="text-capitalize mb-0" bp-section="page-heading">{{ $heading }}</h1>
            @if (!is_null($subheading) && $subheading !== '')
                <p class="mb-0 text-muted" bp-section="page-subheading">{{ $subheading }}</p>
            @endif
        </div>

        @if ($tools)
            <div class="um-header-tools ms-auto d-flex align-items-end flex-wrap gap-2">
                {{ $tools }}
            </div>
        @endif
    </div>

</div>
