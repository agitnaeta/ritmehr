@extends(backpack_view('blank'))

@section('header')
    <section class="container-fluid">
        <h2>Jurnal <small class="text-muted">semua transaksi buku besar</small></h2>
    </section>
@endsection

@section('content')
@php($canEdit = backpack_user()?->can('accounting.edit'))
@if($errors->any())
    <div class="alert alert-danger">
        @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
    </div>
@endif

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Dari Tanggal</label>
                <input type="date" name="from" class="form-control" value="{{ $filters['from'] ?? '' }}">
            </div>
            <div class="col-md-3">
                <label class="form-label">Sampai Tanggal</label>
                <input type="date" name="to" class="form-control" value="{{ $filters['to'] ?? '' }}">
            </div>
            <div class="col-md-3">
                <label class="form-label">Akun</label>
                <select name="account_id" class="form-control">
                    <option value="">— semua akun —</option>
                    @foreach($accounts as $a)
                        <option value="{{ $a->id }}" @selected(($filters['account_id'] ?? '') == $a->id)>
                            ({{ $a->code }}) {{ $a->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <button class="btn btn-primary" type="submit"><i class="la la-filter"></i> Filter</button>
                <a href="{{ backpack_url('accounting/journal') }}" class="btn btn-link">Reset</a>
            </div>
        </form>
    </div>
</div>

@if($canEdit)
<div class="mb-3">
    <a href="{{ backpack_url('accounting/transaksi') }}" class="btn btn-success">
        <i class="la la-plus"></i> Catat Transaksi
    </a>
</div>
@endif

<div class="card">
    <div class="card-body">
        <table class="table table-sm" id="journalTable">
            <thead>
                <tr>
                    <th>Tanggal</th><th>Ref/Jenis</th><th>Deskripsi</th>
                    <th>Akun</th><th class="text-end">Debit</th><th class="text-end">Kredit</th>
                    @if($canEdit)<th>Aksi</th>@endif
                </tr>
            </thead>
            <tbody>
                @forelse($entries as $entry)
                    @php($rows = $entry->lines->count())
                    @foreach($entry->lines as $i => $line)
                        <tr>
                            @if($i === 0)
                                <td rowspan="{{ $rows }}">{{ $entry->date->format('d/m/Y') }}</td>
                                <td rowspan="{{ $rows }}">
                                    @if($entry->isManual())
                                        @if($entry->isReversal())
                                            <span class="badge bg-warning text-dark">Pembalik</span>
                                        @elseif($entry->kind === 'expense')
                                            <span class="badge bg-danger">Pengeluaran</span>
                                        @elseif($entry->kind === 'income')
                                            <span class="badge bg-success">Pemasukan</span>
                                        @elseif($entry->kind === 'transfer')
                                            <span class="badge bg-info text-dark">Transfer</span>
                                        @else
                                            <span class="badge bg-secondary">Manual</span>
                                        @endif
                                    @else
                                        <span class="badge bg-secondary" title="Otomatis dari {{ $entry->source }}">
                                            <i class="la la-lock"></i> Auto
                                        </span>
                                        <div><code class="small">{{ $entry->reference }}</code></div>
                                    @endif
                                    @if($entry->hasAttachment())
                                        <div><a href="{{ backpack_url('accounting/journal/'.$entry->id.'/attachment') }}"
                                                target="_blank" title="{{ $entry->attachment_name }}">
                                            <i class="la la-paperclip"></i> bukti</a></div>
                                    @endif
                                    @if($entry->isReversed())
                                        <span class="badge bg-dark">sudah dibalik</span>
                                    @endif
                                </td>
                                <td rowspan="{{ $rows }}">{{ $entry->description }}</td>
                            @endif
                            <td>({{ $line->account->code }}) {{ $line->account->name }}</td>
                            <td class="text-end">{{ $line->debit > 0 ? money($line->debit) : '' }}</td>
                            <td class="text-end">{{ $line->credit > 0 ? money($line->credit) : '' }}</td>
                            @if($canEdit)
                                @if($i === 0)
                                    <td rowspan="{{ $rows }}" data-entry-actions="{{ $entry->id }}">
                                        @if($entry->isManual())
                                            <a href="{{ backpack_url('accounting/journal/'.$entry->id.'/edit') }}"
                                               class="btn btn-sm btn-outline-primary" title="Edit"><i class="la la-edit"></i></a>
                                            <form method="POST" action="{{ backpack_url('accounting/journal/'.$entry->id) }}"
                                                  class="d-inline" onsubmit="return confirm('Hapus jurnal ini?')">
                                                @csrf @method('DELETE')
                                                <button class="btn btn-sm btn-outline-danger" title="Hapus"><i class="la la-trash"></i></button>
                                            </form>
                                        @else
                                            @unless($entry->isReversed())
                                                <form method="POST" action="{{ backpack_url('accounting/journal/'.$entry->id.'/reverse') }}"
                                                      class="d-inline" onsubmit="return confirm('Buat jurnal pembalik untuk koreksi?')">
                                                    @csrf
                                                    <button class="btn btn-sm btn-outline-warning" title="Jurnal Pembalik">
                                                        <i class="la la-undo"></i> Balik
                                                    </button>
                                                </form>
                                            @else
                                                <span class="text-muted small">—</span>
                                            @endunless
                                        @endif
                                    </td>
                                @endif
                            @endif
                        </tr>
                    @endforeach
                @empty
                    <tr><td colspan="7" class="text-center text-muted">Belum ada transaksi jurnal.</td></tr>
                @endforelse
            </tbody>
        </table>
        {{ $entries->links() }}
    </div>
</div>
@endsection
