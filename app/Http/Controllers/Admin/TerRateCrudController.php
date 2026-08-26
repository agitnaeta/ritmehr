<?php

namespace App\Http\Controllers\Admin;

use App\Models\TerRate;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

/**
 * M19 — CRUD for PPh 21 TER effective-rate brackets. Read-mostly reference data;
 * create/edit gated by the recruitment/tax permission at the route level.
 */
class TerRateCrudController extends CrudController
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
            'category'    => 'required|in:A,B,C',
            'lower_bound' => 'required|integer|min:0',
            'upper_bound' => 'nullable|integer|gt:lower_bound',
            'rate'        => 'required|numeric|min:0|max:100',
        ], [
            'year.required'        => 'Tahun wajib diisi.',
            'category.required'    => 'Kategori (A/B/C) wajib diisi.',
            'lower_bound.required' => 'Batas bawah wajib diisi.',
            'upper_bound.gt'       => 'Batas atas harus lebih besar dari batas bawah.',
            'rate.required'        => 'Tarif wajib diisi.',
            'rate.max'             => 'Tarif tidak boleh lebih dari 100%.',
        ]);
    }

    public function setup()
    {
        CRUD::setModel(TerRate::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/ter-rate');
        CRUD::setEntityNameStrings('tarif TER', 'Tarif TER (PPh 21)');
    }

    protected function setupListOperation()
    {
        CRUD::column('year')->label('Tahun');
        CRUD::addColumn([
            'name' => 'category', 'label' => 'Kategori',
            'type' => 'closure',
            'function' => fn (TerRate $e) => 'TER ' . $e->category,
        ]);
        CRUD::addColumn([
            'name'     => 'lower_bound', 'label' => 'Batas Bawah',
            'type'     => 'closure',
            'function' => fn (TerRate $e) => money($e->lower_bound),
        ]);
        CRUD::addColumn([
            'name'     => 'upper_bound', 'label' => 'Batas Atas',
            'type'     => 'closure',
            'function' => fn (TerRate $e) => $e->upper_bound === null ? 'Tak terbatas' : money($e->upper_bound),
        ]);
        CRUD::addColumn([
            'name' => 'rate', 'label' => 'Tarif Efektif', 'type' => 'number', 'suffix' => ' %',
        ]);

        $this->crud->orderBy('year', 'desc')->orderBy('category')->orderBy('lower_bound');
    }

    protected function setupCreateOperation()
    {
        CRUD::field('year')->label('Tahun')->type('number')->default((int) now()->year);
        CRUD::addField([
            'name' => 'category', 'label' => 'Kategori', 'type' => 'select_from_array',
            'options' => ['A' => 'TER A (TK/0, TK/1, K/0)', 'B' => 'TER B (TK/2, TK/3, K/1, K/2)', 'C' => 'TER C (K/3)'],
        ]);
        CRUD::field('lower_bound')->label('Batas Bawah (Rp)')->type('number');
        CRUD::field('upper_bound')->label('Batas Atas (Rp)')->type('number')
            ->hint('Kosongkan untuk lapisan tertinggi.');
        CRUD::addField([
            'name' => 'rate', 'label' => 'Tarif Efektif (%)', 'type' => 'number',
            'attributes' => ['step' => '0.01'],
        ]);
    }

    protected function setupUpdateOperation()
    {
        $this->setupCreateOperation();
    }
}
