@extends(backpack_view('blank'))

@php
    $cur = app(\App\Services\CurrencyService::class)->symbol();
    $rp = fn ($v) => $cur . ' ' . number_format((int) $v, 0, ',', '.');
    $u = $salary->user;
    $overtimeType = $salary->overtime_type === 'hour' ? 'Per-Jam' : 'Flat';
    $fineType = $salary->fine_type === 'minute' ? 'Per-Menit' : 'Flat';
@endphp

@section('content')
<div class="container-fluid animated fadeIn" style="max-width: 960px;">

    <div class="d-flex justify-content-between align-items-center mb-3 mt-2">
        <div>
            <h2 class="mb-0">{{ $u->name ?? '-' }}</h2>
            <small class="text-muted">
                {{ $u->department?->name ?? '—' }} · {{ $u->position?->name ?? '—' }}
            </small>
        </div>
        <div>
            <a href="{{ url($crud->route) }}" class="btn btn-sm btn-outline-secondary">
                <i class="la la-angle-left"></i> Kembali
            </a>
            @if(backpack_user()->can('salary.edit'))
                <a href="{{ url($crud->route.'/'.$salary->id.'/edit') }}" class="btn btn-sm btn-primary">
                    <i class="la la-edit"></i> Ubah
                </a>
            @endif
        </div>
    </div>

    <div class="row">
        {{-- Komponen Gaji --}}
        <div class="col-lg-6 mb-3">
            <div class="card shadow-xs h-100">
                <div class="card-header fw-bold">
                    <i class="la la-money-bill text-success"></i> Komponen Gaji
                </div>
                <div class="card-body p-0">
                    <table class="table table-vcenter mb-0">
                        <tbody>
                            <tr>
                                <td>Gaji Pokok</td>
                                <td class="text-end">{{ $rp($salary->basic_salary) }}</td>
                            </tr>
                            @forelse($allowances as $a)
                                <tr>
                                    <td class="ps-4 text-muted">{{ $a->type->label }}</td>
                                    <td class="text-end">{{ $rp($a->amount) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="ps-4 text-muted fst-italic" colspan="2">Tidak ada tunjangan</td>
                                </tr>
                            @endforelse
                            <tr class="fw-bold border-top">
                                <td>Total Gaji</td>
                                <td class="text-end text-success">{{ $rp($salary->amount) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Lembur & Denda --}}
        <div class="col-lg-6 mb-3">
            <div class="card shadow-xs h-100">
                <div class="card-header fw-bold">
                    <i class="la la-clock text-primary"></i> Lembur, Denda & Potongan
                </div>
                <div class="card-body p-0">
                    <table class="table table-vcenter mb-0">
                        <tbody>
                            <tr><td>Besaran 1x Lembur</td><td class="text-end">{{ $rp($salary->overtime_amount) }}</td></tr>
                            <tr><td>Tipe Lembur</td><td class="text-end">{{ $overtimeType }}</td></tr>
                            <tr><td>Jenis Denda Telat</td><td class="text-end">{{ $fineType }}</td></tr>
                            <tr><td>Denda Per-Menit</td><td class="text-end">{{ $rp($salary->fine_per_minute) }}</td></tr>
                            <tr><td>Denda Telat (Flat)</td><td class="text-end">{{ $salary->fine ? $rp($salary->fine) : '—' }}</td></tr>
                            <tr><td>Potongan Absen / Hari</td><td class="text-end">{{ $rp($salary->unpaid_leave_deduction) }}</td></tr>
                            <tr><td>Lebih Waktu / Menit</td><td class="text-end">{{ $rp($salary->extra_time) }}</td></tr>
                            <tr>
                                <td>Aturan Lebih Waktu</td>
                                <td class="text-end">
                                    @if($salary->extra_time_rule == 1)
                                        <span class="badge bg-success">Aktif</span>
                                    @else
                                        <span class="badge bg-secondary">Nonaktif</span>
                                    @endif
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @if(backpack_user()->can('salary.edit'))
        <div class="text-end">
            <form action="{{ url($crud->route.'/'.$salary->id) }}" method="POST" class="d-inline"
                  onsubmit="return confirm('Hapus data gaji {{ $u->name ?? '' }}?');">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-sm btn-outline-danger">
                    <i class="la la-trash"></i> Hapus
                </button>
            </form>
        </div>
    @endif

</div>
@endsection
