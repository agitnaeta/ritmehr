@extends(backpack_view('blank'))

@section('header')
    <section class="container-fluid">
        <h2>Neraca <small class="text-muted">Aset = Kewajiban + Ekuitas</small></h2>
    </section>
@endsection

@section('content')
<div class="row" id="balanceSheet">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><strong>Aset</strong></div>
            <div class="card-body">
                <table class="table table-sm">
                    @forelse($assets as $r)
                        <tr><td>({{ $r['code'] }}) {{ $r['name'] }}</td>
                            <td class="text-end">{{ money($r['amount']) }}</td></tr>
                    @empty
                        <tr><td colspan="2" class="text-muted">—</td></tr>
                    @endforelse
                    <tr class="table-light fw-bold"><td>Total Aset</td>
                        <td class="text-end" id="totalAssets">{{ money($totalAssets) }}</td></tr>
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><strong>Kewajiban</strong></div>
            <div class="card-body">
                <table class="table table-sm">
                    @forelse($liabilities as $r)
                        <tr><td>({{ $r['code'] }}) {{ $r['name'] }}</td>
                            <td class="text-end">{{ money($r['amount']) }}</td></tr>
                    @empty
                        <tr><td colspan="2" class="text-muted">—</td></tr>
                    @endforelse
                    <tr class="table-light fw-bold"><td>Total Kewajiban</td>
                        <td class="text-end">{{ money($totalLiabilities) }}</td></tr>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><strong>Ekuitas</strong></div>
            <div class="card-body">
                <table class="table table-sm">
                    @foreach($equity as $r)
                        <tr><td>({{ $r['code'] }}) {{ $r['name'] }}</td>
                            <td class="text-end">{{ money($r['amount']) }}</td></tr>
                    @endforeach
                    <tr><td>Laba/Rugi Berjalan</td>
                        <td class="text-end">{{ money($retainedEarnings) }}</td></tr>
                    <tr class="table-light fw-bold"><td>Total Ekuitas</td>
                        <td class="text-end">{{ money($totalEquityWithEarnings) }}</td></tr>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Total Kewajiban + Ekuitas</h5>
        <div>
            <h4 class="mb-0 d-inline" id="totalLiabEquity">{{ money($totalLiabEquity) }}</h4>
            @if(round($totalAssets - $totalLiabEquity, 2) == 0)
                <span class="badge bg-success" id="balanceBadge">SEIMBANG ✓</span>
            @else
                <span class="badge bg-danger" id="balanceBadge">TIDAK SEIMBANG (selisih {{ money(abs($totalAssets - $totalLiabEquity)) }})</span>
            @endif
        </div>
    </div>
</div>
@endsection
