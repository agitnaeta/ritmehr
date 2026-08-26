<?php

namespace App\Http\Controllers\Admin;

use App\Exports\SalaryRecapExport;
use App\Http\Controllers\Admin\Operations\SetPaymentOperation;
use App\Http\Requests\SalaryRecapRequest;
use App\Models\CompanyProfile;
use App\Models\SalaryRecap;
use App\Models\User;
use App\Services\SalaryService;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Barryvdh\DomPDF\Facade\Pdf;
use Database\Factories\TranslateFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Prologue\Alerts\Facades\Alert;

/**
 * Class SalaryRecapCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class SalaryRecapCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
    use SetPaymentOperation;
    protected $entityField = [
        'name'=>'user_id',
        'entity'=>'user',
        'model'=>User::class,
        'attribute'=>'name',
        'type'=>'select',
        'label'=>'Nama Karyawan'
    ];
    protected $transferStatus = [
        'name'=>'paid_status',
        'type'=>'model_function',
        'function_name'=>'getPaidStatusAttribute',
        'label'=>'Status Bayar',
    ];

    /**
     * Configure the CrudPanel object. Apply settings to all operations.
     *
     * @return void
     */
    public function setup()
    {
        CRUD::setModel(\App\Models\SalaryRecap::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/salary-recap');
        CRUD::setEntityNameStrings('Rekap Gaji', 'Rekap Gaji');
        $this->crud->addClause('with','user');
        $this->crud->denyAccess('create');
        $this->crud->allowAccess(['filter_monthly','export_salary_recap']);

        // Manager boleh melihat rekap, tidak boleh mengubah atau membayarkannya.
        if (! backpack_user()->can('salary_recap.edit')) {
            $this->crud->denyAccess(['update', 'delete']);
        }
        if (! backpack_user()->can('salary.pay')) {
            $this->crud->denyAccess(['setPayment', 'set_payment_cash', 'set_payment_transfer']);
        }
        if (! backpack_user()->can('salary.recalculate')) {
            $this->crud->denyAccess(['recalculateSalary', 'recalculate_salary']);
        }
    }


    protected function autoSetupShowOperation()
    {
        CRUD::setFromDb();
        $this->fieldModification();
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
        CRUD::setOperationSetting('lineButtonsAsDropdown', true);
        $this->crud->set('recap_months',SalaryRecap::distinct('recap_month')->pluck('recap_month')->toArray());
        $filter = $this->crud->getRequest()->query->get('recap_month');
        if($filter !== null){
            $this->crud->set('f_recap_month',$filter);
            $this->crud->addClause('where','recap_month',$filter);
        }
        /**
         * Columns can be defined using the fluent syntax:
         * - CRUD::column('price')->type('number');
         */


        $this->fieldModification();
    }

    public function fieldModification(){
        $this->crud->field($this->entityField)->makeFirst();

        // Columns
        $columnsToRemove = [
            'user_id',
            'work_day',
            'late_day',
            'loan_cut',
            'late_cut',
            'abstain_cut',
            'created_at',
            'received_at',
            'updated_at',
            'desc'
        ];
        $this->crud->removeColumns($columnsToRemove);
        $this->crud->addColumn($this->entityField)->beforeColumn('recap_month');
        $this->crud->addColumn($this->transferStatus)->beforeColumn('recap_month');

// Buttons
        if ($this->crud->getCurrentOperation() != 'show') {
            $this->crud->removeButtons(['delete']);
        }

// Form fields
        $disableFields = [
            'recap_month',
            'user_id',
            'work_day',
            'late_day',
            'salary_amount',
            'overtime_amount',
            'late_cut',
            'received',
            'abstain_cut',
            'abstain_count',
            'late_minute_count'
        ];
        foreach ($disableFields as $field) {
            $this->crud->field($field)->attributes(['readonly' => true, 'class' => 'disabled-input form-control']);
        }

// Translate Field
        $translate = new TranslateFactory();
        foreach ($translate->salaryRecap() as $key => $value) {
            $this->crud->field($key)->label($value);
            $this->crud->column($key)->label($value);
        }

// Prefix
        foreach ($translate->salaryRecapPrefix() as $key => $value) {
            $this->crud->field($key)->prefix($value);
            $this->crud->column($key)->prefix($value);
        }

// Order Fields
        $order = [
            'user_id',
            'recap_month',
            'work_day',
            'abstain_count',
            'late_day',
            'late_minute_count',
            'salary_amount',
            'abstain_cut',
            'late_cut',
            'loan_cut',
            'received',
            'paid',
            'desc',
            'method',
        ];
        if($this->crud->getActionMethod() == "show"){
            $this->crud->orderColumns($order);
        }

        $this->crud->orderFields($order);

// Field order
        $this->crud->field('loan_cut')->before('received');

        // add button
        $this->crud->addButtonFromView(
            'top',
            'filter_monthly',
            'filter_monthly',
            'end'
        );

        $this->crud->allowAccess('print_salary');
        $this->crud->addButtonFromView(
            'line',
            'print_salary',
            'print_salary',
            'end'
        );



    }

    /**
     * Define what happens when the Create operation is loaded.
     *
     * @see https://backpackforlaravel.com/docs/crud-operation-create
     * @return void
     */
    protected function setupCreateOperation()
    {
        CRUD::setValidation(SalaryRecapRequest::class);
        CRUD::setFromDb(); // set fields from db columns.

        /**
         * Fields can be defined using the fluent syntax:
         * - CRUD::field('price')->type('number');
         */
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
        $this->setupCreateOperation();
    }

    public function store(){
        $request = $this->crud->validateRequest();
        SalaryRecap::create($request->validated());
        Alert::success('Berhasil Update data')->flash();
        return redirect(route('salary-recap.index'));
    }

    public function update()
    {
        $request = $this->crud->validateRequest();
        $salaryRecap   = $this->crud->getCurrentEntry();
        $salaryRecap->update($request->validated());
        Alert::success('Berhasil Update data')->flash();
        return redirect(route('salary-recap.index'));
    }

    function export(Request $request)
    {
        $sr =  $request->get('salary_recap');
        $recaps = SalaryRecap::with(['user'])
        ->where(function ($q) use ($sr){
            if($sr != null){
                $q->where('recap_month', '=', $sr);
            }
            return $q;
        })->get();

        return Excel::download(new SalaryRecapExport($recaps),"recap-$sr.xlsx");
    }

    function print(Request $request)
    {
        $ui =  $request->get('id');
        $sr =  $request->get('salary_recap');
        $recaps = SalaryRecap::with(['user'=>function($u){
            $u->with('salary');
        }, 'allowanceLines'])
            ->where(function ($q) use ($sr,$ui){
                if($sr != null){
                    $q->where('recap_month', '=', $sr);
                }
                if($ui != null){
                    $q->where('id', '=', $ui);
                }
                return $q;
            })->get();
        $recaps->map(function ($recap){
            $recap->work_in_month = app(SalaryService::class)->workdayInAMonth($recap);
            return $recap;
        });
        $company = CompanyProfile::first();

        // Periksa nilai mentahnya dulu: Storage::path("public/") pada image kosong
        // mengembalikan path direktori yang panjangnya bukan nol, sehingga guard
        // lolos dan dompdf memanggil getimagesize() pada sebuah direktori.
        $isCompanyImage = filled($company?->image);
        if ($isCompanyImage) {
            $logoPath = Storage::path("public/{$company->image}");
            $isCompanyImage = is_file($logoPath);   // berkas bisa saja sudah terhapus
            $company->image = $logoPath;
        }

        $pdf  = Pdf::loadView('salary-recap.print',compact('recaps','isCompanyImage','company'));
        $pdf->setPaper([0,0,350,500],'P');
        return $pdf->stream('rekap-gaji.pdf');
    }
}
