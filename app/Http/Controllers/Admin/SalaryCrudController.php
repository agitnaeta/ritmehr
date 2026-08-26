<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\SalaryRequest;
use App\Models\Loan;
use App\Models\Salary;
use App\Models\User;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Prologue\Alerts\Facades\Alert;

/**
 * Class SalaryCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class SalaryCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    protected $entityField = [
        'name'=>'user_id',
        'entity'=>'user',
        'model'=>User::class,
        'attribute'=>'name',
        'type'=>'select',
        'label'=>'Nama Karyawan'
    ];

    /**
     * Configure the CrudPanel object. Apply settings to all operations.
     *
     * @return void
     */
    public function setup()
    {
        CRUD::setModel(\App\Models\Salary::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/salary');
        CRUD::setEntityNameStrings('Gaji', 'Gaji');

        // Manager boleh melihat komponen gaji, tidak boleh mengubahnya.
        if (! backpack_user()->can('salary.edit')) {
            CRUD::denyAccess(['create', 'update', 'delete']);
        }
    }

    protected function setupShowOperation()
    {
        $this->autoSetupShowOperation();
        CRUD::setFromDB();
        $this->fieldModification();
    }

    /**
     * M20b — Clean, report-style salary detail instead of the raw column dump.
     */
    public function show($id)
    {
        $this->crud->hasAccessOrFail('show');

        $salary = Salary::with('user')->findOrFail($id);
        $allowances = \App\Models\EmployeeSalaryAllowance::with('type')
            ->where('user_id', $salary->user_id)
            ->get()
            ->filter(fn ($a) => $a->type && $a->type->is_active)
            ->values();

        return view('admin.salary.show', [
            'crud'       => $this->crud,
            'salary'     => $salary,
            'allowances' => $allowances,
            'title'      => 'Detail Gaji — ' . ($salary->user->name ?? '-'),
        ]);
    }

    /**
     * Define what happens when the List operation is loaded.
     *
     * @see  https://backpackforlaravel.com/docs/crud-operation-list-entries
     * @return void
     */
    protected function setupListOperation()
    {
        CRUD::setFromDb(); // set columns from db columns.

        /**
         * Columns can be defined using the fluent syntax:
         * - CRUD::column('price')->type('number');
         */
        $this->crud->addColumn($this->entityField)->beforeColumn('amount');
        $this->fieldModification();
    }


    /**
     * Define what happens when the Create operation is loaded.
     *
     * @see https://backpackforlaravel.com/docs/crud-operation-create
     * @return void
     */
    protected function setupCreateOperation()
    {
        CRUD::setValidation(SalaryRequest::class);
        CRUD::setFromDb(); // set fields from db columns.

        /**
         * Fields can be defined using the fluent syntax:
         * - CRUD::field('price')->type('number');
         */
        $this->fieldModification();
    }

    public function fieldModification(){

        $this->crud->field($this->entityField)->beforeColumn('amount');
        $this->crud->field(
            [
                'name'        => 'overtime_type',
                'label'       => 'Jenis Lembur',
                'type'        => 'radio',
                'options'     => [
                    'hour' => 'Per-Jam',
                    'flat' => 'Flat'
                ]
            ]
        );

        $this->crud->field(
            [
                'name'        => 'fine_type',
                'label'       => 'Jenis Denda Telat',
                'type'        => 'radio',
                'required' => true,
                'options'=>[
                        'minute' => 'Per-Menit',
                        'flat' => 'Flat',
                ],
                'value'=>$this->crud->getCurrentEntry()->fine_type ?? ''
            ]
        );

        $cur = app(\App\Services\CurrencyService::class)->symbol();
        $fields = [
            'basic_salary' => ['Gaji Pokok', $cur],
            'unpaid_leave_deduction' => ['Besaran Potongan Absen', $cur],
            'amount' => ['Total Gaji (otomatis: pokok + tunjangan)', $cur],
            'overtime_amount' => ['Besaran 1x Lembur', $cur],
            'fine_per_minute' => ['Denda Per-Menit', $cur],
            'fine' => ['Besaran Denda Telat - Flat', $cur],
            'extra_time' => ['Besaran lebih waktu (per-menit)', $cur],
            'extra_time_rule' => ['Aturan Lebih Waktu', ''],
        ];

        foreach ($fields as $fieldName => [$label, $prefix]) {
            $this->crud->field($fieldName)
                ->label($label)
                ->prefix($prefix);
        }

        // M20 — amount is auto-maintained (basic + Σ allowances); make it read-only
        // in the form so HR edits basic salary + allowances, not the total directly.
        if ($this->crud->getCurrentOperation() !== 'show') {
            $this->crud->field('amount')
                ->attributes(['readonly' => 'readonly'])
                ->hint('Terisi otomatis dari Gaji Pokok + total Tunjangan karyawan.');
        }



        // kolom
        $this->crud->removeColumn('user_id');
        $this->crud->addColumn($this->entityField)->makeFirstColumn();

        $fields = [
            'amount' => ['Gaji', $cur,2],
            'overtime_amount' => ['1x Lembur', $cur,3],
            'overtime_type' => ['Tipe Lembur', '',4],
            'fine_type' => ['Jenis Denda', '',5],
            'fine_per_minute' => ['Denda Per-Menit', $cur,6],
            'fine' => ['Denda Flat',$cur,7],
            'unpaid_leave_deduction' => ['Potongan Absen', $cur,8],
            'extra_time' => ['Besaran lebih waktu (per-menit)', $cur,9],
            'extra_time_rule' => ['Aturan Lebih Waktu', '',10],
        ];

        foreach ($fields as $fieldName => [$label, $prefix,$prior]) {
            $this->crud->column($fieldName)
                ->label($label)
                ->priority($prior)
                ->prefix($prefix);
        }
        if($this->crud->getCurrentOperation() != 'show'){
            $this->crud->removeColumn('fine');
        }

        $this->renderAllowanceFields();
    }

    /**
     * M20b — Render one number input per active allowance type on the create/edit
     * form, prefilled with the employee's existing values on edit. Blank/0 removes
     * the allowance on save (see syncAllowancesFromRequest).
     */
    protected function renderAllowanceFields(): void
    {
        $op = $this->crud->getCurrentOperation();
        if (! in_array($op, ['create', 'update'], true)) {
            return;
        }

        $types = \App\Models\SalaryAllowanceType::active()
            ->orderBy('sort_order')->orderBy('label')->get();

        if ($types->isEmpty()) {
            return;
        }

        $existing = [];
        if ($op === 'update' && ($entry = $this->crud->getCurrentEntry())) {
            $existing = \App\Models\EmployeeSalaryAllowance::where('user_id', $entry->user_id)
                ->pluck('amount', 'salary_allowance_type_id')->toArray();
        }

        $this->crud->addField([
            'name'  => 'allowance_section',
            'type'  => 'custom_html',
            'value' => '<h5 class="mt-3 mb-0">Tunjangan</h5>'
                . '<small class="text-muted">Kosongkan bila tidak ada. Total gaji otomatis = gaji pokok + tunjangan.</small>',
        ])->afterField('basic_salary');

        // Chain each allowance field after the previous so the block sits between
        // "Gaji Pokok" and "Total Gaji".
        $prev = 'allowance_section';
        $cur = app(\App\Services\CurrencyService::class)->symbol();
        foreach ($types as $t) {
            $name = "allowance[{$t->id}]";
            $this->crud->addField([
                'name'       => $name,
                'label'      => $t->label,
                'type'       => 'number',
                'prefix'     => $cur,
                'value'      => $existing[$t->id] ?? null,
                'wrapper'    => ['class' => 'form-group col-md-6'],
                'attributes' => ['min' => 0, 'step' => 1000, 'placeholder' => '0', 'class' => 'form-control js-allowance'],
            ])->afterField($prev);
            $prev = $name;
        }

        // Live total calculation in the UI (Total = Gaji Pokok + Σ Tunjangan),
        // so HR sees the total update as they type — not only after saving.
        $this->crud->addField([
            'name'  => 'allowance_livecalc',
            'type'  => 'custom_html',
            'value' => <<<'HTML'
<script>
(function () {
    function parseNum(el) {
        if (!el) return 0;
        var v = parseInt(String(el.value).replace(/[^0-9]/g, ''), 10);
        return isNaN(v) ? 0 : v;
    }
    function recalc() {
        var basic = parseNum(document.querySelector('[name="basic_salary"]'));
        var total = basic;
        document.querySelectorAll('.js-allowance').forEach(function (el) { total += parseNum(el); });
        var amt = document.querySelector('[name="amount"]');
        if (amt) amt.value = total;
    }
    function bind() {
        var basic = document.querySelector('[name="basic_salary"]');
        if (!basic) { setTimeout(bind, 200); return; }
        basic.addEventListener('input', recalc);
        document.querySelectorAll('.js-allowance').forEach(function (el) {
            el.addEventListener('input', recalc);
        });
        recalc();
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bind);
    } else { bind(); }
})();
</script>
HTML,
        ])->afterField('amount');
    }

    public function autoSetupShowOperation()
    {
        $this->fieldModification();
    }

    /**
     * Define what happens when the Update operation is loaded.
     *
     * @see https://backpackforlaravel.com/docs/crud-operation-update
     * @return void
     */
    protected function setupUpdateOperation()
    {
        $id = $this->crud->getCurrentEntryId();
        CRUD::setValidation((new SalaryRequest())->rulesUpdate($id));
        CRUD::setFromDb(); // set fields from db columns.

        /**
         * Fields can be defined using the fluent syntax:
         * - CRUD::field('price')->type('number');
         */
        $this->fieldModification();
    }
    public function store()
    {
        //  'user_id', 'amount', 'overtime_amount', 'overtime_type',

        $request = $this->crud->validateRequest();
        $salary = Salary::create($request->except('allowance'));
        $this->syncAllowancesFromRequest($salary->user_id, (array) $request->input('allowance', []));
        Alert::add('success', 'Berhasil input Gaji')->flash();
        return redirect(route('salary.index'));
    }

    public function update()
    {
        $request = $this->crud->validateRequest();
        $salary = Salary::find($this->crud->getCurrentEntryId());
        $salary->update($request->except('allowance'));
        $this->syncAllowancesFromRequest($salary->user_id, (array) $request->input('allowance', []));
        Alert::add('success', 'Berhasil input Gaji')->flash();
        return redirect(route('salary.index'));
    }

    /**
     * M20b — Upsert per-employee allowances from the salary form. Blank/0 removes
     * the row. The EmployeeSalaryAllowance observer then recalcs salaries.amount.
     */
    public function syncAllowancesFromRequest(int $userId, array $allowance): void
    {
        foreach ($allowance as $typeId => $amount) {
            $typeId = (int) $typeId;
            $amount = (int) $amount;

            if ($amount > 0) {
                \App\Models\EmployeeSalaryAllowance::updateOrCreate(
                    ['user_id' => $userId, 'salary_allowance_type_id' => $typeId],
                    ['amount' => $amount],
                );
            } else {
                \App\Models\EmployeeSalaryAllowance::where('user_id', $userId)
                    ->where('salary_allowance_type_id', $typeId)
                    ->delete();
            }
        }

        // Make sure the total is fresh even if nothing triggered the observer.
        optional(Salary::where('user_id', $userId)->first())->recalcTotal();
    }
}
