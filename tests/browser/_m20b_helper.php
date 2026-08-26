<?php
// M20b browser-test helper. Usage: php tests/browser/_m20b_helper.php <action> [args]
require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$action = $argv[1] ?? '';
$a = array_slice($argv, 2);

switch ($action) {
    case 'seed_type': // <label> → id
        echo \App\Models\SalaryAllowanceType::create(['label' => $a[0], 'is_active' => true])->id;
        break;
    case 'type_id':
        echo \App\Models\SalaryAllowanceType::where('label', $a[0])->value('id');
        break;
    case 'salary_id_for_user': // <userId>
        echo (int) \App\Models\Salary::where('user_id', $a[0])->value('id');
        break;
    case 'user_with_salary': // returns "userId:salaryId"
        $s = \App\Models\Salary::query()->first();
        echo $s ? ($s->user_id . ':' . $s->id) : '0:0';
        break;
    case 'amount': // <userId>
        echo (int) \App\Models\Salary::where('user_id', $a[0])->value('amount');
        break;
    case 'basic': // <userId>
        echo (int) \App\Models\Salary::where('user_id', $a[0])->value('basic_salary');
        break;
    case 'has_allowance': // <userId> <typeId> → amount or ''
        echo (int) \App\Models\EmployeeSalaryAllowance::where('user_id', $a[0])
            ->where('salary_allowance_type_id', $a[1])->value('amount');
        break;
    case 'cleanup': // <typeId>
        \App\Models\EmployeeSalaryAllowance::where('salary_allowance_type_id', (int) $a[0])->delete();
        \App\Models\SalaryAllowanceType::where('id', (int) $a[0])->delete();
        foreach (\App\Models\Salary::all() as $s) { $s->recalcTotal(); }
        echo 'cleaned';
        break;
    default:
        echo 'unknown';
}
