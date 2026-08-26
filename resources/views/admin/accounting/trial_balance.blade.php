@extends(backpack_view('blank'))

@section('header')
    <section class="container-fluid">
        <h2>Neraca Saldo <small class="text-muted">total debit vs kredit semua akun</small></h2>
    </section>
@endsection

@section('content')
<div class="card">
    <div class="card-body">
        <table class="table table-sm" id="trialBalanceTable">
            <thead>
                <tr><th>Kode</th><th>Akun</th>
                    <th class="text-end">Debit</th><th class="text-end">Kredit</th></tr>
            </thead>
            <tbody>
                @forelse($rows as $r)
                    <tr>
                        <td>{{ $r['code'] }}</td>
                        <td>{{ $r['name'] }}</td>
                        <td class="text-end">{{ money($r['debit']) }}</td>
                        <td class="text-end">{{ money($r['credit']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted">Belum ada transaksi.</td></tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr class="table-light fw-bold">
                    <td colspan="2">TOTAL</td>
                    <td class="text-end" id="totalDebit">{{ money($totalDebit) }}</td>
                    <td class="text-end" id="totalCredit">{{ money($totalCredit) }}</td>
                </tr>
                <tr>
                    <td colspan="4" class="text-center">
                        @if(round($totalDebit - $totalCredit, 2) == 0)
                            <span class="badge bg-success" id="balanceBadge">SEIMBANG ✓ (debit = kredit)</span>
                        @else
                            <span class="badge bg-danger" id="balanceBadge">TIDAK SEIMBANG — selisih {{ money(abs($totalDebit - $totalCredit)) }}</span>
                        @endif
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
@endsection
