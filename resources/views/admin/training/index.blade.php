@extends(backpack_view('blank'))

@php use App\Models\Training; @endphp

@section('header')
    <x-admin.page-header
        :breadcrumb="['Admin' => backpack_url('dashboard'), 'Pelatihan' => false]"
        heading="Pelatihan"
        subheading="Kelola materi & latihan untuk karyawan">

        @if($canEdit && ! $showArchived)
            <x-slot:actions>
                <a href="{{ backpack_url('training/create') }}" class="btn btn-success">
                    <i class="la la-plus"></i> Buat Pelatihan
                </a>
            </x-slot:actions>
        @endif

        <x-slot:tools>
            <div class="btn-group">
                <a href="{{ backpack_url('training') }}"
                   class="btn {{ ! $showArchived ? 'btn-primary' : 'btn-outline-secondary' }}">Aktif</a>
                <a href="{{ backpack_url('training') }}?archived=1"
                   class="btn {{ $showArchived ? 'btn-primary' : 'btn-outline-secondary' }}">Diarsipkan</a>
            </div>
        </x-slot:tools>
    </x-admin.page-header>
@endsection

@section('content')
<div class="card"><div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
            <tr>
                <th>Judul</th><th>Pelatih</th><th style="width:90px;">KKM</th>
                <th style="width:90px;">Materi</th><th style="width:90px;">Soal</th>
                <th style="width:100px;">Peserta</th><th style="width:110px;">Status</th><th style="width:90px;"></th>
            </tr>
        </thead>
        <tbody>
            @forelse($trainings as $t)
                <tr>
                    <td><div class="fw-semibold">{{ $t->title }}</div>
                        @if($t->category)<span class="text-muted small">{{ $t->category }}</span>@endif</td>
                    <td>{{ $t->trainer?->name ?? '—' }}</td>
                    <td>{{ $t->passing_score }}</td>
                    <td>{{ $t->materials_count }}</td>
                    <td>{{ $t->questions_count }}</td>
                    <td>{{ $t->enrollments_count }}</td>
                    <td>
                        @php $badge = ['draft'=>'bg-secondary','published'=>'bg-success','archived'=>'bg-dark'][$t->status] ?? 'bg-secondary'; @endphp
                        <span class="badge {{ $badge }}">{{ $t->statusLabel() }}</span>
                    </td>
                    <td class="text-end">
                        <a href="{{ backpack_url('training/'.$t->id.'/manage') }}" class="btn btn-sm btn-outline-primary">
                            {{ $canEdit ? 'Kelola' : 'Lihat' }}
                        </a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">
                    {{ $showArchived ? 'Tidak ada pelatihan diarsipkan.' : 'Belum ada pelatihan. Klik "Buat Pelatihan".' }}
                </td></tr>
            @endforelse
        </tbody>
    </table>
</div></div>
@endsection
