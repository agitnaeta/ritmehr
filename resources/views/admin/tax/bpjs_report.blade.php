@extends(backpack_view('blank'))

@section('header')
    <section class="container-fluid"><h2>Rekap BPJS <small>{{ $month }}</small></h2></section>
@endsection

@section('content')
<div class="card">
    <div class="card-header">
        <div class="row g-2 align-items-end">
            <div class="col-auto">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-auto">
                        <label class="form-label mb-0">Bulan Rekap (mm-yyyy)</label>
                        <input type="text" name="month" class="form-control form-control-sm"
                               value="{{ $month }}" placeholder="08-2026">
                    </div>
                    <div class="col-auto"><button class="btn btn-sm btn-primary">Tampilkan</button></div>
                </form>
            </div>
            <div class="col-auto">
                <form method="POST" action="{{ backpack_url('tax-report/recalculate') }}">
                    @csrf
                    <input type="hidden" name="month" value="{{ $month }}">
                    <button class="btn btn-sm btn-outline-secondary"
                            onclick="return confirm('Hitung ulang pajak & BPJS untuk {{ $month }}?')">
                        <i class="la la-calculator"></i> Hitung Ulang
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-sm mb-0">
                <thead>
                    <tr>
                        <th>Karyawan</th>
                        <th class="text-end">Upah</th>
                        <th class="text-end">Kes (Kry)</th>
                        <th class="text-end">Kes (Prsh)</th>
                        <th class="text-end">JHT (Kry)</th>
                        <th class="text-end">JHT (Prsh)</th>
                        <th class="text-end">JP (Kry)</th>
                        <th class="text-end">JP (Prsh)</th>
                        <th class="text-end">JKK</th>
                        <th class="text-end">JKM</th>
                        <th class="text-end">Total Kry</th>
                        <th class="text-end">Total Prsh</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td>{{ $row['user']?->name ?? '—' }}</td>
                            <td class="text-end">@rupiah($row['base'])</td>
                            <td class="text-end">@rupiah($row['kes_employee'])</td>
                            <td class="text-end">@rupiah($row['kes_employer'])</td>
                            <td class="text-end">@rupiah($row['jht_employee'])</td>
                            <td class="text-end">@rupiah($row['jht_employer'])</td>
                            <td class="text-end">@rupiah($row['jp_employee'])</td>
                            <td class="text-end">@rupiah($row['jp_employer'])</td>
                            <td class="text-end">@rupiah($row['jkk'])</td>
                            <td class="text-end">@rupiah($row['jkm'])</td>
                            <td class="text-end fw-bold">@rupiah($row['employee_total'])</td>
                            <td class="text-end fw-bold">@rupiah($row['employer_total'])</td>
                        </tr>
                    @empty
                        <tr><td colspan="12" class="text-center text-muted p-4">
                            Tidak ada rekap gaji untuk {{ $month }}.
                            Jalankan "Hitung Ulang" setelah rekap gaji dibuat.
                        </td></tr>
                    @endforelse
                </tbody>
                @if($rows->isNotEmpty())
                    <tfoot class="table-light fw-bold">
                        <tr>
                            <td colspan="10">Total</td>
                            <td class="text-end">@rupiah($rows->sum('employee_total'))</td>
                            <td class="text-end">@rupiah($rows->sum('employer_total'))</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
@endsection
