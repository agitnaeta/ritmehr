@extends(backpack_view('blank'))

@section('header')
    <section class="container-fluid"><h2>Laporan Headcount</h2></section>
@endsection

@section('content')
<div class="row">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><strong>Per Status</strong></div>
            <div class="card-body">
                @foreach(['active' => 'Aktif', 'probation' => 'Masa Percobaan',
                          'resigned' => 'Resign', 'terminated' => 'Diberhentikan'] as $key => $label)
                    <div class="d-flex justify-content-between border-bottom py-2">
                        <span>{{ $label }}</span>
                        <strong>{{ $headcount['by_status'][$key] ?? 0 }}</strong>
                    </div>
                @endforeach
                <div class="d-flex justify-content-between pt-2">
                    <strong>Total Aktif</strong><strong>{{ $headcount['total'] }}</strong>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><strong>Per Departemen</strong></div>
            <div class="card-body">
                @forelse($headcount['by_department'] as $row)
                    <div class="d-flex justify-content-between border-bottom py-2">
                        <span>{{ $row['name'] }}</span><strong>{{ $row['count'] }}</strong>
                    </div>
                @empty
                    <p class="text-muted mb-0">Belum ada departemen.</p>
                @endforelse
                @if($headcount['unassigned'] > 0)
                    <div class="d-flex justify-content-between py-2 text-warning">
                        <span>Tanpa Departemen</span><strong>{{ $headcount['unassigned'] }}</strong>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><strong>Per Cabang</strong></div>
            <div class="card-body">
                @forelse($headcount['by_branch'] as $row)
                    <div class="d-flex justify-content-between border-bottom py-2">
                        <span>{{ $row['name'] }}</span><strong>{{ $row['count'] }}</strong>
                    </div>
                @empty
                    <p class="text-muted mb-0">Belum ada cabang.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
