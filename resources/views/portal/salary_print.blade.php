<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Slip Gaji {{ $recap->recap_month }} — {{ $user->name }}</title>
    @include('partials.payslip-styles')
</head>
<body>
    <div class="toolbar">
        <a href="#" class="btn" onclick="window.print();return false;">🖨️ Cetak / Simpan PDF</a>
        <a href="{{ route('portal.salary.show', $recap->id) }}" class="btn secondary">Kembali</a>
    </div>

    @include('partials.payslip', ['recap' => $recap, 'user' => $user, 'company' => $company])
</body>
</html>
