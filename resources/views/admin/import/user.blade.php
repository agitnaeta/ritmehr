@include('admin.import._layout', [
    'title'       => 'Import Karyawan dari Excel',
    'backUrl'     => url('admin/user'),
    'templateUrl' => route('user.import.template'),
    'previewUrl'  => route('user.import.preview'),
    'storeUrl'    => route('user.import.store'),
    'columns'     => 'nama, email, nik, tgl_bergabung, departemen, cabang, jabatan, password, status, bahasa',
    'preview'     => $preview ?? null,
    'result'      => $result ?? null,
])
