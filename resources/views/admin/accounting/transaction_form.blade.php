@extends(backpack_view('blank'))

@php
    $isEdit = (bool) $entry;
    $labels = [
        'expense'  => ['title' => 'Pengeluaran', 'color' => 'danger'],
        'income'   => ['title' => 'Pemasukan', 'color' => 'success'],
        'transfer' => ['title' => 'Transfer', 'color' => 'info'],
    ];
    $meta = $labels[$kind] ?? $labels['expense'];
@endphp

@section('header')
    <section class="container-fluid">
        <h2>{{ $isEdit ? 'Edit' : 'Catat' }} {{ $meta['title'] }}
            <small><a href="{{ $isEdit ? backpack_url('accounting/journal') : backpack_url('accounting/transaksi') }}" class="font-sm">
                <i class="la la-angle-double-left"></i> Kembali</a></small>
        </h2>
    </section>
@endsection

@section('content')
@if($errors->any())
    <div class="alert alert-danger">
        @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
    </div>
@endif

<div class="row">
    <div class="col-lg-7">
        <div class="card border-{{ $meta['color'] }}">
            <div class="card-body">
                <form method="POST"
                      action="{{ $isEdit ? backpack_url('accounting/journal/'.$entry->id) : backpack_url('accounting/journal') }}"
                      enctype="multipart/form-data">
                    @csrf
                    @if($isEdit) @method('PUT') @endif
                    <input type="hidden" name="kind" value="{{ $kind }}">

                    <div class="mb-3">
                        <label class="form-label">Tanggal <span class="text-danger">*</span></label>
                        <input type="date" name="date" class="form-control" required
                               value="{{ old('date', $isEdit ? $entry->date->toDateString() : now()->toDateString()) }}">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Jumlah (Rp) <span class="text-danger">*</span></label>
                        <input type="number" name="amount" step="0.01" min="0.01" class="form-control form-control-lg"
                               placeholder="0" required
                               value="{{ old('amount', $prefill['amount']) }}">
                    </div>

                    @if($kind === 'expense')
                        <div class="mb-3">
                            <label class="form-label">Bayar dari <span class="text-danger">*</span></label>
                            <select name="cash_account_id" class="form-control" required>
                                <option value="">— pilih kas/bank —</option>
                                @foreach($cashAccounts as $a)
                                    <option value="{{ $a->id }}" @selected(old('cash_account_id', $prefill['cash_account_id']) == $a->id)>{{ $a->cashLabel() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Untuk (kategori) <span class="text-danger">*</span></label>
                            <select name="category_account_id" class="form-control" required>
                                <option value="">— pilih kategori beban —</option>
                                @foreach($expenseAccounts as $a)
                                    <option value="{{ $a->id }}" @selected(old('category_account_id', $prefill['category_account_id']) == $a->id)>{{ $a->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @elseif($kind === 'income')
                        <div class="mb-3">
                            <label class="form-label">Masuk ke <span class="text-danger">*</span></label>
                            <select name="cash_account_id" class="form-control" required>
                                <option value="">— pilih kas/bank —</option>
                                @foreach($cashAccounts as $a)
                                    <option value="{{ $a->id }}" @selected(old('cash_account_id', $prefill['cash_account_id']) == $a->id)>{{ $a->cashLabel() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Sumber (kategori) <span class="text-danger">*</span></label>
                            <select name="category_account_id" class="form-control" required>
                                <option value="">— pilih kategori pendapatan —</option>
                                @foreach($incomeAccounts as $a)
                                    <option value="{{ $a->id }}" @selected(old('category_account_id', $prefill['category_account_id']) == $a->id)>{{ $a->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @elseif($kind === 'transfer')
                        <div class="mb-3">
                            <label class="form-label">Dari <span class="text-danger">*</span></label>
                            <select name="from_account_id" class="form-control" required>
                                <option value="">— pilih kas/bank asal —</option>
                                @foreach($cashAccounts as $a)
                                    <option value="{{ $a->id }}" @selected(old('from_account_id', $prefill['from_account_id']) == $a->id)>{{ $a->cashLabel() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Ke <span class="text-danger">*</span></label>
                            <select name="to_account_id" class="form-control" required>
                                <option value="">— pilih kas/bank tujuan —</option>
                                @foreach($cashAccounts as $a)
                                    <option value="{{ $a->id }}" @selected(old('to_account_id', $prefill['to_account_id']) == $a->id)>{{ $a->cashLabel() }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div class="mb-3">
                        <label class="form-label">Catatan <span class="text-danger">*</span></label>
                        <input type="text" name="description" class="form-control" required maxlength="255"
                               placeholder="mis. Listrik kantor Agustus"
                               value="{{ old('description', $entry->description ?? '') }}">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Lampiran bukti (nota/kwitansi)</label>
                        @if($isEdit && $entry->hasAttachment())
                            <div class="mb-1">
                                <a href="{{ backpack_url('accounting/journal/'.$entry->id.'/attachment') }}" target="_blank">
                                    <i class="la la-paperclip"></i> {{ $entry->attachment_name }}
                                </a>
                                <label class="ms-3 small text-danger">
                                    <input type="checkbox" name="remove_attachment" value="1"> hapus lampiran
                                </label>
                            </div>
                        @endif
                        <input type="file" name="attachment" class="form-control" accept=".jpg,.jpeg,.png,.pdf">
                        <small class="form-text text-muted">Opsional. JPG/PNG/PDF, maks 10 MB.</small>
                    </div>

                    <div class="d-flex gap-2 border-top pt-3 mt-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="la la-save"></i> Simpan
                        </button>
                        <a href="{{ backpack_url('accounting/journal') }}" class="btn btn-outline-secondary">Batal</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card bg-light">
            <div class="card-body">
                <h6><i class="la la-info-circle"></i> Info</h6>
                @if($kind === 'expense')
                    <p class="mb-0 small">Catat uang yang <strong>keluar</strong> dari kas/bank untuk suatu keperluan. Sistem otomatis mencatatnya ke pembukuan dengan benar.</p>
                @elseif($kind === 'income')
                    <p class="mb-0 small">Catat uang yang <strong>masuk</strong> ke kas/bank. Sistem otomatis membukukannya.</p>
                @else
                    <p class="mb-0 small">Pindahkan dana antar kas/bank (mis. tarik tunai dari bank ke kas). Saldo kedua akun menyesuaikan otomatis.</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
