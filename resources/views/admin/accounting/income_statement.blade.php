@extends(backpack_view('blank'))

@section('header')
    <section class="container-fluid">
        <h2>Laba Rugi <small class="text-muted">pendapatan &minus; beban</small></h2>
    </section>
@endsection

@section('content')
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
                <button class="btn btn-primary" type="submit"><i class="la la-filter"></i> Filter</button>
                <a href="{{ backpack_url('accounting/income-statement') }}" class="btn btn-link">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body" id="incomeStatement">
        <h6 class="text-success">Pendapatan</h6>
        <table class="table table-sm">
            @forelse($income as $r)
                <tr><td>({{ $r['code'] }}) {{ $r['name'] }}</td>
                    <td class="text-end">{{ money($r['amount']) }}</td></tr>
            @empty
                <tr><td colspan="2" class="text-muted">Belum ada pendapatan.</td></tr>
            @endforelse
            <tr class="table-light fw-bold"><td>Total Pendapatan</td>
                <td class="text-end" id="totalIncome">{{ money($totalIncome) }}</td></tr>
        </table>

        <h6 class="text-danger mt-4">Beban</h6>
        <table class="table table-sm">
            @forelse($expense as $r)
                <tr><td>({{ $r['code'] }}) {{ $r['name'] }}</td>
                    <td class="text-end">{{ money($r['amount']) }}</td></tr>
            @empty
                <tr><td colspan="2" class="text-muted">Belum ada beban.</td></tr>
            @endforelse
            <tr class="table-light fw-bold"><td>Total Beban</td>
                <td class="text-end" id="totalExpense">{{ money($totalExpense) }}</td></tr>
        </table>

        <div class="d-flex justify-content-between align-items-center border-top pt-3 mt-3">
            <h5 class="mb-0">Laba / Rugi Bersih</h5>
            <h4 class="mb-0 {{ $netProfit >= 0 ? 'text-success' : 'text-danger' }}" id="netProfit">
                {{ money($netProfit) }}
            </h4>
        </div>
    </div>
</div>
@endsection
