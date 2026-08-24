<?php

namespace App\Http\Controllers\Admin;

use App\Models\ApprovalFlow;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;

class ApprovalFlowCrudController extends CrudController
{
    use ListOperation;
    use CreateOperation { store as traitStore; }
    use UpdateOperation { update as traitUpdate; }
    use DeleteOperation;
    use ShowOperation;

    /** Modul yang punya alur persetujuan. */
    public const MODULES = ['leave', 'loan', 'overtime'];

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
            'name'      => 'required|string|max:100',
            'module'    => 'required|in:' . implode(',', self::MODULES),
            'is_active' => [
                'nullable', 'boolean',
                // Satu alur aktif per modul — dijanjikan dokumentasi, kini ditegakkan.
                function ($attribute, $value, $fail) use ($id) {
                    if (! $value) {
                        return;
                    }
                    $bentrok = ApprovalFlow::where('module', request()->input('module'))
                        ->where('is_active', true)
                        ->when($id, fn ($q) => $q->where('id', '!=', $id))
                        ->exists();

                    if ($bentrok) {
                        $fail('Sudah ada alur aktif untuk modul ini. Nonaktifkan dulu alur tersebut.');
                    }
                },
            ],
        ], [
            'name.required'   => 'Nama alur wajib diisi.',
            'module.required' => 'Modul wajib dipilih.',
            'module.in'       => 'Modul tidak dikenal.',
        ]);
    }

    public function setup()
    {
        CRUD::setModel(ApprovalFlow::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/approval-flow');
        CRUD::setEntityNameStrings('alur persetujuan', 'alur persetujuan');

        if (! backpack_user()->can('approval.configure')) {
            abort(403, 'Anda tidak berhak mengubah alur persetujuan.');
        }
    }

    protected function setupListOperation()
    {
        CRUD::column('name')->label('Nama');
        CRUD::column('module')->label('Modul');
        CRUD::addColumn([
            'name'   => 'flowSteps',
            'label'  => 'Jumlah Step',
            'type'   => 'relationship_count',
            'suffix' => ' step',
        ]);
        CRUD::column('is_active')->label('Aktif')->type('boolean');
    }

    protected function setupCreateOperation()
    {
        CRUD::field('name')->label('Nama')->type('text');

        CRUD::addField([
            'name'    => 'module',
            'label'   => 'Modul',
            'type'    => 'select_from_array',
            'options' => [
                'leave'    => 'Cuti / Izin',
                'loan'     => 'Kasbon',
                'overtime' => 'Lembur',
            ],
            'allows_null' => false,
            'hint'        => 'Hanya satu alur aktif per modul yang akan dipakai.',
        ]);

        CRUD::field('is_active')->label('Aktif')->type('checkbox')->default(true);

        CRUD::addField([
            'name'  => 'steps',
            'label' => 'Jumlah Step (informasi)',
            'type'  => 'number',
            'hint'  => 'Diisi otomatis dari step yang dikonfigurasi.',
            'attributes' => ['readonly' => 'readonly'],
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
        CRUD::column('updated_at')->label('Diubah');
    }
}
