<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Slip Gaji {{ $recap->recap_month }} — {{ $user->name }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: -apple-system, "Segoe UI", Roboto, Arial, sans-serif; color: #1f2937; margin: 0; padding: 24px; font-size: 13px; }
        .sheet { max-width: 720px; margin: 0 auto; }
        .head { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #111827; padding-bottom: 12px; margin-bottom: 16px; }
        .company { font-size: 18px; font-weight: 700; }
        .doc-title { text-align: right; }
        .doc-title h1 { font-size: 16px; margin: 0; }
        .doc-title .period { color: #6b7280; }
        .meta { display: flex; flex-wrap: wrap; gap: 4px 32px; margin-bottom: 16px; }
        .meta div { min-width: 200px; }
        .meta .label { color: #6b7280; font-size: 11px; text-transform: uppercase; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        th, td { padding: 6px 8px; text-align: left; }
        .section-title { font-weight: 700; margin: 14px 0 4px; padding-bottom: 2px; border-bottom: 1px solid #e5e7eb; }
        .earn { color: #047857; }
        .ded { color: #b91c1c; }
        td.num { text-align: right; font-variant-numeric: tabular-nums; }
        tr.sub td { border-top: 1px solid #e5e7eb; font-weight: 700; }
        .net { display: flex; justify-content: space-between; align-items: center; margin-top: 16px; padding: 12px 8px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 6px; }
        .net .amt { font-size: 20px; font-weight: 800; color: #047857; }
        .foot { margin-top: 28px; color: #9ca3af; font-size: 11px; text-align: center; }
        .toolbar { max-width: 720px; margin: 0 auto 16px; display: flex; gap: 8px; }
        .btn { border: 1px solid #111827; background: #111827; color: #fff; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-size: 13px; text-decoration: none; }
        .btn.secondary { background: #fff; color: #111827; }
        @media print {
            .toolbar { display: none !important; }
            body { padding: 0; }
        }
    </style>
</head>
<body>
    @php
        $totalEarn = $recap->salary_amount + $recap->overtime_amount + $recap->extra_time_amount;
        $totalDed = $recap->loan_cut + $recap->late_cut + $recap->abstain_cut
            + ($recap->pph21 ?? 0) + ($recap->bpjs_kes_employee ?? 0)
            + ($recap->bpjs_jht_employee ?? 0) + ($recap->bpjs_jp_employee ?? 0);
    @endphp

    <div class="toolbar">
        <a href="#" class="btn" onclick="window.print();return false;">🖨️ Cetak / Simpan PDF</a>
        <a href="{{ route('portal.salary.show', $recap->id) }}" class="btn secondary">Kembali</a>
    </div>

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
            <tr><td>Gaji Pokok</td><td class="num">{{ money($recap->salary_amount) }}</td></tr>
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
</body>
</html>
