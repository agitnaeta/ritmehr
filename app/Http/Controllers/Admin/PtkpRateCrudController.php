<?php

namespace App\Http\Controllers\Admin;

use App\Models\EmployeeTaxProfile;
use App\Models\PtkpRate;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Validation\Rule;

class PtkpRateCrudController extends CrudController
{
    use ListOperation;
    use CreateOperation { store as traitStore; }
    use UpdateOperation { update as traitUpdate; }
    use DeleteOperation;

    public function store()
    {
        $this->validatePayload();

        return $this->traitStore();
    }

    public function update()
    {
        $this->validatePayload();

        return $this->traitUpdate();
    }

    private function validatePayload(): void
    {
        $id = request()->input('id');

        request()->validate([
            'year'   => [
                'required', 'integer', 'min:2000', 'max:2100',
                // Satu tarif per kombinasi tahun + status PTKP.
                Rule::unique('ptkp_rates', 'year')
                    ->where(fn ($q) => $q->where('status', request()->input('status')))
                    ->ignore($id),
            ],
            'status' => 'required|string|max:10',
            'amount' => 'required|integer|min:0',
        ], [
            'year.required'   => 'Tahun wajib diisi.',
            'year.unique'     => 'Tarif PTKP untuk tahun dan status ini sudah ada.',
            'status.required' => 'Status PTKP wajib diisi.',
            'amount.required' => 'Jumlah PTKP wajib diisi.',
            'amount.min'      => 'Jumlah PTKP tidak boleh negatif.',
        ]);
    }

    public function setup()
    {
        CRUD::setModel(PtkpRate::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/ptkp-rate');
        CRUD::setEntityNameStrings('tarif PTKP', 'tarif PTKP');
    }

    protected function setupListOperation()
    {
        CRUD::column('year')->label('Tahun');
        CRUD::column('status')->label('Status');
        CRUD::addColumn([
            'name' => 'amount', 'label' => 'PTKP Setahun',
            'type' => 'number', 'prefix' => app(\App\Services\CurrencyService::class)->symbol() . ' ',
            'decimals' => 0, 'dec_point' => ',', 'thousands_sep' => '.',
        ]);

        $this->crud->orderBy('year', 'desc')->orderBy('status');
    }

    protected function setupCreateOperation()
    {
        CRUD::field('year')->label('Tahun')->type('number')->default((int) now()->year);

        CRUD::addField([
            'name' => 'status', 'label' => 'Status PTKP', 'type' => 'select_from_array',
            'options' => array_combine(
                EmployeeTaxProfile::TAX_STATUSES,
                EmployeeTaxProfile::TAX_STATUSES
            ),
            'allows_null' => false,
        ]);

        CRUD::field('amount')->label('Jumlah PTKP Setahun (Rp)')->type('number');
    }

    protected function setupUpdateOperation()
    {
        $this->setupCreateOperation();
    }
}
