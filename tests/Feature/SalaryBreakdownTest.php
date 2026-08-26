<?php

namespace Tests\Feature;

use App\Models\EmployeeSalaryAllowance;
use App\Models\Salary;
use App\Models\SalaryAllowanceType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * M20-2 — salaries.amount stays = basic_salary + Σ active allowances.
 */
class SalaryBreakdownTest extends TestCase
{
    use RefreshDatabase;

    private function salaryFor(User $user, int $basic): Salary
    {
        return Salary::create([
            'user_id' => $user->id,
            'basic_salary' => $basic,
            'overtime_amount' => 0, 'overtime_type' => 'flat',
        ]);
    }

    public function test_amount_equals_basic_when_no_allowances(): void
    {
        $user = User::factory()->create();
        $salary = $this->salaryFor($user, 8_000_000);

        $this->assertSame(8_000_000, (int) $salary->fresh()->amount);
    }

    public function test_amount_includes_active_allowances(): void
    {
        $user = User::factory()->create();
        $this->salaryFor($user, 8_000_000);

        $jabatan = SalaryAllowanceType::create(['label' => 'Tunjangan Jabatan']);
        $transport = SalaryAllowanceType::create(['label' => 'Transport']);

        EmployeeSalaryAllowance::create(['user_id' => $user->id, 'salary_allowance_type_id' => $jabatan->id, 'amount' => 1_500_000]);
        EmployeeSalaryAllowance::create(['user_id' => $user->id, 'salary_allowance_type_id' => $transport->id, 'amount' => 500_000]);

        // 8jt + 1.5jt + 0.5jt = 10jt
        $this->assertSame(10_000_000, (int) Salary::where('user_id', $user->id)->value('amount'));
    }

    public function test_amount_updates_when_allowance_changed_or_deleted(): void
    {
        $user = User::factory()->create();
        $this->salaryFor($user, 8_000_000);
        $type = SalaryAllowanceType::create(['label' => 'Transport']);
        $a = EmployeeSalaryAllowance::create(['user_id' => $user->id, 'salary_allowance_type_id' => $type->id, 'amount' => 500_000]);

        $this->assertSame(8_500_000, (int) Salary::where('user_id', $user->id)->value('amount'));

        $a->update(['amount' => 700_000]);
        $this->assertSame(8_700_000, (int) Salary::where('user_id', $user->id)->value('amount'));

        $a->delete();
        $this->assertSame(8_000_000, (int) Salary::where('user_id', $user->id)->value('amount'));
    }

    public function test_inactive_allowance_type_excluded(): void
    {
        $user = User::factory()->create();
        $this->salaryFor($user, 8_000_000);
        $type = SalaryAllowanceType::create(['label' => 'Bonus Musiman', 'is_active' => true]);
        EmployeeSalaryAllowance::create(['user_id' => $user->id, 'salary_allowance_type_id' => $type->id, 'amount' => 1_000_000]);

        $this->assertSame(9_000_000, (int) Salary::where('user_id', $user->id)->value('amount'));

        // Deactivate the type → recalc excludes it.
        $type->update(['is_active' => false]);
        Salary::where('user_id', $user->id)->first()->recalcTotal();

        $this->assertSame(8_000_000, (int) Salary::where('user_id', $user->id)->value('amount'));
    }

    public function test_changing_basic_salary_updates_total(): void
    {
        $user = User::factory()->create();
        $salary = $this->salaryFor($user, 8_000_000);
        $type = SalaryAllowanceType::create(['label' => 'Transport']);
        EmployeeSalaryAllowance::create(['user_id' => $user->id, 'salary_allowance_type_id' => $type->id, 'amount' => 500_000]);

        $salary->refresh();
        $salary->basic_salary = 9_000_000;
        $salary->save();

        $this->assertSame(9_500_000, (int) $salary->fresh()->amount);
    }
}
