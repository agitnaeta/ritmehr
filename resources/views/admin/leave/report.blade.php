@extends(backpack_view('blank'))

@section('header')
    <section class="container-fluid">
        <h2>Rekap Cuti <small>Tahun {{ $year }}</small></h2>
    </section>
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
        <table class="table table-striped mb-0">
            <thead>
                <tr>
                    <th>Karyawan</th>
                    <th>Departemen</th>
                    @foreach($types as $typeName)
                        <th class="text-end">{{ $typeName }}</th>
                    @endforeach
                    <th class="text-end">Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td>{{ $row['user']?->name ?? '—' }}</td>
                        <td>{{ $row['department'] }}</td>
                        @foreach($types as $typeName)
                            <td class="text-end">{{ $row['byType'][$typeName] ?? 0 }}</td>
                        @endforeach
                        <td class="text-end fw-bold">{{ $row['total'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $types->count() + 3 }}" class="text-center text-muted p-4">
                            Belum ada cuti disetujui pada periode ini.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
