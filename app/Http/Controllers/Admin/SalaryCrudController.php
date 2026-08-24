<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\SalaryRequest;
use App\Models\Loan;
use App\Models\Salary;
use App\Models\User;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Prologue\Alerts\Facades\Alert;

/**
 * Class SalaryCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class SalaryCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    protected $entityField = [
        'name'=>'user_id',
        'entity'=>'user',
        'model'=>User::class,
        'attribute'=>'name',
        'type'=>'select',
        'label'=>'Nama Karyawan'
    ];

    /**
     * Configure the CrudPanel object. Apply settings to all operations.
     *
     * @return void
     */
    public function setup()
    {
        CRUD::setModel(\App\Models\Salary::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/salary');
        CRUD::setEntityNameStrings('Gaji', 'Gaji');

        // Manager boleh melihat komponen gaji, tidak boleh mengubahnya.
        if (! backpack_user()->can('salary.edit')) {
            CRUD::denyAccess(['create', 'update', 'delete']);
        }
    }

    protected function setupShowOperation()
    {
        $this->autoSetupShowOperation();
        CRUD::setFromDB();
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

        /**
         * Columns can be defined using the fluent syntax:
         * - CRUD::column('price')->type('number');
         */
        $this->crud->addColumn($this->entityField)->beforeColumn('amount');
        $this->fieldModification();
    }


    /**
     * Define what happens when the Create operation is loaded.
     *
     * @see https://backpackforlaravel.com/docs/crud-operation-create
     * @return void
     */
    protected function setupCreateOperation()
    {
        CRUD::setValidation(SalaryRequest::class);
        CRUD::setFromDb(); // set fields from db columns.

        /**
         * Fields can be defined using the fluent syntax:
         * - CRUD::field('price')->type('number');
         */
        $this->fieldModification();
    }

    public function fieldModification(){

        $this->crud->field($this->entityField)->beforeColumn('amount');
        $this->crud->field(
            [
                'name'        => 'overtime_type',
                'label'       => 'Jenis Lembur',
                'type'        => 'radio',
                'options'     => [
                    'hour' => 'Per-Jam',
                    'flat' => 'Flat'
                ]
            ]
        );

        $this->crud->field(
            [
                'name'        => 'fine_type',
                'label'       => 'Jenis Denda Telat',
                'type'        => 'radio',
                'required' => true,
                'options'=>[
                        'minute' => 'Per-Menit',
                        'flat' => 'Flat',
                ],
                'value'=>$this->crud->getCurrentEntry()->fine_type ?? ''
            ]
        );

        $fields = [
            'unpaid_leave_deduction' => ['Besaran Potongan Absen', 'Rp'],
            'amount' => ['Besaran Gaji', 'Rp'],
            'overtime_amount' => ['Besaran 1x Lembur', 'Rp'],
            'fine_per_minute' => ['Denda Per-Menit', 'Rp.'],
            'fine' => ['Besaran Denda Telat - Flat', 'Rp.'],
            'extra_time' => ['Besaran lebih waktu (per-menit)', 'Rp.'],
            'extra_time_rule' => ['Aturan Lebih Waktu', ''],
        ];

        foreach ($fields as $fieldName => [$label, $prefix]) {
            $this->crud->field($fieldName)
                ->label($label)
                ->prefix($prefix);
        }



        // kolom
        $this->crud->removeColumn('user_id');
        $this->crud->addColumn($this->entityField)->makeFirstColumn();

        $fields = [
            'amount' => ['Gaji', 'Rp.',2],
            'overtime_amount' => ['1x Lembur', 'Rp.',3],
            'overtime_type' => ['Tipe Lembur', '',4],
            'fine_type' => ['Jenis Denda', '',5],
            'fine_per_minute' => ['Denda Per-Menit', 'Rp.',6],
            'fine' => ['Denda Flat','Rp.',7],
            'unpaid_leave_deduction' => ['Potongan Absen', 'Rp.',8],
            'extra_time' => ['Besaran lebih waktu (per-menit)', 'Rp.',9],
            'extra_time_rule' => ['Aturan Lebih Waktu', '',10],
        ];

        foreach ($fields as $fieldName => [$label, $prefix,$prior]) {
            $this->crud->column($fieldName)
                ->label($label)
                ->priority($prior)
                ->prefix($prefix);
        }
        if($this->crud->getCurrentOperation() != 'show'){
            $this->crud->removeColumn('fine');
        }

    }

    public function autoSetupShowOperation()
    {
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
        $id = $this->crud->getCurrentEntryId();
        CRUD::setValidation((new SalaryRequest())->rulesUpdate($id));
        CRUD::setFromDb(); // set fields from db columns.

        /**
         * Fields can be defined using the fluent syntax:
         * - CRUD::field('price')->type('number');
         */
        $this->fieldModification();
    }
    public function store()
    {
        //  'user_id', 'amount', 'overtime_amount', 'overtime_type',

        $request = $this->crud->validateRequest();
        Salary::create($request->all());
        Alert::add('success', 'Berhasil input Gaji')->flash();
        return redirect(route('salary.index'));
    }

    public function update()
    {
        $request = $this->crud->validateRequest();
        $salary = Salary::find($this->crud->getCurrentEntryId());
        $salary->update($request->all());
        Alert::add('success', 'Berhasil input Gaji')->flash();
        return redirect(route('salary.index'));
    }
}
