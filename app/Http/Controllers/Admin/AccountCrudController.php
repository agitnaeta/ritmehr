<?php

namespace App\Http\Controllers\Admin;

use App\Models\Account;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

/**
 * M12 — Chart of accounts management.
 */
class AccountCrudController extends CrudController
{
    use ListOperation;
    use CreateOperation { store as traitStore; }
    use UpdateOperation { update as traitUpdate; }
    use DeleteOperation;
    use ShowOperation;

    public function setup()
    {
        CRUD::setModel(Account::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/account');
        CRUD::setEntityNameStrings('akun', 'daftar akun');

        abort_unless(backpack_user()->can('accounting.view'), 403);

        if (! backpack_user()->can('accounting.edit')) {
            CRUD::denyAccess(['create', 'update', 'delete']);
        }
    }

    private array $types = [
        Account::TYPE_ASSET     => 'Aset',
        Account::TYPE_LIABILITY => 'Kewajiban',
        Account::TYPE_EQUITY    => 'Ekuitas',
        Account::TYPE_INCOME    => 'Pendapatan',
        Account::TYPE_EXPENSE   => 'Beban',
    ];

    protected function setupListOperation()
    {
        CRUD::column('code')->label('Kode');
        CRUD::column('name')->label('Nama Akun');
        CRUD::addColumn([
            'name'  => 'type',
            'label' => 'Tipe',
            'type'  => 'closure',
            'function' => fn ($e) => $this->types[$e->type] ?? $e->type,
        ]);
        CRUD::addColumn([
            'name'  => 'role',
            'label' => 'Peran Posting',
            'type'  => 'closure',
            'function' => fn ($e) => $e->roleLabel() ?? '—',
        ]);
        CRUD::addColumn([
            'name'  => 'is_cash',
            'label' => 'Kas/Bank',
            'type'  => 'closure',
            'function' => fn ($e) => $e->is_cash ? '✓' : '',
        ]);
        CRUD::addColumn([
            'name'  => 'saldo',
            'label' => 'Saldo',
            'type'  => 'closure',
            'function' => fn ($e) => 'Rp ' . number_format($e->balance(), 0, ',', '.'),
        ]);
        CRUD::column('is_active')->label('Aktif')->type('boolean');
        $this->crud->orderBy('code');
    }

    protected function setupCreateOperation()
    {
        CRUD::field('code')->label('Kode Akun')->type('text');
        CRUD::field('name')->label('Nama Akun')->type('text');
        CRUD::addField([
            'name'    => 'type',
            'label'   => 'Tipe Akun',
            'type'    => 'select_from_array',
            'options' => $this->types,
        ]);
        CRUD::addField([
            'name'        => 'role',
            'label'       => 'Peran Posting Otomatis',
            'type'        => 'select_from_array',
            'options'     => Account::roleOptions(),
            'allows_null' => true,
            'hint'        => 'Opsional. Akun dengan peran ini dipakai otomatis saat posting gaji/kasbon. Satu peran cukup satu akun.',
        ]);
        CRUD::field('is_active')->label('Aktif')->type('checkbox')->default(true);
        CRUD::field('is_cash')->label('Akun Kas/Bank? (bisa dipakai bayar/terima uang)')->type('checkbox');
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
        $id = request()->input('id');
        request()->validate([
            'code' => 'required|string|max:20|unique:accounts,code' . ($id ? ",{$id}" : ''),
            'name' => 'required|string|max:150',
            'type' => 'required|in:asset,liability,equity,income,expense',
        ], [
            'code.required' => 'Kode akun wajib diisi.',
            'code.unique'   => 'Kode akun sudah dipakai.',
            'name.required' => 'Nama akun wajib diisi.',
        ]);
    }
}
