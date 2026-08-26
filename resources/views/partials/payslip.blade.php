{{-- Shared single payslip sheet. Identical output for /my/salary and admin.
     Expects: $recap, $user, $company. --}}
@php
    $allowanceLines = $recap->relationLoaded('allowanceLines') ? $recap->allowanceLines : $recap->allowanceLines()->get();
    $hasBreakdown   = $allowanceLines && $allowanceLines->count() > 0;
    $totalEarn = $recap->salary_amount + $recap->overtime_amount + $recap->extra_time_amount;
    $totalDed = $recap->loan_cut + $recap->late_cut + $recap->abstain_cut
        + ($recap->pph21 ?? 0) + ($recap->bpjs_kes_employee ?? 0)
        + ($recap->bpjs_jht_employee ?? 0) + ($recap->bpjs_jp_employee ?? 0);
@endphp

<div class="sheet">
    <div class="head">
        <div>
            <div class="company">{{ $company?->name ?? setting('company_name', config('app.name', 'Perusahaan')) }}</div>
            <div style="color:#6b7280;">Slip Gaji Karyawan @if($company?->address)<br><span style="font-size:11px;">{{ $company->address }}</span>@endif</div>
        </div>
        <div class="doc-title">
            <h1>SLIP GAJI</h1>
            <div class="period">Periode {{ $recap->recap_month }}</div>
        </div>
    </div>

    <div class="meta">
        <div><div class="label">Nama</div><div>{{ $user->name }}</div></div>
        <div><div class="label">Departemen</div><div>{{ $user->department?->name ?? '—' }}</div></div>
        <div><div class="label">Jabatan</div><div>{{ $user->position?->name ?? '—' }}</div></div>
        <div><div class="label">Status Pembayaran</div><div>{{ $recap->paid ? 'Dibayar' : 'Belum Dibayar' }}</div></div>
    </div>

    <div class="section-title earn">Pendapatan</div>
    <table>
        @if($hasBreakdown)
            <tr><td>Gaji Pokok</td><td class="num">{{ money($recap->basic_salary) }}</td></tr>
            @foreach($allowanceLines as $line)
                <tr class="detail"><td>{{ $line->label }}</td><td class="num">{{ money($line->amount) }}</td></tr>
            @endforeach
            <tr class="sub"><td>Subtotal Gaji</td><td class="num">{{ money($recap->salary_amount) }}</td></tr>
        @else
            <tr><td>Gaji Pokok</td><td class="num">{{ money($recap->salary_amount) }}</td></tr>
        @endif
        <tr><td>Lembur</td><td class="num">{{ money($recap->overtime_amount) }}</td></tr>
        <tr><td>Jam Tambahan</td><td class="num">{{ money($recap->extra_time_amount) }}</td></tr>
        <tr class="sub"><td>Total Pendapatan</td><td class="num">{{ money($totalEarn) }}</td></tr>
    </table>

    <div class="section-title ded">Potongan</div>
    <table>
        <tr><td>Kasbon</td><td class="num">{{ money($recap->loan_cut) }}</td></tr>
        <tr><td>Keterlambatan ({{ $recap->late_day }} hari / {{ $recap->late_minute_count }} menit)</td><td class="num">{{ money($recap->late_cut) }}</td></tr>
        <tr><td>Ketidakhadiran ({{ $recap->abstain_count }} hari)</td><td class="num">{{ money($recap->abstain_cut) }}</td></tr>
        <tr><td>PPh 21</td><td class="num">{{ money($recap->pph21 ?? 0) }}</td></tr>
        <tr><td>BPJS Kesehatan (karyawan)</td><td class="num">{{ money($recap->bpjs_kes_employee ?? 0) }}</td></tr>
        <tr><td>BPJS JHT (karyawan)</td><td class="num">{{ money($recap->bpjs_jht_employee ?? 0) }}</td></tr>
        <tr><td>BPJS JP (karyawan)</td><td class="num">{{ money($recap->bpjs_jp_employee ?? 0) }}</td></tr>
        <tr class="sub"><td>Total Potongan</td><td class="num">{{ money($totalDed) }}</td></tr>
    </table>

    <div class="net">
        <span style="font-weight:700;">DITERIMA (NET)</span>
        <span class="amt">{{ money($recap->net_income ?: $recap->received) }}</span>
    </div>

    @if($recap->desc)
        <div style="margin-top:16px;"><div class="label" style="color:#6b7280;font-size:11px;">Catatan</div>{!! nl2br(e($recap->desc)) !!}</div>
    @endif

    <div class="foot">
        Dokumen ini dibuat otomatis oleh sistem pada {{ now()->format('d/m/Y H:i') }}.
        Slip gaji sah tanpa tanda tangan basah.
    </div>
</div>
