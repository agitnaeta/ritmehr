<?php

namespace App\Http\Controllers\Admin;

use App\Models\EmployeeTaxProfile;
use App\Models\User;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Validation\Rule;

class EmployeeTaxProfileCrudController extends CrudController
{
    use ListOperation;
    use CreateOperation { store as traitStore; }
    use UpdateOperation { update as traitUpdate; }
    use DeleteOperation;

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
            // Satu profil pajak per karyawan.
            'user_id' => [
                'required', 'exists:users,id',
                Rule::unique('employee_tax_profiles', 'user_id')->ignore($id),
            ],
            'tax_status' => 'required|string|max:10',
            'tax_method' => 'required|string|max:20',
            'npwp'       => 'nullable|string|max:25',
        ], [
            'user_id.required'    => 'Karyawan wajib dipilih.',
            'user_id.unique'      => 'Karyawan ini sudah punya profil pajak.',
            'tax_status.required' => 'Status PTKP wajib dipilih.',
            'tax_method.required' => 'Metode pajak wajib dipilih.',
        ]);
    }

    public function setup()
    {
        CRUD::setModel(EmployeeTaxProfile::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/tax-profile');
        CRUD::setEntityNameStrings('profil pajak', 'profil pajak');
        CRUD::addClause('with', 'user');
    }

    protected function setupListOperation()
    {
        CRUD::addColumn([
            'name' => 'user_id', 'label' => 'Karyawan', 'type' => 'select',
            'entity' => 'user', 'attribute' => 'name', 'model' => User::class,
        ]);
        CRUD::column('npwp')->label('NPWP');
        CRUD::column('tax_status')->label('Status PTKP');
        CRUD::column('tax_method')->label('Metode');
        CRUD::column('bpjs_kesehatan')->label('BPJS Kes')->type('boolean');
        CRUD::column('bpjs_ketenagakerjaan')->label('BPJS TK')->type('boolean');
    }

    protected function setupCreateOperation()
    {
        CRUD::addField([
            'name' => 'user_id', 'label' => 'Karyawan', 'type' => 'select',
            'entity' => 'user', 'attribute' => 'name', 'model' => User::class,
        ]);

        CRUD::field('npwp')->label('NPWP')->type('text')
            ->hint('Kosongkan jika belum punya — PPh 21 akan dikenakan tambahan 20%.');

        CRUD::addField([
            'name' => 'tax_status', 'label' => 'Status PTKP', 'type' => 'select_from_array',
            'options' => array_combine(
                EmployeeTaxProfile::TAX_STATUSES,
                EmployeeTaxProfile::TAX_STATUSES
            ),
            'allows_null' => false, 'default' => 'TK/0',
            'hint' => 'TK = tidak kawin, K = kawin, K/I = istri bekerja. Angka = jumlah tanggungan.',
        ]);

        CRUD::addField([
            'name' => 'tax_method', 'label' => 'Metode Pajak', 'type' => 'select_from_array',
            'options' => ['gross' => 'Gross', 'gross_up' => 'Gross Up', 'nett' => 'Nett'],
            'allows_null' => false, 'default' => 'gross',
        ]);

        foreach ([
            'bpjs_kesehatan'       => 'BPJS Kesehatan',
            'bpjs_ketenagakerjaan' => 'BPJS Ketenagakerjaan (induk)',
            'bpjs_tk_jht'          => 'JHT — Jaminan Hari Tua',
            'bpjs_tk_jp'           => 'JP — Jaminan Pensiun',
            'bpjs_tk_jkk'          => 'JKK — Jaminan Kecelakaan Kerja',
            'bpjs_tk_jkm'          => 'JKM — Jaminan Kematian',
        ] as $name => $label) {
            CRUD::field($name)->label($label)->type('checkbox')->default(true);
        }
    }

    protected function setupUpdateOperation()
    {
        $this->setupCreateOperation();
    }
}
