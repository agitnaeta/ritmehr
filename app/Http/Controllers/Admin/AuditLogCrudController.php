<?php

namespace App\Http\Controllers\Admin;

use App\Models\AuditLog;
use App\Models\User;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

class AuditLogCrudController extends CrudController
{
    use ListOperation;
    use ShowOperation;
    use \App\Traits\HasSimpleFilters;

    public function setup()
    {
        CRUD::setModel(AuditLog::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/audit-log');
        CRUD::setEntityNameStrings('audit log', 'audit logs');

        // Only super_admin and hr_admin can access
        $this->crud->denyAccess(['create', 'update', 'delete']);
    }

    protected function setupListOperation()
    {
        CRUD::column('id')->label('ID');
        CRUD::addColumn([
            'name'      => 'user_id',
            'label'     => 'User',
            'type'      => 'select',
            'entity'    => 'user',
            'attribute' => 'name',
            'model'     => User::class,
        ]);
        CRUD::column('action')->label('Action');
        CRUD::column('auditable_type')->label('Model Type');
        CRUD::column('auditable_id')->label('Model ID');
        CRUD::column('created_at')->label('Created At');

        $this->applySimpleFilters([
            [
                'name'    => 'action',
                'label'   => 'Aksi',
                'type'    => 'select',
                'options' => [
                    'create'  => 'Create',  'update' => 'Update', 'delete' => 'Delete',
                    'login'   => 'Login',   'logout' => 'Logout',
                    'approve' => 'Approve', 'reject' => 'Reject', 'export' => 'Export',
                ],
            ],
            [
                'name'    => 'user_id',
                'label'   => 'User',
                'type'    => 'select',
                'options' => User::orderBy('name')->pluck('name', 'id')->toArray(),
            ],
            [
                'name'  => 'from',
                'label' => 'Dari Tanggal',
                'type'  => 'date',
                'apply' => fn ($query, $value) => $query->whereDate('created_at', '>=', $value),
            ],
            [
                'name'  => 'to',
                'label' => 'Sampai Tanggal',
                'type'  => 'date',
                'apply' => fn ($query, $value) => $query->whereDate('created_at', '<=', $value),
            ],
        ]);

        // Default ordering
        $this->crud->orderBy('created_at', 'desc');
    }

    protected function setupShowOperation()
    {
        CRUD::column('id')->label('ID');
        CRUD::addColumn([
            'name'      => 'user_id',
            'label'     => 'User',
            'type'      => 'select',
            'entity'    => 'user',
            'attribute' => 'name',
            'model'     => User::class,
        ]);
        CRUD::column('action')->label('Action');
        CRUD::column('auditable_type')->label('Model Type');
        CRUD::column('auditable_id')->label('Model ID');
        CRUD::column('ip_address')->label('IP Address');
        CRUD::column('user_agent')->label('User Agent');
        CRUD::addColumn([
            'name'  => 'old_values',
            'label' => 'Old Values',
            'type'  => 'json',
        ]);
        CRUD::addColumn([
            'name'  => 'new_values',
            'label' => 'New Values',
            'type'  => 'json',
        ]);
        CRUD::column('created_at')->label('Created At');
    }
}
