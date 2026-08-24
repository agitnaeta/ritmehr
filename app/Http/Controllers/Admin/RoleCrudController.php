<?php

namespace App\Http\Controllers\Admin;

use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

class RoleCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    public function setup()
    {
        CRUD::setModel(\App\Models\Role::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/role');
        CRUD::setEntityNameStrings('role', 'roles');

        // Granted to super_admin only — see RolesAndPermissionsSeeder.
        if (! backpack_user()->can('role.edit')) {
            abort(403, 'Only super admin can manage roles.');
        }
    }

    protected function setupListOperation()
    {
        CRUD::column('name')->type('text')->label('Role Name');
        CRUD::column('guard_name')->type('text')->label('Guard');
        CRUD::addColumn([
            'name'      => 'permissions',
            'label'     => 'Permissions',
            'type'      => 'relationship_count',
            'suffix'    => ' permissions',
        ]);
        CRUD::column('created_at')->type('datetime');
    }

    protected function setupCreateOperation()
    {
        CRUD::field('name')->type('text')->label('Role Name');
        CRUD::field('guard_name')->type('text')->label('Guard')->default('web');
        CRUD::addField([
            'label'     => 'Permissions',
            'type'      => 'select_multiple',
            'name'      => 'permissions',
            'entity'    => 'permissions',
            'model'     => \App\Models\Permission::class,
            'attribute' => 'name',
            'pivot'     => true,
        ]);
    }

    protected function setupUpdateOperation()
    {
        $this->setupCreateOperation();
    }
}
