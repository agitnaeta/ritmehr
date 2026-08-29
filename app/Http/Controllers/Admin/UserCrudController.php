<?php

namespace App\Http\Controllers\Admin;

use App\Exports\UserExport;
use App\Http\Requests\UserRequest;
use App\Models\Branch;
use App\Models\CompanyProfile;
use App\Models\Department;
use App\Models\Position;
use App\Models\Schedule;
use App\Models\User;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Prologue\Alerts\Facades\Alert;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * Class UserCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class UserCrudController extends CrudController
{
    use \App\Traits\HasSimpleFilters;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    /**
     * UM-08 — Bahasa yang didukung i18n proyek (lang/id, lang/en).
     * Dipakai di field dropdown & relabel kolom list.
     *
     * @var array<string, string>
     */
    public const LOCALE_OPTIONS = [
        'id' => 'Indonesia',
        'en' => 'English',
    ];

    /**
     * Configure the CrudPanel object. Apply settings to all operations.
     *
     * @return void
     */
    public function setup()
    {
        CRUD::setModel(\App\Models\User::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/user');
        CRUD::setEntityNameStrings('user', 'users');
        $this->crud->addClause('with','schedule');

        $me = backpack_user();

        if (! $me->can('user.view')) {
            abort(403, 'Anda tidak berhak melihat data karyawan.');
        }

        // Manager hanya melihat bawahan langsungnya; HR dan super admin semuanya.
        $this->crud->addClause('visibleTo', $me);

        if (! $me->can('user.create')) CRUD::denyAccess(['create']);
        if (! $me->can('user.edit'))   CRUD::denyAccess(['update']);
        if (! $me->can('user.delete')) CRUD::denyAccess(['delete']);
    }

    protected function setupShowOperation()
    {
        $this->autoSetupShowOperation();

        $this->crud->removeColumn('schedule_id');
        $this->crud->column([
            'name'=>'image',
            'label'=>'Foto Karyawan',
            'type'=>'custom_html',
            'value'=>function($entry){
                $path = "public/$entry->image";
                $storage = Storage::url($path);
                return "<img width='100' height='100' src='$storage' />";
            }
        ]);
        $this->crud->column( [
            'name' => 'schedule_id',
            'label' => 'Jadwal',
            'type' => 'select',
            'entity' => 'schedule', // the relationship method name
            'attribute' => 'name', // the attribute to display from the related model
            'model' => Schedule::class, // the related model
        ])->after('email');

        $this->crud->column([
           "name"=>"qr",
           "label"=>"QR Code",
           "type"=>"custom_html",
           "value"=> function($entry){
                if(!$entry->qr){
                    $entry->qr = Str::uuid();
                    $entry->saveQuietly();
                }

                $base = base64_encode( QrCode::size(200)
                    ->generate($entry->qr));
                return "<img src='data:image/svg+xml;base64,$base'/>";
           }
        ])->after('email');

    }

    /**
     * Define what happens when the List operation is loaded.
     *
     * @see  https://backpackforlaravel.com/docs/crud-operation-list-entries
     * @return void
     */
    protected function setupListOperation()
    {
        CRUD::setFromDb(); // set columns from db columns.

        // UM-01 — Matikan auto-collapse DataTables Responsive. Dengan default ON,
        // ekstensi menyembunyikan kolom di balik ikon ⋮ bahkan saat masih ada ruang
        // (terlihat di desktop 1280px). Kolom sudah diringkas ke 5 inti di bawah,
        // jadi cukup muat di semua layar; layar sempit pakai scroll horizontal.
        $this->crud->setOperationSetting('responsiveTable', false);

        /**
         * Columns can be defined using the fluent syntax:
         * - CRUD::column('price')->type('number');
         */

        $this->crud->removeColumn('schedule_id');
        $this->crud->column( [
            'name' => 'schedule_id',
            'label' => 'Jadwal',
            'type' => 'select',
            'entity' => 'schedule', // the relationship method name
            'attribute' => 'name', // the attribute to display from the related model
            'model' => Schedule::class, // the related model
        ]);

        $this->orgListColumns();

        // QW-03 — setFromDb() meng-humanize kolom DB jadi label Inggris
        // ("Name/Email/Locale/Employee/Join date"). Seragamkan ke Bahasa Indonesia.
        // UM-01 — Tabel responsif: hanya kolom INTI yang tampil di tabel
        // (Nama, Email, Karyawan/NIK, Departemen, Status). Kolom sekunder
        // di-visibleInTable(false) → tetap ada di export & halaman detail, tapi
        // tidak memenuhi tabel & memicu collapse responsive DataTables.
        // `priority` rendah = kolom lebih dipertahankan saat layar menyempit.
        $this->crud->column('name')->label('Nama')->priority(1);
        $this->crud->column('email')->label('Email')->priority(3);
        $this->crud->column('employee_id')->label('Karyawan')->priority(2);
        $this->crud->column('department_id')->priority(4);   // Departemen (label diset di orgListColumns)
        $this->crud->column('employment_status')->priority(5); // Status (label diset di orgListColumns)

        // Kolom sekunder — sembunyikan dari tabel (tetap tersedia utk export/detail)
        $this->crud->column('locale')->label('Bahasa')->visibleInTable(false)
            ->type('select_from_array')->options(self::LOCALE_OPTIONS);
        $this->crud->column('join_date')->label('Tgl Bergabung')->visibleInTable(false);
        $this->crud->column('schedule_id')->visibleInTable(false); // Jadwal
        $this->crud->column('position_id')->visibleInTable(false);  // Jabatan
        $this->crud->column('branch_id')->visibleInTable(false);    // Cabang
        $this->crud->column('phone')->visibleInTable(false);
        $this->crud->column('image')->visibleInTable(false);

        $this->crud->addButtonFromView('line','user-print','user-print','end');
        // UM-03: Export, Import, Cetak Semua ID — digabung di dropdown "⋯"
        $this->crud->allowAccess("user_export");
        $this->crud->allowAccess('print_id_cards');
        $this->crud->addButtonFromView('top','user_actions_dropdown','user_actions_dropdown','end');


    }


    /**
     * Define what happens when the Create operation is loaded.
     *
     * @see https://backpackforlaravel.com/docs/crud-operation-create
     * @return void
     */
    protected function setupCreateOperation()
    {
        CRUD::setValidation(UserRequest::class);
        CRUD::setFromDb(); // set fields from db columns.

        $this->fieldModification();
    }

    /**
     * Define what happens when the Update operation is loaded.
     *
     * @see https://backpackforlaravel.com/docs/crud-operation-update
     * @return void
     */
    protected function setupUpdateOperation()
    {

        $userReq = $this->crud->validateRequest();
        $this->crud->setValidation(
            (new UserRequest())->updateRules($userReq->get('id')),
            (new UserRequest())->messages(),
        );
        CRUD::setFromDb(); // set fields from db columns.
        $this->fieldModification();
    }

    function fieldModification(){

        CRUD::field([
            'Label'=> "Jadwal",
            'name'=>'schedule_id',
            'type'=>'select',
            'model'     => Schedule::class,
            'attribute'=>'name'
        ]);
        CRUD::field('image')
            ->type('upload')
            ->withFiles([
                'disk' => 'public', // the disk where file will be stored
                'path' => 'uploads', // the path inside the disk where file will be stored
            ]);

        $this->orgFields();

        // UM-08 — Bahasa (locale) sebagai dropdown pilihan, bukan text input.
        // Opsi konsisten dengan i18n proyek (lang/id, lang/en). Default Indonesia.
        CRUD::field([
            'name'        => 'locale',
            'label'       => 'Bahasa',
            'type'        => 'select_from_array',
            'options'     => self::LOCALE_OPTIONS,
            'allows_null' => false,
            'default'     => 'id',
        ]);
    }

    /**
     * setFromDb() would list every new org column as raw text/ids, which makes
     * the table unreadable. Swap in relationship columns and drop the noise.
     */
    private function orgListColumns(): void
    {
        foreach (['manager_id', 'department_id', 'branch_id', 'position_id',
                  'address', 'employment_status'] as $col) {
            $this->crud->removeColumn($col);
        }

        $this->crud->column([
            'name'      => 'department_id',
            'label'     => 'Departemen',
            'type'      => 'select',
            'entity'    => 'department',
            'attribute' => 'name',
            'model'     => Department::class,
        ]);

        $this->crud->column([
            'name'      => 'position_id',
            'label'     => 'Jabatan',
            'type'      => 'select',
            'entity'    => 'position',
            'attribute' => 'name',
            'model'     => Position::class,
        ]);

        $this->crud->column([
            'name'      => 'branch_id',
            'label'     => 'Cabang',
            'type'      => 'select',
            'entity'    => 'branch',
            'attribute' => 'name',
            'model'     => Branch::class,
        ]);

        $this->crud->column([
            'name'     => 'employment_status',
            'label'    => 'Status',
            'type'     => 'closure',
            'function' => fn (User $entry) => $entry->employmentStatusLabel(),
        ]);

        $this->applySimpleFilters([
            [
                'name'    => 'department_id',
                'label'   => 'Departemen',
                'type'    => 'select',
                'options' => Department::orderBy('name')->pluck('name', 'id')->toArray(),
            ],
            [
                'name'    => 'branch_id',
                'label'   => 'Cabang',
                'type'    => 'select',
                'options' => Branch::orderBy('name')->pluck('name', 'id')->toArray(),
            ],
            [
                'name'    => 'employment_status',
                'label'   => 'Status',
                'type'    => 'select',
                'options' => [
                    User::STATUS_ACTIVE     => 'Aktif',
                    User::STATUS_PROBATION  => 'Masa Percobaan',
                    User::STATUS_RESIGNED   => 'Resign',
                    User::STATUS_TERMINATED => 'Diberhentikan',
                ],
            ],
        ]);
    }

    /**
     * Organisation fields (M1). setFromDb() picks these columns up as plain
     * inputs, so replace them with proper relationship/enum widgets.
     */
    private function orgFields(): void
    {
        CRUD::field([
            'name'        => 'department_id',
            'label'       => 'Departemen',
            'type'        => 'select',
            'entity'      => 'department',
            'attribute'   => 'name',
            'model'       => Department::class,
            'allows_null' => true,
        ]);

        CRUD::field([
            'name'        => 'branch_id',
            'label'       => 'Cabang',
            'type'        => 'select',
            'entity'      => 'branch',
            'attribute'   => 'name',
            'model'       => Branch::class,
            'allows_null' => true,
        ]);

        CRUD::field([
            'name'        => 'position_id',
            'label'       => 'Jabatan',
            'type'        => 'select',
            'entity'      => 'position',
            'attribute'   => 'name',
            'model'       => Position::class,
            'allows_null' => true,
        ]);

        CRUD::field([
            'name'        => 'manager_id',
            'label'       => 'Atasan Langsung',
            'type'        => 'select',
            'entity'      => 'manager',
            'attribute'   => 'name',
            'model'       => User::class,
            'allows_null' => true,
            'hint'        => 'Dipakai sebagai approver default pada alur persetujuan.',
        ]);

        CRUD::field([
            'name'  => 'employee_id',
            'label' => 'NIK / NIP',
            'type'  => 'text',
        ]);

        CRUD::field([
            'name'  => 'join_date',
            'label' => 'Tanggal Bergabung',
            'type'  => 'date',
        ]);

        CRUD::field([
            'name'        => 'employment_status',
            'label'       => 'Status Kepegawaian',
            'type'        => 'select_from_array',
            'options'     => [
                User::STATUS_ACTIVE     => 'Aktif',
                User::STATUS_PROBATION  => 'Masa Percobaan',
                User::STATUS_RESIGNED   => 'Resign',
                User::STATUS_TERMINATED => 'Diberhentikan',
            ],
            'allows_null' => false,
            'default'     => User::STATUS_ACTIVE,
        ]);

        CRUD::field(['name' => 'phone', 'label' => 'No. Telepon', 'type' => 'text']);
        CRUD::field(['name' => 'address', 'label' => 'Alamat', 'type' => 'textarea']);
    }

    public function update()
    {
        $request = $this->crud->validateRequest()->all();
        $user = User::find($request['id']);
        if($request['password']){
            $user->password = Hash::make($request['password']);
        }
        else{
            unset($request['password']);

        }

        if(!isset($request['image'])){
            unset($request['image']);
        }
        $user->update($request);
        Alert::success("<strong>Success</strong><br> Berhasil Update data")->flash();
        return redirect(route('user.index'));
    }

    public function printButton($userId){
        // and even the attributes of the <a> element in meta's `wrapper`
        CRUD::button('print')->stack('line')->view('crud::buttons.quick')->meta([
            'access' => true,
            'label' => 'Print',
            'icon' => 'la la-print',
            'wrapper' => [
                'element' => 'a',
                'href' => route('user.print',['id'=>$userId]),
                'target' => '_blank',
                'title' => 'Print PDF ID CARD',
            ]
        ]);
    }

    public function printAllIdCard(){
        $this->crud->allowAccess('print_id_cards');
        $this->crud->addButtonFromView(
            'top','print_id_cards','print_id_cards','end'
        );
    }

    public function print($id){
        $me = backpack_user();
        abort_unless($me?->can('user.view'), 403, 'Anda tidak berhak mencetak ID karyawan.');
        // Scope visibilitas: manager hanya boleh mencetak ID bawahannya.
        $users = User::where('id',$id)->visibleTo($me)->get();
        abort_if($users->isEmpty(), 404, 'Karyawan tidak ditemukan atau di luar wewenang Anda.');
        return $this->_print($users);
    }
    public function printAll(){
        $me = backpack_user();
        abort_unless($me?->can('user.view'), 403, 'Anda tidak berhak mencetak ID karyawan.');
        // Scope visibilitas: manager hanya mencetak bawahannya, bukan seluruh karyawan.
        $users = User::query()->visibleTo($me)->get();
        return $this->_print($users);
    }
    private function _print(Collection $users){
        $users->map(function ($user){
            $user->isUserImage = strlen($user->image) > 0 ;
            $user->image = Storage::path("public/$user->image");
            if($user->qr){
                $user->qr = base64_encode(QrCode::size(150)->generate($user->qr));
            }
        });
        $company = CompanyProfile::find(1);
        if(!$company->id_card || !$company->image){
            Alert::error("Silahkan Seting Profile Perusaan Terlebih dahulu!")->flash();
            return redirect(route('company-profile.index'));
        }
        $company->image = Storage::path("public/$company->image");
        $company->id_card = Storage::path("public/$company->id_card");
//        return view('user.detail',compact('users','company'));
        $pdf =  Pdf::loadView('user.detail',compact('users','company'))
            ->setPaper([0,0,300,470],'p');
        return $pdf->stream("sample.pdf");
    }

    public function export(){
        $me = backpack_user();
        abort_unless($me?->can('user.view'), 403, 'Anda tidak berhak mengekspor data karyawan.');
        // Viewer dilempar ke export agar query di-scope visibleTo (anti bocor lintas-tim).
        return Excel::download(new UserExport($me),'user-export.xlsx');
    }

    // ── IMP-03 — Import karyawan dari Excel ────────────────

    public function importForm()
    {
        $this->crud->hasAccessOrFail('create');

        return view('admin.import.user', ['preview' => null, 'result' => null]);
    }

    public function importTemplate()
    {
        $this->crud->hasAccessOrFail('create');

        return Excel::download(new \App\Exports\UserTemplateExport, 'template-karyawan.xlsx');
    }

    public function importPreview(\Illuminate\Http\Request $request)
    {
        $this->crud->hasAccessOrFail('create');
        $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv|max:2048']);

        // Simpan file supaya bisa dipakai lagi saat konfirmasi impor.
        $token = (string) Str::uuid();
        $path = $request->file('file')->storeAs('imports', "user-$token.xlsx", 'local');

        $preview = \App\Support\ImportPreview::build(
            Storage::disk('local')->path($path),
            ['email', 'nama'],                 // kolom wajib
            ['nama', 'email', 'nik', 'departemen', 'cabang'] // kolom yang ditampilkan
        );
        $preview['token'] = $token;

        return view('admin.import.user', ['preview' => $preview, 'result' => null]);
    }

    public function importStore(\Illuminate\Http\Request $request)
    {
        $this->crud->hasAccessOrFail('create');

        // Dua jalur: konfirmasi dari preview (token) ATAU upload langsung (file).
        if ($request->filled('token')) {
            $path = Storage::disk('local')->path('imports/user-' . $request->token . '.xlsx');
            abort_unless(is_file($path), 404, 'File impor kadaluarsa, unggah ulang.');
        } else {
            $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv|max:2048']);
            $path = $request->file('file')->getRealPath();
        }

        $import = new \App\Imports\UserImport;
        Excel::import($import, $path);

        if ($request->filled('token')) {
            Storage::disk('local')->delete('imports/user-' . $request->token . '.xlsx');
        }

        $result = [
            'imported' => $import->imported,
            'skipped'  => count($import->failures()),
            'errors'   => collect($import->failures())
                ->map(fn ($f) => 'Baris ' . $f->row() . ' — ' . implode(', ', $f->errors()))
                ->all(),
        ];
        Alert::success("{$result['imported']} karyawan berhasil diimpor.")->flash();

        return view('admin.import.user', ['preview' => null, 'result' => $result]);
    }
}
