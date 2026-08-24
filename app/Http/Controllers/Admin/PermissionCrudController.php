<?php

namespace App\Http\Controllers\Admin;

use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

class PermissionCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    public function setup()
    {
        CRUD::setModel(\App\Models\Permission::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/permission');
        CRUD::setEntityNameStrings('permission', 'permissions');

        // Granted to super_admin only — see RolesAndPermissionsSeeder.
        if (! backpack_user()->can('permission.view')) {
            abort(403, 'Only super admin can view permissions.');
        }
    }

    protected function setupListOperation()
    {
        CRUD::column('id')->type('number')->label('ID');
        CRUD::column('name')->type('text')->label('Permission');
        CRUD::column('guard_name')->type('text')->label('Guard');
        CRUD::column('created_at')->type('datetime');
    }
}
