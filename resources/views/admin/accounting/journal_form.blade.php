@extends(backpack_view('blank'))

@php($isEdit = (bool) $entry)

@section('header')
    <section class="container-fluid">
        <h2>{{ $isEdit ? 'Edit Jurnal Manual' : 'Buat Jurnal Manual' }}
            <small><a href="{{ backpack_url('accounting/journal') }}" class="font-sm">
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
    <div class="col-lg-9">
        <div class="card">
            <div class="card-body">
                <form method="POST"
                      action="{{ $isEdit ? backpack_url('accounting/journal/'.$entry->id) : backpack_url('accounting/journal') }}">
                    @csrf
                    @if($isEdit) @method('PUT') @endif

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Tanggal <span class="text-danger">*</span></label>
                            <input type="date" name="date" class="form-control" required
                                   value="{{ old('date', $isEdit ? $entry->date->toDateString() : now()->toDateString()) }}">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Deskripsi <span class="text-danger">*</span></label>
                            <input type="text" name="description" class="form-control" required maxlength="255"
                                   placeholder="mis. Pembayaran listrik kantor Agustus"
                                   value="{{ old('description', $entry->description ?? '') }}">
                        </div>
                    </div>

                    <table class="table table-sm" id="linesTable">
                        <thead>
                            <tr><th style="width:45%">Akun</th><th class="text-end">Debit</th>
                                <th class="text-end">Kredit</th><th></th></tr>
                        </thead>
                        <tbody id="linesBody">
                            {{-- rows injected by JS --}}
                        </tbody>
                        <tfoot>
                            <tr class="table-light fw-bold">
                                <td class="text-end">Total</td>
                                <td class="text-end" id="sumDebit">{{ money(0) }}</td>
                                <td class="text-end" id="sumCredit">{{ money(0) }}</td>
                                <td><span id="balanceHint" class="badge bg-secondary">—</span></td>
                            </tr>
                        </tfoot>
                    </table>

                    <button type="button" class="btn btn-sm btn-outline-secondary mb-3" id="addLine">
                        <i class="la la-plus"></i> Tambah Baris
                    </button>

                    <div class="d-flex gap-2 border-top pt-3 mt-3">
                        <button type="submit" class="btn btn-primary" id="saveJournal">
                            <i class="la la-save"></i> Simpan Jurnal
                        </button>
                        <a href="{{ backpack_url('accounting/journal') }}" class="btn btn-outline-secondary">Batal</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
const ACCOUNTS = @json($accountsJson);
const PRESET = @json(old('lines') ?: $presetLines);

function optionsHtml(selected) {
    let h = '<option value="">— pilih akun —</option>';
    for (const a of ACCOUNTS) {
        h += `<option value="${a.id}" ${String(a.id) === String(selected) ? 'selected' : ''}>${a.label}</option>`;
    }
    return h;
}

function addRow(preset) {
    const tb = document.getElementById('linesBody');
    const idx = tb.children.length;
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td><select name="lines[${idx}][account_id]" class="form-control form-control-sm acc">${optionsHtml(preset?.account_id)}</select></td>
        <td><input type="number" step="0.01" min="0" name="lines[${idx}][debit]" class="form-control form-control-sm text-end debit" value="${preset?.debit || ''}"></td>
        <td><input type="number" step="0.01" min="0" name="lines[${idx}][credit]" class="form-control form-control-sm text-end credit" value="${preset?.credit || ''}"></td>
        <td><button type="button" class="btn btn-sm btn-outline-danger rm"><i class="la la-times"></i></button></td>`;
    tb.appendChild(tr);
    tr.querySelector('.rm').onclick = () => { tr.remove(); recalc(); };
    tr.querySelectorAll('input').forEach(i => i.addEventListener('input', recalc));
    recalc();
}

function fmt(n) { return @json(app(\App\Services\CurrencyService::class)->symbol()) + ' ' + (n||0).toLocaleString('id-ID'); }

function recalc() {
    let d = 0, c = 0;
    document.querySelectorAll('#linesBody tr').forEach(tr => {
        d += parseFloat(tr.querySelector('.debit').value) || 0;
        c += parseFloat(tr.querySelector('.credit').value) || 0;
    });
    document.getElementById('sumDebit').innerText = fmt(d);
    document.getElementById('sumCredit').innerText = fmt(c);
    const hint = document.getElementById('balanceHint');
    const balanced = Math.round((d - c) * 100) === 0 && d > 0;
    hint.className = 'badge ' + (balanced ? 'bg-success' : 'bg-danger');
    hint.innerText = balanced ? 'SEIMBANG' : 'selisih ' + fmt(Math.abs(d - c));
    document.getElementById('saveJournal').disabled = !balanced;
}

document.getElementById('addLine').onclick = () => addRow(null);

// seed rows
if (PRESET && PRESET.length) { PRESET.forEach(addRow); }
else { addRow(null); addRow(null); }
</script>
@endsection
