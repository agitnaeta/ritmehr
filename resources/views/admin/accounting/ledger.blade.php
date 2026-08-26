@extends(backpack_view('blank'))

@section('header')
    <section class="container-fluid">
        <h2>Buku Besar <small class="text-muted">mutasi & saldo per akun</small></h2>
    </section>
@endsection

@section('content')
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-6">
                <label class="form-label">Akun</label>
                <select name="account_id" class="form-control" onchange="this.form.submit()">
                    @foreach($accounts as $a)
                        <option value="{{ $a->id }}" @selected($account && $account->id == $a->id)>
                            ({{ $a->code }}) {{ $a->name }} — {{ $a->typeLabel() }}
                        </option>
                    @endforeach
                </select>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        @if($account)
            <div class="d-flex justify-content-between mb-3">
                <h5 class="mb-0">({{ $account->code }}) {{ $account->name }}</h5>
                <h5 class="mb-0">Saldo Akhir:
                    <span class="{{ $endBalance >= 0 ? 'text-success' : 'text-danger' }}">
                        {{ money($endBalance) }}
                    </span>
                </h5>
            </div>
            <table class="table table-sm" id="ledgerTable">
                <thead>
                    <tr><th>Tanggal</th><th>Deskripsi</th>
                        <th class="text-end">Debit</th><th class="text-end">Kredit</th>
                        <th class="text-end">Saldo</th></tr>
                </thead>
                <tbody>
                    @forelse($rows as $r)
                        <tr>
                            <td>{{ $r['date'] ? \Carbon\Carbon::parse($r['date'])->format('d/m/Y') : '' }}</td>
                            <td>{{ $r['description'] }}</td>
                            <td class="text-end">{{ $r['debit'] > 0 ? money($r['debit']) : '' }}</td>
                            <td class="text-end">{{ $r['credit'] > 0 ? money($r['credit']) : '' }}</td>
                            <td class="text-end">{{ money($r['balance']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted">Belum ada mutasi untuk akun ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        @else
            <p class="text-muted">Belum ada akun. Buat dulu di menu Daftar Akun.</p>
        @endif
    </div>
</div>
@endsection
