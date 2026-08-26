<!doctype html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Gaji</title>
    @include('partials.payslip-styles')
</head>
<body>
    @foreach($recaps as $row)
        @include('partials.payslip', ['recap' => $row, 'user' => $row->user, 'company' => $company])
        @if(! $loop->last)
            <div class="page-break"></div>
        @endif
    @endforeach
</body>
</html>
