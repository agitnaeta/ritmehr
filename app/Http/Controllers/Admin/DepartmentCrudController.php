<?php

namespace App\Http\Controllers\Admin;

use App\Models\Department;
use App\Models\User;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Prologue\Alerts\Facades\Alert;

class DepartmentCrudController extends CrudController
{
    use ListOperation;
    use CreateOperation { store as traitStore; }
    use UpdateOperation { update as traitUpdate; }
    use DeleteOperation;
    use ShowOperation;

    public function setup()
    {
        CRUD::setModel(Department::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/department');
        CRUD::setEntityNameStrings('departemen', 'departemen');

        // Manager boleh melihat struktur organisasi, tidak boleh mengubahnya.
        if (! backpack_user()->can('org.edit')) {
            CRUD::denyAccess(['create', 'update', 'delete']);
        }
    }

    protected function setupListOperation()
    {
        CRUD::column('code')->label('Kode');

        CRUD::addColumn([
            'name'     => 'name',
            'label'    => 'Departemen',
            'type'     => 'closure',
            'function' => fn (Department $e) => $e->fullPath(),
        ]);

        CRUD::addColumn([
            'name'      => 'head_user_id',
            'label'     => 'Kepala',
            'type'      => 'select',
            'entity'    => 'head',
            'attribute' => 'name',
            'model'     => User::class,
        ]);

        CRUD::addColumn([
            'name'   => 'users',
            'label'  => 'Jumlah Karyawan',
            'type'   => 'relationship_count',
            'suffix' => ' orang',
        ]);
    }

    protected function setupCreateOperation()
    {
        CRUD::field('name')->label('Nama Departemen')->type('text');
        CRUD::field('code')->label('Kode')->type('text')->hint('Contoh: IT, HR, FIN');

        CRUD::addField([
            'name'        => 'parent_id',
            'label'       => 'Induk Departemen',
            'type'        => 'select',
            'entity'      => 'parent',
            'attribute'   => 'name',
            'model'       => Department::class,
            'allows_null' => true,
            'hint'        => 'Kosongkan jika ini departemen tingkat atas.',
        ]);

        CRUD::addField([
            'name'        => 'head_user_id',
            'label'       => 'Kepala Departemen',
            'type'        => 'select',
            'entity'      => 'head',
            'attribute'   => 'name',
            'model'       => User::class,
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
        CRUD::column('created_at')->label('Dibuat');
    }

    public function store()
    {
        $this->validatePayload();

        return $this->traitStore();
    }

    public function update()
    {
        $this->validatePayload();

        $id = request()->input('id');
        $parentId = request()->input('parent_id');

        // A department that is its own ancestor makes fullPath() and the org
        // chart loop forever, so refuse the edit outright.
        if ($id && $parentId && ($dept = Department::find($id)) && $dept->wouldCycle($parentId)) {
            Alert::error('Induk departemen tidak valid — akan membentuk lingkaran hierarki.')->flash();

            return redirect()->back()->withInput();
        }

        return $this->traitUpdate();
    }

    private function validatePayload(): void
    {
        $id = request()->input('id');

        request()->validate([
            'name' => 'required|string|max:100',
            'code' => 'nullable|string|max:20|unique:departments,code' . ($id ? ",{$id}" : ''),
        ], [
            'name.required' => 'Nama departemen wajib diisi.',
            'code.unique'   => 'Kode departemen sudah dipakai.',
        ]);
    }

    /**
     * Tree visualisation of the whole org.
     */
    public function orgChart()
    {
        return view('admin.department.org_chart', [
            'tree'      => Department::tree(),
            'unassigned' => User::employed()->whereNull('department_id')->orderBy('name')->get(),
        ]);
    }
}
