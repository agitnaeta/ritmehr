<?php

namespace App\Http\Controllers\Admin;

use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\LeaveService;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Validation\Rule;
use Prologue\Alerts\Facades\Alert;

class LeaveBalanceCrudController extends CrudController
{
    use ListOperation;
    use \App\Traits\HasSimpleFilters;
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

        // `remaining` sengaja tidak divalidasi — kolom generated
        // (quota + carry_over - used) dan tidak bisa ditulis.
        request()->validate([
            'user_id' => [
                'required', 'exists:users,id',
                // Satu saldo per karyawan + jenis cuti + tahun.
                Rule::unique('leave_balances', 'user_id')
                    ->where(fn ($q) => $q
                        ->where('leave_type_id', request()->input('leave_type_id'))
                        ->where('year', request()->input('year')))
                    ->ignore($id),
            ],
            'leave_type_id' => 'required|exists:leave_types,id',
            'year'          => 'required|integer|min:2000|max:2100',
            'quota'         => 'required|integer|min:0|max:365',
            'carry_over'    => 'nullable|integer|min:0|max:365',
            'used'          => 'nullable|integer|min:0|max:365',
        ], [
            'user_id.required'       => 'Karyawan wajib dipilih.',
            'user_id.unique'         => 'Saldo cuti untuk karyawan, jenis, dan tahun ini sudah ada.',
            'leave_type_id.required' => 'Jenis cuti wajib dipilih.',
            'year.required'          => 'Tahun wajib diisi.',
            'quota.required'         => 'Jatah hari wajib diisi.',
            'quota.min'              => 'Jatah hari tidak boleh negatif.',
            'used.min'               => 'Hari terpakai tidak boleh negatif.',
        ]);
    }

    public function setup()
    {
        CRUD::setModel(LeaveBalance::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/leave-balance');
        CRUD::setEntityNameStrings('saldo cuti', 'saldo cuti');
        CRUD::addClause('with', ['user', 'leaveType']);
    }

    protected function setupListOperation()
    {
        CRUD::addColumn([
            'name' => 'user_id', 'label' => 'Karyawan', 'type' => 'select',
            'entity' => 'user', 'attribute' => 'name', 'model' => User::class,
        ]);

        CRUD::addColumn([
            'name' => 'leave_type_id', 'label' => 'Jenis Cuti', 'type' => 'select',
            'entity' => 'leaveType', 'attribute' => 'name', 'model' => LeaveType::class,
        ]);

        CRUD::column('year')->label('Tahun');
        CRUD::column('quota')->label('Jatah');
        CRUD::column('carry_over')->label('Sisa Tahun Lalu');
        CRUD::column('used')->label('Terpakai');

        CRUD::addColumn([
            'name'     => 'remaining',
            'label'    => 'Sisa',
            'type'     => 'closure',
            'function' => fn (LeaveBalance $e) => $e->remainingDays() . ' hari',
        ]);

        $this->applySimpleFilters([
            [
                'name'    => 'year',
                'label'   => 'Tahun',
                'type'    => 'select',
                'options' => $this->yearOptions(),
            ],
            [
                'name'    => 'user_id',
                'label'   => 'Karyawan',
                'type'    => 'select',
                'options' => User::orderBy('name')->pluck('name', 'id')->toArray(),
            ],
            [
                'name'    => 'leave_type_id',
                'label'   => 'Jenis Cuti',
                'type'    => 'select',
                'options' => LeaveType::orderBy('name')->pluck('name', 'id')->toArray(),
            ],
        ]);

        $this->crud->orderBy('year', 'desc');

        $this->crud->allowAccess('generate_balances');
        $this->crud->addButtonFromView('top', 'generate_balances', 'generate_balances', 'end');
    }

    protected function setupCreateOperation()
    {
        CRUD::addField([
            'name' => 'user_id', 'label' => 'Karyawan', 'type' => 'select',
            'entity' => 'user', 'attribute' => 'name', 'model' => User::class,
        ]);

        CRUD::addField([
            'name' => 'leave_type_id', 'label' => 'Jenis Cuti', 'type' => 'select',
            'entity' => 'leaveType', 'attribute' => 'name', 'model' => LeaveType::class,
        ]);

        CRUD::addField([
            'name' => 'year', 'label' => 'Tahun', 'type' => 'number',
            'default' => (int) now()->year,
        ]);

        CRUD::field('quota')->label('Jatah Hari')->type('number')->default(0);
        CRUD::field('carry_over')->label('Sisa Tahun Lalu')->type('number')->default(0);
        CRUD::field('used')->label('Terpakai')->type('number')->default(0)
            ->hint('Biasanya dihitung otomatis saat cuti disetujui.');
    }

    protected function setupUpdateOperation()
    {
        $this->setupCreateOperation();
    }

    /**
     * Bulk-create balances for everyone for a given year.
     */
    public function generate(LeaveService $leaveService)
    {
        $year = (int) (request()->input('year') ?: now()->year);

        if ($year < 2000 || $year > 2100) {
            Alert::error('Tahun tidak valid.')->flash();

            return back();
        }

        $created = $leaveService->generateYearlyBalances($year);

        Alert::success("{$created} saldo cuti dibuat untuk tahun {$year}.")->flash();

        return back();
    }

    /**
     * Roll last year's unused days into this year.
     */
    public function carryOver(LeaveService $leaveService)
    {
        $from = (int) (request()->input('from_year') ?: now()->year - 1);
        $to = (int) (request()->input('to_year') ?: now()->year);
        $max = request()->input('max_carry');

        if ($from >= $to) {
            Alert::error('Tahun asal harus lebih kecil dari tahun tujuan.')->flash();

            return back();
        }

        $applied = $leaveService->carryOver($from, $to, $max !== null && $max !== '' ? (int) $max : null);

        Alert::success("Carry-over diterapkan ke {$applied} saldo ({$from} → {$to}).")->flash();

        return back();
    }

    private function yearOptions(): array
    {
        $current = (int) now()->year;
        $years = [];

        for ($y = $current + 1; $y >= $current - 3; $y--) {
            $years[$y] = (string) $y;
        }

        return $years;
    }
}
