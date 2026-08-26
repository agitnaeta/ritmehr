<?php

namespace App\Http\Controllers\Admin;

use App\Models\Kpi;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

class KpiCrudController extends CrudController
{
    use ListOperation;
    use CreateOperation { store as traitStore; }
    use UpdateOperation { update as traitUpdate; }
    use DeleteOperation;
    use ShowOperation;

    public function setup()
    {
        CRUD::setModel(Kpi::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/kpi');
        CRUD::setEntityNameStrings('KPI', 'KPI');

        if (! backpack_user()->can('performance.edit')) {
            CRUD::denyAccess(['create', 'update', 'delete']);
        }
    }

    protected function setupListOperation()
    {
        CRUD::column('name')->label('Nama KPI');
        CRUD::column('weight')->label('Bobot');
        CRUD::column('is_active')->label('Aktif')->type('boolean');
    }

    protected function setupCreateOperation()
    {
        CRUD::field('name')->label('Nama KPI')->type('text');
        CRUD::field('description')->label('Deskripsi')->type('textarea');
        CRUD::addField([
            'name' => 'weight', 'label' => 'Bobot', 'type' => 'number',
            'default' => 1, 'hint' => 'Bobot relatif untuk rata-rata tertimbang. Default 1 (setara).',
            'wrapper' => ['class' => 'form-group col-md-6'],
        ]);
        CRUD::field('is_active')->label('Aktif')->type('checkbox')->default(true);
    }

    protected function setupUpdateOperation()
    {
        $this->setupCreateOperation();
    }

    protected function setupShowOperation()
    {
        $this->setupListOperation();
        CRUD::column('description')->label('Deskripsi');
    }

    public function store()
    {
        request()->validate([
            'name'   => 'required|string|max:150',
            'weight' => 'required|integer|min:1|max:100',
        ], ['name.required' => 'Nama KPI wajib diisi.']);

        return $this->traitStore();
    }

    public function update()
    {
        request()->validate([
            'name'   => 'required|string|max:150',
            'weight' => 'required|integer|min:1|max:100',
        ], ['name.required' => 'Nama KPI wajib diisi.']);

        return $this->traitUpdate();
    }
}
