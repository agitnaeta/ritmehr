@extends(backpack_view('blank'))

@section('header')
    <section class="container-fluid"><h2>Laporan Kasbon <small>sisa pinjaman berjalan</small></h2></section>
@endsection

@section('content')
<div class="card">
    <div class="card-body p-0">
        <table class="table table-striped mb-0">
            <thead>
                <tr>
                    <th>Karyawan</th><th>Departemen</th>
                    <th class="text-end">Total Pinjaman</th>
                    <th class="text-end">Sudah Dibayar</th>
                    <th class="text-end">Sisa</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td>{{ $row['user']->name }}</td>
                        <td>{{ $row['department'] }}</td>
                        <td class="text-end">@rupiah($row['borrowed'])</td>
                        <td class="text-end text-success">@rupiah($row['repaid'])</td>
                        <td class="text-end fw-bold text-danger">@rupiah($row['outstanding'])</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted p-4">Tidak ada kasbon berjalan.</td></tr>
                @endforelse
            </tbody>
            @if($rows->isNotEmpty())
                <tfoot class="table-light fw-bold">
                    <tr>
                        <td colspan="2">Total</td>
                        <td class="text-end">@rupiah($rows->sum('borrowed'))</td>
                        <td class="text-end">@rupiah($rows->sum('repaid'))</td>
                        <td class="text-end">@rupiah($rows->sum('outstanding'))</td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>
@endsection
