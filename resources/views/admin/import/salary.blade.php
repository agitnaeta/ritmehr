@include('admin.import._layout', [
    'title'       => 'Import Struktur Gaji dari Excel',
    'backUrl'     => url('admin/salary'),
    'templateUrl' => route('salary.import.template'),
    'previewUrl'  => route('salary.import.preview'),
    'storeUrl'    => route('salary.import.store'),
    'columns'     => 'email, gaji_pokok, lembur_1x, denda_per_menit, potongan_absen',
    'preview'     => $preview ?? null,
    'result'      => $result ?? null,
])
