<?php

namespace App\Http\Controllers\Admin;

use App\Models\BpjsRate;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Validation\Rule;

class BpjsRateCrudController extends CrudController
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
            'year' => [
                'required', 'integer', 'min:2000', 'max:2100',
                // Satu tarif per kombinasi tahun + jenis iuran.
                Rule::unique('bpjs_rates', 'year')
                    ->where(fn ($q) => $q->where('type', request()->input('type')))
                    ->ignore($id),
            ],
            'type'          => 'required|string|max:30',
            'employee_rate' => 'required|numeric|min:0|max:100',
            'employer_rate' => 'required|numeric|min:0|max:100',
            // Kosong berarti tanpa plafon, seperti JHT.
            'max_salary'    => 'nullable|integer|min:0',
        ], [
            'year.required'          => 'Tahun wajib diisi.',
            'year.unique'            => 'Tarif untuk tahun dan jenis iuran ini sudah ada.',
            'type.required'          => 'Jenis iuran wajib diisi.',
            'employee_rate.required' => 'Persentase karyawan wajib diisi.',
            'employer_rate.required' => 'Persentase pemberi kerja wajib diisi.',
            'employee_rate.max'      => 'Persentase tidak boleh lebih dari 100.',
            'employer_rate.max'      => 'Persentase tidak boleh lebih dari 100.',
        ]);
    }

    public function setup()
    {
        CRUD::setModel(BpjsRate::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/bpjs-rate');
        CRUD::setEntityNameStrings('tarif BPJS', 'tarif BPJS');
    }

    protected function setupListOperation()
    {
        CRUD::column('year')->label('Tahun');
        CRUD::column('type')->label('Jenis');
        CRUD::addColumn(['name' => 'employer_rate', 'label' => 'Perusahaan', 'type' => 'number', 'suffix' => ' %']);
        CRUD::addColumn(['name' => 'employee_rate', 'label' => 'Karyawan', 'type' => 'number', 'suffix' => ' %']);
        CRUD::addColumn([
            'name'     => 'max_salary',
            'label'    => 'Batas Upah',
            'type'     => 'closure',
            'function' => fn (BpjsRate $e) => $e->max_salary === null
                ? 'Tanpa batas'
                : 'Rp ' . number_format($e->max_salary, 0, ',', '.'),
        ]);

        $this->crud->orderBy('year', 'desc')->orderBy('type');
    }

    protected function setupCreateOperation()
    {
        CRUD::field('year')->label('Tahun')->type('number')->default((int) now()->year);

        CRUD::addField([
            'name' => 'type', 'label' => 'Jenis', 'type' => 'select_from_array',
            'options' => [
                BpjsRate::TYPE_KESEHATAN => 'Kesehatan',
                BpjsRate::TYPE_JHT       => 'JHT — Jaminan Hari Tua',
                BpjsRate::TYPE_JP        => 'JP — Jaminan Pensiun',
                BpjsRate::TYPE_JKK       => 'JKK — Jaminan Kecelakaan Kerja',
                BpjsRate::TYPE_JKM       => 'JKM — Jaminan Kematian',
            ],
            'allows_null' => false,
        ]);

        CRUD::addField([
            'name' => 'employer_rate', 'label' => 'Ditanggung Perusahaan (%)',
            'type' => 'number', 'attributes' => ['step' => '0.01'],
        ]);
        CRUD::addField([
            'name' => 'employee_rate', 'label' => 'Dipotong dari Karyawan (%)',
            'type' => 'number', 'attributes' => ['step' => '0.01'],
        ]);
        CRUD::field('max_salary')->label('Batas Upah Perhitungan (Rp)')->type('number')
            ->hint('Kosongkan jika tidak ada batas atas.');
    }

    protected function setupUpdateOperation()
    {
        $this->setupCreateOperation();
    }
}
