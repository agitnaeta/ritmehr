@extends(backpack_view('blank'))

@section('header')
    <section class="container-fluid">
        <h2>Kelengkapan Dokumen
            <small><a href="{{ backpack_url('employee-document') }}" class="font-sm">
                <i class="la la-angle-double-left"></i> Kembali</a></small>
        </h2>
    </section>
@endsection

@section('content')
<div class="card">
    <div class="card-body p-0">
        <table class="table table-striped mb-0">
            <thead>
                <tr>
                    <th>Karyawan</th>
                    <th>Departemen</th>
                    <th class="text-center">Kelengkapan</th>
                    <th>Dokumen Kurang</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td>{{ $row['user']->name }}</td>
                        <td>{{ $row['department'] }}</td>
                        <td class="text-center">
                            @if($row['required'] === 0)
                                <span class="text-muted">—</span>
                            @elseif($row['complete'])
                                <span class="badge bg-success">Lengkap ({{ $row['held'] }}/{{ $row['required'] }})</span>
                            @else
                                <span class="badge bg-warning text-dark">{{ $row['held'] }}/{{ $row['required'] }}</span>
                            @endif
                        </td>
                        <td class="small">
                            @forelse($row['missing'] as $type)
                                <span class="badge bg-light text-dark">{{ $type->name }}</span>
                            @empty
                                <span class="text-muted">—</span>
                            @endforelse
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted p-4">Belum ada karyawan aktif.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
