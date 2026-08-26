<?php

namespace App\Observers;

use App\Models\EmployeeSalaryAllowance;
use App\Models\Salary;

/**
 * M20 — Keep salaries.amount in sync (= basic_salary + Σ active allowances)
 * whenever an employee's allowance is added, changed, or removed.
 */
class EmployeeSalaryAllowanceObserver
{
    public function saved(EmployeeSalaryAllowance $allowance): void
    {
        $this->resync($allowance->user_id);
    }

    public function deleted(EmployeeSalaryAllowance $allowance): void
    {
        $this->resync($allowance->user_id);
    }

    private function resync($userId): void
    {
        $salary = Salary::where('user_id', $userId)->first();
        $salary?->recalcTotal();
    }
}
