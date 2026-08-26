<?php
// M20 browser-test helper. Usage: php tests/browser/_m20_helper.php <action> [args]
require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$action = $argv[1] ?? '';
$a = array_slice($argv, 2);

switch ($action) {
    case 'type_id':   // <label>
        echo \App\Models\SalaryAllowanceType::where('label', $a[0])->value('id');
        break;
    case 'salary_user': // first user_id that has a salary
        echo \App\Models\Salary::query()->value('user_id');
        break;
    case 'amount':    // <userId>
        echo (int) \App\Models\Salary::where('user_id', $a[0])->value('amount');
        break;
    case 'basic':     // <userId>
        echo (int) \App\Models\Salary::where('user_id', $a[0])->value('basic_salary');
        break;
    case 'cleanup':   // <typeId> <userId>
        \App\Models\EmployeeSalaryAllowance::where('salary_allowance_type_id', (int) $a[0])->delete();
        \App\Models\SalaryAllowanceType::where('id', (int) $a[0])->delete();
        $s = \App\Models\Salary::where('user_id', $a[1])->first();
        if ($s) { $s->recalcTotal(); }
        echo 'cleaned';
        break;
    default:
        echo 'unknown';
}
