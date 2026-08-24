<?php

namespace App\Http\Controllers\Admin\Operations;

use App\Services\TransactionService;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Prologue\Alerts\Facades\Alert;
use App\Services\SalaryService;

trait SetPaymentOperation
{
    /**
     * Define which routes are needed for this operation.
     *
     * @param string $segment    Name of the current entity (singular). Used as first URL segment.
     * @param string $routeName  Prefix of the route name.
     * @param string $controller Name of the current CrudController.
     */
    protected function setupSetPaymentRoutes($segment, $routeName, $controller)
    {
        // POST, bukan GET: operasi ini mengubah data. Sebagai GET ia bisa terpicu
        // prefetch browser atau crawler yang menelusuri tautan.
        Route::post($segment.'/{id}/set-payment', [
            'as'        => $routeName.'.setPayment',
            'uses'      => $controller.'@setPayment',
            'operation' => 'setPayment',
        ]);

        Route::get($segment.'/{id}/recalculate-salary', [
            'as'        => $routeName.'.recalculateSalary',
            'uses'      => $controller.'@recalculateSalary',
            'operation' => 'recalculateSalary',
        ]);

    }

    /**
     * Add the default settings, buttons, etc that this operation needs.
     */
    protected function setupSetPaymentDefaults()
    {
        CRUD::allowAccess('setPayment');
        CRUD::allowAccess('set_payment_cash');
        CRUD::allowAccess('set_payment_transfer');
        CRUD::allowAccess('recalculate_salary');

        CRUD::operation('setPayment', function () {
            CRUD::loadDefaultOperationSettingsFromConfig();
        });

        CRUD::operation('list', function () {
            // CRUD::addButton('top', 'set_payment', 'view', 'crud::buttons.set_payment');
             CRUD::addButton('line', 'set_payment_cash', 'view', 'crud::buttons.set_payment_cash');
             CRUD::addButton('line', 'set_payment_transfer', 'view', 'crud::buttons.set_payment_transfer');
             CRUD::addButton('line', 'recalculate_salary', 'view', 'crud::buttons.recalculate_salary');
        });
    }

    /**
     * Show the view for performing the operation.
     *
     * @return Response
     */
    public function setPayment()
    {
        $data = $this->crud->getRequest()->validate([
            'method' => 'required|in:cash,transfer',
        ], [
            'method.required' => 'Metode pembayaran wajib dipilih.',
            'method.in'       => 'Metode pembayaran harus cash atau transfer.',
        ]);

        $recap = $this->crud->getCurrentEntry();
        abort_if(! $recap, 404);

        if ($recap->paid) {
            Alert::add('warning', '<strong>Dilewati</strong><br>Rekap gaji ini sudah dibayar.')->flash();

            return redirect(route('salary-recap.index'));
        }

        // Satu transaksi: jangan sampai rekap tertandai lunas sementara
        // pencatatan ke akuntansi gagal.
        DB::transaction(function () use ($recap, $data) {
            $recap->forceFill([
                'paid'   => 1,
                'method' => $data['method'],
            ])->saveQuietly();

            app(TransactionService::class)->updateRecordSalaryToACC($recap);
        });

        Alert::add('success', '<strong>Berhasil</strong><br>Berhasil bayar secara '.$recap->method)->flash();
        return redirect(route('salary-recap.index'));
    }


    public function recalculateSalary(){
        $recap = $this->crud->getCurrentEntry();
        app(SalaryService::class)->calculateSalaryRecap($recap);
        Alert::add('success', '<strong>Berhasil</strong><br>Berhasil update perhitungan gaji')->flash();
        return redirect(route('salary-recap.index'));
    }


}
