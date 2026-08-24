<?php

namespace App\Http\Controllers\Admin;

use App\Models\Pph21Bracket;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

class Pph21BracketCrudController extends CrudController
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
        request()->validate([
            'year'        => 'required|integer|min:2000|max:2100',
            'lower_bound' => 'required|integer|min:0',
            // Kosong berarti lapisan teratas (tanpa batas atas).
            'upper_bound' => 'nullable|integer|gt:lower_bound',
            'rate'        => 'required|numeric|min:0|max:100',
        ], [
            'year.required'        => 'Tahun wajib diisi.',
            'lower_bound.required' => 'Batas bawah wajib diisi.',
            'upper_bound.gt'       => 'Batas atas harus lebih besar dari batas bawah.',
            'rate.required'        => 'Tarif wajib diisi.',
            'rate.max'             => 'Tarif tidak boleh lebih dari 100%.',
        ]);
    }

    public function setup()
    {
        CRUD::setModel(Pph21Bracket::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/pph21-bracket');
        CRUD::setEntityNameStrings('lapisan PPh 21', 'lapisan PPh 21');
    }

    protected function setupListOperation()
    {
        CRUD::column('year')->label('Tahun');
        CRUD::addColumn([
            'name' => 'lower_bound', 'label' => 'Batas Bawah',
            'type' => 'number', 'prefix' => 'Rp ',
            'decimals' => 0, 'dec_point' => ',', 'thousands_sep' => '.',
        ]);
        CRUD::addColumn([
            'name'     => 'upper_bound',
            'label'    => 'Batas Atas',
            'type'     => 'closure',
            'function' => fn (Pph21Bracket $e) => $e->upper_bound === null
                ? 'Tak terbatas'
                : 'Rp ' . number_format($e->upper_bound, 0, ',', '.'),
        ]);
        CRUD::addColumn([
            'name' => 'rate', 'label' => 'Tarif', 'type' => 'number', 'suffix' => ' %',
        ]);

        $this->crud->orderBy('year', 'desc')->orderBy('lower_bound');
    }

    protected function setupCreateOperation()
    {
        CRUD::field('year')->label('Tahun')->type('number')->default((int) now()->year);
        CRUD::field('lower_bound')->label('Batas Bawah (Rp)')->type('number');
        CRUD::field('upper_bound')->label('Batas Atas (Rp)')->type('number')
            ->hint('Kosongkan untuk lapisan tertinggi.');
        CRUD::addField([
            'name' => 'rate', 'label' => 'Tarif (%)', 'type' => 'number',
            'attributes' => ['step' => '0.01'],
        ]);
    }

    protected function setupUpdateOperation()
    {
        $this->setupCreateOperation();
    }
}
