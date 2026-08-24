@extends(backpack_view('blank'))

@section('header')
    <section class="container-fluid"><h2>Laporan Gaji <small>{{ $recapMonth }}</small></h2></section>
@endsection

@section('content')
<div class="card">
    <div class="card-header">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label mb-0">Bulan Rekap (mm-yyyy)</label>
                <input type="text" name="recap_month" class="form-control form-control-sm"
                       value="{{ $recapMonth }}" placeholder="08-2026">
            </div>
            <div class="col-auto">
                <label class="form-label mb-0">Departemen</label>
                <select name="department_id" class="form-control form-control-sm">
                    <option value="">Semua</option>
                    @foreach($departments as $d)
                        <option value="{{ $d->id }}" @selected($departmentId == $d->id)>{{ $d->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto"><button class="btn btn-sm btn-primary">Tampilkan</button></div>
        </form>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>Karyawan</th><th>Departemen</th>
                        <th class="text-end">Hari Kerja</th><th class="text-end">Gaji Pokok</th>
                        <th class="text-end">Lembur</th><th class="text-end">Potongan</th>
                        <th class="text-end">PPh 21</th><th class="text-end">Diterima</th><th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td>{{ $row['user']?->name ?? '—' }}</td>
                            <td>{{ $row['department'] }}</td>
                            <td class="text-end">{{ $row['work_day'] }}</td>
                            <td class="text-end">@rupiah($row['salary'])</td>
                            <td class="text-end">@rupiah($row['overtime'])</td>
                            <td class="text-end text-danger">@rupiah($row['deductions'])</td>
                            <td class="text-end text-danger">@rupiah($row['pph21'])</td>
                            <td class="text-end fw-bold">@rupiah($row['received'])</td>
                            <td>
                                @if($row['paid'])<span class="badge bg-success">Dibayar</span>
                                @else<span class="badge bg-secondary">Belum</span>@endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center text-muted p-4">Tidak ada rekap gaji untuk {{ $recapMonth }}.</td></tr>
                    @endforelse
                </tbody>
                @if($rows->isNotEmpty())
                    <tfoot class="table-light fw-bold">
                        <tr>
                            <td colspan="3">Total</td>
                            <td class="text-end">@rupiah($rows->sum('salary'))</td>
                            <td class="text-end">@rupiah($rows->sum('overtime'))</td>
                            <td class="text-end">@rupiah($rows->sum('deductions'))</td>
                            <td class="text-end">@rupiah($rows->sum('pph21'))</td>
                            <td class="text-end">@rupiah($rows->sum('received'))</td>
                            <td></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
@endsection
