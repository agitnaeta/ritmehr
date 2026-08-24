<?php

namespace App\Http\Controllers\Admin;

use App\Models\LeaveType;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

class LeaveTypeCrudController extends CrudController
{
    use ListOperation;
    use CreateOperation { store as traitStore; }
    use UpdateOperation { update as traitUpdate; }
    use DeleteOperation;
    use ShowOperation;

    public function setup()
    {
        CRUD::setModel(LeaveType::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/leave-type');
        CRUD::setEntityNameStrings('jenis cuti', 'jenis cuti');
    }

    protected function setupListOperation()
    {
        CRUD::column('name')->label('Nama');
        CRUD::column('code')->label('Kode');
        CRUD::column('is_paid')->label('Dibayar')->type('boolean');

        CRUD::addColumn([
            'name'     => 'default_quota',
            'label'    => 'Jatah/Tahun',
            'type'     => 'closure',
            'function' => fn (LeaveType $e) => $e->default_quota === null
                ? 'Tanpa kuota'
                : $e->default_quota . ' hari',
        ]);

        CRUD::column('requires_attachment')->label('Wajib Lampiran')->type('boolean');
        CRUD::column('is_active')->label('Aktif')->type('boolean');
    }

    protected function setupCreateOperation()
    {
        CRUD::field('name')->label('Nama')->type('text');
        CRUD::field('code')->label('Kode')->type('text')
            ->hint('Huruf kecil tanpa spasi, mis. annual, sick, unpaid.');

        CRUD::field('is_paid')->label('Cuti Dibayar')->type('checkbox')->default(true)
            ->hint('Jika tidak dicentang, hari cuti akan memotong gaji.');

        CRUD::addField([
            'name'  => 'default_quota',
            'label' => 'Jatah Default per Tahun',
            'type'  => 'number',
            'hint'  => 'Kosongkan untuk jenis tanpa kuota (mis. sakit).',
        ]);

        CRUD::addField([
            'name'  => 'max_consecutive_days',
            'label' => 'Maks. Hari Berturut-turut',
            'type'  => 'number',
            'hint'  => 'Kosongkan jika tidak dibatasi.',
        ]);

        CRUD::field('requires_attachment')->label('Wajib Lampiran')->type('checkbox');
        CRUD::field('is_active')->label('Aktif')->type('checkbox')->default(true);
        CRUD::field('color')->label('Warna Kalender')->type('color')->default('#3498db');
    }

    protected function setupUpdateOperation()
    {
        $this->setupCreateOperation();
    }

    protected function setupShowOperation()
    {
        $this->setupListOperation();
        CRUD::column('max_consecutive_days')->label('Maks. Berturut-turut');
        CRUD::column('color')->label('Warna');
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
            'name'                 => 'required|string|max:100',
            'code'                 => 'required|string|max:20|unique:leave_types,code' . ($id ? ",{$id}" : ''),
            'default_quota'        => 'nullable|integer|min:0|max:365',
            'max_consecutive_days' => 'nullable|integer|min:1|max:365',
        ], [
            'name.required' => 'Nama jenis cuti wajib diisi.',
            'code.required' => 'Kode wajib diisi.',
            'code.unique'   => 'Kode ini sudah dipakai jenis cuti lain.',
        ]);
    }
}
