<?php

namespace App\Http\Controllers\Admin;

use App\Exports\LoanDetailExport;
use App\Exports\LoanExport;
use App\Http\Requests\LoanRequest;
use App\Models\Loan;
use App\Models\LoanPayment;
use App\Models\User;
use App\Repositories\LoanRepository;
use App\Services\TransactionService;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Barryvdh\DomPDF\Facade\Pdf;
use Database\Factories\TranslateFactory;
use Maatwebsite\Excel\Facades\Excel;
use Prologue\Alerts\Facades\Alert;

/**
 * Class LoanCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class LoanCrudController extends CrudController
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
     *
     * Configure the CrudPanel object. Apply settings to all operations.
     *
     * @return void
     */
    public function setup()
    {
        CRUD::setModel(\App\Models\Loan::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/loan');
        CRUD::setEntityNameStrings('Kasbon', 'Kasbon');
        $this->crud->addClause('with','user');

        // Manager boleh melihat kasbon, tidak boleh menerbitkan atau mengubahnya.
        if (! backpack_user()->can('loan.create')) CRUD::denyAccess(['create']);
        if (! backpack_user()->can('loan.edit'))   CRUD::denyAccess(['update']);
        if (! backpack_user()->can('loan.delete')) CRUD::denyAccess(['delete']);
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
        CRUD::setValidation(LoanRequest::class);
        CRUD::setFromDb(); // set fields from db columns.

        /**
         * Fields can be defined using the fluent syntax:
         * - CRUD::field('price')->type('number');
         */
        $this->crud->field('amount')->prefix(app(\App\Services\CurrencyService::class)->symbol());
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
        $loan  = new Loan();
        $loan->user_id = $request->user_id;
        $loan->amount = $request->amount;
        $loan->date = $request->date;

        $loan->save();
        $this->transactionService->recordLoanACC($loan);
        Alert::add('success', 'Berhasil input data')->flash();
        return redirect(route('loan.index'));
    }

    public function update()
    {
        $request = $this->crud->validateRequest();
        $loan  = Loan::find($request->id);
        $loan->user_id = $request->user_id;
        $loan->amount = $request->amount;
        $loan->date = $request->date;

        $loan->save();
        $this->transactionService->updateRecordLoanACC($loan);
        Alert::add('success', 'Berhasil update data')->flash();
        return redirect(route('loan.index'));
    }

    public function loanRecap(){
        $loans = LoanRepository::recap();
        return view('loan.recap',compact('loans'));
    }

    public function detail($id){
        $user = User::find($id);
        if(!$user){
            Alert::error("User tidak ditemukan")->flash();
            return redirect(route('loan.recap'));
        }
        $loan  = LoanRepository::detail($user);
        return view('loan.detail',compact('loan','user'));
    }

    public function download(){
        return Excel::download(new LoanExport,'laporan-kasbon.xlsx');
    }
    public function downloadDetail($id){
        $entry= User::find($id);
        return Excel::download(new LoanDetailExport($entry),'laporan-kasbon.xlsx');
    }
    public function print($id){
        $user= User::find($id);
        $loan  = LoanRepository::detail($user);
        $pdf = Pdf::loadView('loan.table-detail',compact('loan','user'));
        return $pdf->stream('detail.pdf');
    }


    public function destroy($id)
    {
        CRUD::hasAccessOrFail('delete');
        $loan = Loan::find($id);
        abort_if(! $loan, 404);

        // `loan_payments` tidak punya `loan_id` — cicilan dicatat per karyawan,
        // bukan per kasbon. Jadi menghapus kasbon yang sudah dicicil membuat
        // buku pembayaran karyawan menggantung: sisanya jadi negatif, dan
        // validasi pembayaran (BUG-009) akan menolak setiap setoran berikutnya
        // sehingga karyawan terjebak tidak bisa membayar apa pun lagi.
        $kasbonLain = (int) Loan::where('user_id', $loan->user_id)
            ->where('id', '!=', $loan->id)
            ->sum('amount');
        $dibayar = (int) LoanPayment::where('user_id', $loan->user_id)->sum('amount');

        if ($dibayar > $kasbonLain) {
            $terpakai = $dibayar - $kasbonLain;

            return response()->json([
                'message' => 'Kasbon ini tidak bisa dihapus karena sudah dicicil Rp '
                    . number_format($terpakai, 0, ',', '.')
                    . '. Hapus atau sesuaikan pembayarannya lebih dulu.',
            ], 422);
        }

        $this->transactionService->deleteRecordLoanACC($loan);

        return CRUD::delete($id);
    }

}
