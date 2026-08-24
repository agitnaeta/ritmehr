<?php

namespace App\Http\Controllers\Admin;

use App\Models\Department;
use App\Models\Position;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

class PositionCrudController extends CrudController
{
    use ListOperation;
    use CreateOperation { store as traitStore; }
    use UpdateOperation { update as traitUpdate; }
    use DeleteOperation;
    use ShowOperation;

    public function setup()
    {
        CRUD::setModel(Position::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/position');
        CRUD::setEntityNameStrings('jabatan', 'jabatan');

        // Manager boleh melihat jabatan, tidak boleh mengubahnya.
        if (! backpack_user()->can('org.edit')) {
            CRUD::denyAccess(['create', 'update', 'delete']);
        }
    }

    protected function setupListOperation()
    {
        CRUD::column('name')->label('Jabatan');
        CRUD::column('level')->label('Level');

        CRUD::addColumn([
            'name'      => 'department_id',
            'label'     => 'Departemen',
            'type'      => 'select',
            'entity'    => 'department',
            'attribute' => 'name',
            'model'     => Department::class,
        ]);

        CRUD::addColumn([
            'name'   => 'users',
            'label'  => 'Jumlah Karyawan',
            'type'   => 'relationship_count',
            'suffix' => ' orang',
        ]);

        $this->crud->orderBy('level', 'desc');
    }

    protected function setupCreateOperation()
    {
        CRUD::field('name')->label('Nama Jabatan')->type('text');

        CRUD::addField([
            'name'    => 'level',
            'label'   => 'Level',
            'type'    => 'number',
            'default' => 0,
            'hint'    => 'Angka lebih besar = jabatan lebih tinggi. Dipakai untuk urutan hierarki.',
        ]);

        CRUD::addField([
            'name'        => 'department_id',
            'label'       => 'Departemen',
            'type'        => 'select',
            'entity'      => 'department',
            'attribute'   => 'name',
            'model'       => Department::class,
            'allows_null' => true,
        ]);
    }

    protected function setupUpdateOperation()
    {
        $this->setupCreateOperation();
    }

    protected function setupShowOperation()
    {
        $this->setupListOperation();
    }

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
            'name'  => 'required|string|max:100',
            'level' => 'nullable|integer|min:0|max:100',
        ], [
            'name.required' => 'Nama jabatan wajib diisi.',
        ]);
    }
}
