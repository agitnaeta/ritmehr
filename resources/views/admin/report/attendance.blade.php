@extends(backpack_view('blank'))

@section('header')
    <section class="container-fluid">
        <h2>Laporan Kehadiran <small>{{ $month->locale('id_ID')->isoFormat('MMMM YYYY') }}</small></h2>
    </section>
@endsection

@section('content')
<div class="card">
    <div class="card-header">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label mb-0">Bulan</label>
                <input type="month" name="month" class="form-control form-control-sm" value="{{ $month->format('Y-m') }}">
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
            <div class="col-auto">
                <label class="form-label mb-0">Cabang</label>
                <select name="branch_id" class="form-control form-control-sm">
                    <option value="">Semua</option>
                    @foreach($branches as $b)
                        <option value="{{ $b->id }}" @selected($branchId == $b->id)>{{ $b->name }}</option>
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
                        <th>Karyawan</th><th>Departemen</th><th>Cabang</th>
                        <th class="text-end">Hadir</th><th class="text-end">Telat</th>
                        <th class="text-end">Menit Telat</th><th class="text-end">Lembur</th>
                        <th class="text-end">Luar Radius</th>
                        <th class="text-end">Cuti Dibayar</th><th class="text-end">Cuti Tdk Dibayar</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td>{{ $row['user']->name }}</td>
                            <td>{{ $row['department'] }}</td>
                            <td>{{ $row['branch'] }}</td>
                            <td class="text-end">{{ $row['present'] }}</td>
                            <td class="text-end">{{ $row['late'] }}</td>
                            <td class="text-end">{{ $row['late_minutes'] }}</td>
                            <td class="text-end">{{ $row['overtime'] }}</td>
                            <td class="text-end">{{ $row['outside'] }}</td>
                            <td class="text-end">{{ $row['leave_paid'] }}</td>
                            <td class="text-end">{{ $row['leave_unpaid'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="text-center text-muted p-4">Tidak ada data.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
