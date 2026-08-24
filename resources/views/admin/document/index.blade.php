@extends(backpack_view('blank'))

@section('header')
    <section class="container-fluid"><h2>Dokumen Karyawan</h2></section>
@endsection

@section('content')
<div class="card">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-auto">
                    <label class="form-label mb-0">Karyawan</label>
                    <select name="user_id" class="form-control form-control-sm">
                        <option value="">Semua</option>
                        @foreach($users as $u)
                            <option value="{{ $u->id }}" @selected(($filters['user_id'] ?? null) == $u->id)>{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label mb-0">Jenis</label>
                    <select name="document_type_id" class="form-control form-control-sm">
                        <option value="">Semua</option>
                        @foreach($types as $t)
                            <option value="{{ $t->id }}" @selected(($filters['document_type_id'] ?? null) == $t->id)>{{ $t->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-check-label small">
                        <input type="checkbox" name="expiring" value="1" @checked(!empty($filters['expiring']))>
                        Akan kedaluwarsa (30 hari)
                    </label><br>
                    <label class="form-check-label small">
                        <input type="checkbox" name="expired" value="1" @checked(!empty($filters['expired']))>
                        Sudah kedaluwarsa
                    </label>
                </div>
                <div class="col-auto"><button class="btn btn-sm btn-primary">Filter</button></div>
            </form>

            <div>
                <a href="{{ backpack_url('employee-document/completeness') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="la la-clipboard-check"></i> Kelengkapan
                </a>
                <a href="{{ backpack_url('employee-document/create') }}" class="btn btn-sm btn-primary">
                    <i class="la la-upload"></i> Unggah Dokumen
                </a>
            </div>
        </div>
    </div>

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
                                    @if($doc->isExpired())
                                        <span class="badge bg-danger">Kedaluwarsa</span>
                                    @elseif($doc->daysUntilExpiry() <= 30)
                                        <span class="badge bg-warning text-dark">{{ $doc->daysUntilExpiry() }} hari</span>
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
