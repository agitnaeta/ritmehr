@extends('portal.layout')
@section('title', 'Slip Gaji')
@section('heading', 'Slip Gaji')

@section('content')
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive hide-on-mobile">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>Periode</th>
                        <th class="text-end">Gaji Pokok</th>
                        <th class="text-end">Potongan</th>
                        <th class="text-end">Diterima</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recaps as $recap)
                        @php $deductions = $recap->loan_cut + $recap->late_cut + $recap->abstain_cut; @endphp
                        <tr>
                            <td>{{ $recap->recap_month }}</td>
                            <td class="text-end">@rupiah($recap->salary_amount)</td>
                            <td class="text-end text-danger">@rupiah($deductions)</td>
                            <td class="text-end fw-bold">@rupiah($recap->received)</td>
                            <td>
                                @if($recap->paid)
                                    <span class="badge bg-success">Dibayar</span>
                                @else
                                    <span class="badge bg-secondary">Belum</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('portal.salary.show', $recap->id) }}"
                                   class="btn btn-sm btn-outline-primary">Detail</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted p-4">Belum ada slip gaji.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Versi kartu untuk mobile (tampil <992px via CSS). --}}
        <div class="data-cards p-3">
            @forelse($recaps as $recap)
                @php $deductions = $recap->loan_cut + $recap->late_cut + $recap->abstain_cut; @endphp
                <div class="data-card">
                    <div class="data-card__top">
                        <div>
                            <div class="data-card__title">{{ $recap->recap_month }}</div>
                            <div class="data-card__meta">
                                Pokok @rupiah($recap->salary_amount) · Potongan @rupiah($deductions)
                            </div>
                        </div>
                        <div class="data-card__amt text-success">@rupiah($recap->received)</div>
                    </div>
                    <div class="data-card__foot">
                        @if($recap->paid)
                            <span class="badge bg-success">Dibayar</span>
                        @else
                            <span class="badge bg-secondary">Belum dibayar</span>
                        @endif
                        <a href="{{ route('portal.salary.show', $recap->id) }}"
                           class="btn btn-sm btn-outline-primary">Detail</a>
                    </div>
                </div>
            @empty
                <div class="empty-state">
                    <i class="la la-file-invoice-dollar"></i>
                    Belum ada slip gaji.
                </div>
            @endforelse
        </div>
    </div>
</div>

<div class="mt-3">{{ $recaps->links() }}</div>
@endsection
