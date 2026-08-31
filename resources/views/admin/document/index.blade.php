@extends(backpack_view('blank'))

@section('header')
    <x-admin.page-header
        :breadcrumb="['Admin' => backpack_url('dashboard'), 'Dokumen Karyawan' => false]"
        heading="Dokumen Karyawan"
        subheading="Arsip berkas & masa berlaku dokumen">

        <x-slot:actions>
            <a href="{{ backpack_url('employee-document/completeness') }}" class="btn btn-outline-secondary">
                <i class="la la-clipboard-check"></i> Kelengkapan
            </a>
            <a href="{{ backpack_url('employee-document/create') }}" class="btn btn-primary">
                <i class="la la-upload"></i> Unggah Dokumen
            </a>
        </x-slot:actions>

        <x-slot:tools>
            <form method="GET" class="um-header-tools-form d-flex align-items-end flex-wrap gap-2">
                <div>
                    <label class="form-label mb-0 small text-muted">Karyawan</label>
                    <select name="user_id" class="form-select">
                        <option value="">Semua</option>
                        @foreach($users as $u)
                            <option value="{{ $u->id }}" @selected(($filters['user_id'] ?? null) == $u->id)>{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label mb-0 small text-muted">Jenis</label>
                    <select name="document_type_id" class="form-select">
                        <option value="">Semua</option>
                        @foreach($types as $t)
                            <option value="{{ $t->id }}" @selected(($filters['document_type_id'] ?? null) == $t->id)>{{ $t->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="d-flex flex-column gap-1">
                    <label class="form-check m-0">
                        <input type="checkbox" class="form-check-input" name="expiring" value="1" @checked(!empty($filters['expiring']))>
                        <span class="small">Akan kedaluwarsa (30 hari)</span>
                    </label>
                    <label class="form-check m-0">
                        <input type="checkbox" class="form-check-input" name="expired" value="1" @checked(!empty($filters['expired']))>
                        <span class="small">Sudah kedaluwarsa</span>
                    </label>
                </div>
                <div><button class="btn btn-primary">Filter</button></div>
            </form>
        </x-slot:tools>
    </x-admin.page-header>
@endsection

@section('content')
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>Karyawan</th>
                        <th>Jenis</th>
                        <th>Nomor</th>
                        <th>Berkas</th>
                        <th>Terbit</th>
                        <th>Kedaluwarsa</th>
                        <th>Diunggah oleh</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($documents as $doc)
                        <tr>
                            <td>{{ $doc->user?->name ?? '—' }}</td>
                            <td>{{ $doc->documentType?->name ?? '—' }}</td>
                            <td class="small">{{ $doc->document_number ?: '—' }}</td>
                            <td class="small">
                                {{ \Illuminate\Support\Str::limit($doc->file_name, 30) }}
                                <span class="text-muted">({{ $doc->humanFileSize() }})</span>
                            </td>
                            <td>{{ $doc->issued_date?->format('d/m/Y') ?? '—' }}</td>
                            <td>
                                @if($doc->expiry_date)
                                    {{ $doc->expiry_date->format('d/m/Y') }}
                                    @php($dLeft = $doc->daysUntilExpiry())
                                    @if($doc->isExpired())
                                        <span class="badge bg-danger">Kedaluwarsa</span>
                                    @elseif($dLeft <= 7)
                                        <span class="badge bg-danger">{{ $dLeft }} hari lagi</span>
                                    @elseif($dLeft <= 30)
                                        <span class="badge bg-warning text-dark">{{ $dLeft }} hari</span>
                                    @endif
                                @else
                                    —
                                @endif
                            </td>
                            <td class="small">{{ $doc->uploader?->name ?? '—' }}</td>
                            <td class="text-end text-nowrap">
                                <a href="{{ backpack_url('employee-document/' . $doc->id . '/download') }}"
                                   class="btn btn-sm btn-link"><i class="la la-download"></i></a>
                                <form method="POST" class="d-inline"
                                      action="{{ backpack_url('employee-document/' . $doc->id . '/delete') }}"
                                      onsubmit="return confirm('Hapus dokumen ini beserta berkasnya?')">
                                    @csrf
                                    <button class="btn btn-sm btn-link text-danger"><i class="la la-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted p-4">Belum ada dokumen.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mt-3">{{ $documents->links() }}</div>
@endsection
