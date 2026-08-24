<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\LoanPaymentRequest;
use App\Models\LoanPayment;
use App\Models\User;
use App\Services\TransactionService;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Database\Factories\TranslateFactory;
use Prologue\Alerts\Facades\Alert;

/**
 * Class LoanPaymentCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class LoanPaymentCrudController extends CrudController
{
    protected $transactionService;
    public function __construct(TransactionService $transactionService) {
        parent::__construct();
        $this->transactionService = $transactionService;
    }

    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation { destroy as traitDestroy; }
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    protected $entityField = [
        'name'=>'user_id',
        'entity'=>'user',
        'model'=>User::class,
        'attribute'=>'name',
        'type'=>'select',
        'label'=>'Nama Karyawan'
    ];

    public function fieldModification(){
        $translate = new TranslateFactory();
        foreach($translate->loan() as $key => $value){
            $this->crud->field($key)->label($value);
            $this->crud->column($key)->label($value);
        }
        $this->crud->removeField('created_at');
        $this->crud->removeField('updated_at');
    }

    /**
     * Configure the CrudPanel object. Apply settings to all operations.
     *
     * @return void
     */
    public function setup()
    {
        CRUD::setModel(\App\Models\LoanPayment::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/loan-payment');
        CRUD::setEntityNameStrings('Pembayaran Kasbon ', 'Pembayaran Kasbon');
        $this->crud->addClause('with','user');

        // Manager boleh melihat pembayaran, tidak boleh mencatatnya.
        if (! backpack_user()->can('loan_payment.create')) CRUD::denyAccess(['create']);
        if (! backpack_user()->can('loan_payment.edit'))   CRUD::denyAccess(['update']);
        if (! backpack_user()->can('loan_payment.delete')) CRUD::denyAccess(['delete']);
    }
    protected function setupShowOperation()
    {
        $this->autoSetupShowOperation();
        $this->crud->addColumn($this->entityField)->beforeColumn('amount');
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
        $this->crud->removeColumn('user_id');
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
        CRUD::setValidation(LoanPaymentRequest::class);
        CRUD::setFromDb(); // set fields from db columns.

        /**
         * Fields can be defined using the fluent syntax:
         * - CRUD::field('price')->type('number');
         */
        $this->crud->field('amount')->prefix('Rp.');
        $this->crud->field($this->entityField);
        $this->fieldModification();
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
        $this->setupCreateOperation();
    }

    public function store()
    {

        $request = $this->crud->validateRequest();
        $loan  = new LoanPayment();
        $loan->user_id = $request->user_id;
        $loan->amount = $request->amount;
        $loan->date = $request->date;

        $loan->save();
        $this->transactionService->recordPayLoanACC($loan);
        Alert::add('success', 'Berhasil input data')->flash();
        return redirect(route('loan-payment.index'));
    }

    public function update()
    {
        $request = $this->crud->validateRequest();
        $loan  = LoanPayment::find($request->id);
        $loan->user_id = $request->user_id;
        $loan->amount = $request->amount;
        $loan->date = $request->date;

        $loan->save();
        $this->transactionService->updateRecordPayLoanACC($loan);
        Alert::add('success', 'Berhasil update data')->flash();
        return redirect(route('loan-payment.index'));
    }



    public function destroy($id)
    {
        CRUD::hasAccessOrFail('delete');
        $loan = LoanPayment::find($id);
        $this->transactionService->deleteRecordPayLoanAcc($loan);
        return CRUD::delete($id);
    }
}
