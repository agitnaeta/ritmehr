@extends(backpack_view('blank'))

@section('header')
    <section class="container-fluid"><h2>Rekap Pajak Tahunan <small>{{ $year }}</small></h2></section>
@endsection

@section('content')
<div class="card">
    <div class="card-header">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label mb-0">Tahun</label>
                <input type="number" name="year" class="form-control form-control-sm"
                       value="{{ $year }}" min="2000" max="2100">
            </div>
            <div class="col-auto"><button class="btn btn-sm btn-primary">Tampilkan</button></div>
        </form>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>Karyawan</th>
                        <th>NPWP</th>
                        <th>PTKP</th>
                        <th>Departemen</th>
                        <th class="text-end">Bulan</th>
                        <th class="text-end">Bruto</th>
                        <th class="text-end">BPJS Karyawan</th>
                        <th class="text-end">PPh 21</th>
                        <th class="text-end">Netto</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td>{{ $row['user']?->name ?? '—' }}</td>
                            <td class="small">{{ $row['npwp'] ?: '—' }}</td>
                            <td>{{ $row['tax_status'] ?: 'TK/0' }}</td>
                            <td>{{ $row['department'] }}</td>
                            <td class="text-end">{{ $row['months'] }}</td>
                            <td class="text-end">@rupiah($row['gross'])</td>
                            <td class="text-end">@rupiah($row['bpjs_employee'])</td>
                            <td class="text-end text-danger">@rupiah($row['pph21'])</td>
                            <td class="text-end fw-bold">@rupiah($row['net'])</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center text-muted p-4">
                            Belum ada data rekap gaji untuk tahun {{ $year }}.
                        </td></tr>
                    @endforelse
                </tbody>
                @if($rows->isNotEmpty())
                    <tfoot class="table-light fw-bold">
                        <tr>
                            <td colspan="5">Total</td>
                            <td class="text-end">@rupiah($rows->sum('gross'))</td>
                            <td class="text-end">@rupiah($rows->sum('bpjs_employee'))</td>
                            <td class="text-end">@rupiah($rows->sum('pph21'))</td>
                            <td class="text-end">@rupiah($rows->sum('net'))</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
@endsection
