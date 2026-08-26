<?php

namespace App\Http\Controllers\Admin;

use App\Models\SalaryAllowanceType;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Prologue\Alerts\Facades\Alert;

/**
 * M20 — Master list of salary allowance types (global, free label).
 */
class SalaryAllowanceTypeCrudController extends CrudController
{
    use ListOperation;
    use DeleteOperation;
    use CreateOperation { store as traitStore; }
    use UpdateOperation { update as traitUpdate; }

    public function setup()
    {
        CRUD::setModel(SalaryAllowanceType::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/salary-allowance-type');
        CRUD::setEntityNameStrings('jenis tunjangan', 'Jenis Tunjangan');

        if (! backpack_user()->can('salary.edit')) {
            CRUD::denyAccess(['create', 'update', 'delete']);
        }
    }

    public function store()
    {
        request()->validate(['label' => 'required|string|max:255']);
        $this->traitStore();
        Alert::success('Jenis tunjangan berhasil disimpan.')->flash();

        return redirect(route('salary-allowance-type.index'));
    }

    public function update()
    {
        request()->validate(['label' => 'required|string|max:255']);
        $this->traitUpdate();
        Alert::success('Jenis tunjangan berhasil diperbarui.')->flash();

        return redirect(route('salary-allowance-type.index'));
    }

    protected function setupListOperation()
    {
        CRUD::column('label')->label('Nama Tunjangan');
        CRUD::addColumn(['name' => 'is_active', 'label' => 'Aktif', 'type' => 'boolean']);
        CRUD::column('sort_order')->label('Urutan');
        $this->crud->orderBy('sort_order')->orderBy('label');
    }

    protected function setupCreateOperation()
    {
        CRUD::field('label')->label('Nama Tunjangan (bebas)')->type('text')
            ->hint('Contoh: Tunjangan Jabatan, Transport, Uang Makan.');
        CRUD::field('sort_order')->label('Urutan Tampil')->type('number')->default(0);
        CRUD::field('is_active')->label('Aktif')->type('checkbox')->default(true);
    }

    protected function setupUpdateOperation()
    {
        $this->setupCreateOperation();
    }
}
