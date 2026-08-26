<?php

namespace App\Http\Controllers\Admin;

use App\Models\EmployeeSalaryAllowance;
use App\Models\SalaryAllowanceType;
use App\Models\User;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

/**
 * M20 — Per-employee allowance values. Assigning a value here auto-updates the
 * employee's salary total (via EmployeeSalaryAllowanceObserver).
 */
class EmployeeSalaryAllowanceCrudController extends CrudController
{
    use ListOperation;
    use DeleteOperation;
    use CreateOperation { store as traitStore; }
    use UpdateOperation { update as traitUpdate; }

    public function setup()
    {
        CRUD::setModel(EmployeeSalaryAllowance::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/employee-salary-allowance');
        CRUD::setEntityNameStrings('tunjangan karyawan', 'Tunjangan Karyawan');

        if (! backpack_user()->can('salary.edit')) {
            CRUD::denyAccess(['create', 'update', 'delete']);
        }
    }

    protected function setupListOperation()
    {
        CRUD::addColumn(['name' => 'user_id', 'label' => 'Karyawan', 'type' => 'select',
            'entity' => 'user', 'attribute' => 'name', 'model' => User::class]);
        CRUD::addColumn(['name' => 'salary_allowance_type_id', 'label' => 'Jenis Tunjangan', 'type' => 'select',
            'entity' => 'type', 'attribute' => 'label', 'model' => SalaryAllowanceType::class]);
        CRUD::addColumn(['name' => 'amount', 'label' => 'Nominal', 'type' => 'number',
            'prefix' => app(\App\Services\CurrencyService::class)->symbol() . ' ',
            'decimals' => 0, 'dec_point' => ',', 'thousands_sep' => '.']);
    }

    protected function setupCreateOperation()
    {
        CRUD::addField(['name' => 'user_id', 'label' => 'Karyawan', 'type' => 'select',
            'entity' => 'user', 'attribute' => 'name', 'model' => User::class]);
        CRUD::addField(['name' => 'salary_allowance_type_id', 'label' => 'Jenis Tunjangan', 'type' => 'select',
            'entity' => 'type', 'attribute' => 'label', 'model' => SalaryAllowanceType::class]);
        CRUD::field('amount')->label('Nominal (Rp)')->type('number');
    }

    protected function setupUpdateOperation()
    {
        $this->setupCreateOperation();
    }

    public function store()
    {
        request()->validate([
            'user_id' => 'required|exists:users,id',
            'salary_allowance_type_id' => 'required|exists:salary_allowance_types,id',
            'amount' => 'required|integer|min:0',
        ]);

        $this->traitStore();
        \Prologue\Alerts\Facades\Alert::success('Tunjangan karyawan berhasil disimpan.')->flash();

        return redirect(route('employee-salary-allowance.index'));
    }

    public function update()
    {
        request()->validate([
            'amount' => 'required|integer|min:0',
        ]);

        $this->traitUpdate();
        \Prologue\Alerts\Facades\Alert::success('Tunjangan karyawan berhasil diperbarui.')->flash();

        return redirect(route('employee-salary-allowance.index'));
    }
}
