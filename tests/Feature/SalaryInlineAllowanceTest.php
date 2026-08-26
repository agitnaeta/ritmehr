<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\SalaryCrudController;
use App\Models\EmployeeSalaryAllowance;
use App\Models\Salary;
use App\Models\SalaryAllowanceType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * M20b — Inline allowance editing on the salary form.
 */
class SalaryInlineAllowanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_creates_updates_and_deletes_allowance_rows(): void
    {
        $user = User::factory()->create();
        Salary::create(['user_id' => $user->id, 'basic_salary' => 8_000_000, 'overtime_amount' => 0, 'overtime_type' => 'flat']);
        $jab = SalaryAllowanceType::create(['label' => 'Jabatan']);
        $trans = SalaryAllowanceType::create(['label' => 'Transport']);

        $ctrl = new SalaryCrudController();

        // create two
        $ctrl->syncAllowancesFromRequest($user->id, [$jab->id => 1_500_000, $trans->id => 500_000]);
        $this->assertSame(10_000_000, (int) Salary::where('user_id', $user->id)->value('amount'));
        $this->assertSame(2, EmployeeSalaryAllowance::where('user_id', $user->id)->count());

        // update one, blank the other → deleted
        $ctrl->syncAllowancesFromRequest($user->id, [$jab->id => 2_000_000, $trans->id => 0]);
        $this->assertSame(10_000_000, (int) Salary::where('user_id', $user->id)->value('amount'));
        $this->assertDatabaseMissing('employee_salary_allowances', [
            'user_id' => $user->id, 'salary_allowance_type_id' => $trans->id,
        ]);
        $this->assertSame(1, EmployeeSalaryAllowance::where('user_id', $user->id)->count());
    }

    public function test_sync_with_empty_array_keeps_existing(): void
    {
        $user = User::factory()->create();
        Salary::create(['user_id' => $user->id, 'basic_salary' => 8_000_000, 'overtime_amount' => 0, 'overtime_type' => 'flat']);
        $jab = SalaryAllowanceType::create(['label' => 'Jabatan']);
        (new SalaryCrudController())->syncAllowancesFromRequest($user->id, [$jab->id => 1_000_000]);

        // empty array = no keys iterated → nothing changed
        (new SalaryCrudController())->syncAllowancesFromRequest($user->id, []);
        $this->assertSame(9_000_000, (int) Salary::where('user_id', $user->id)->value('amount'));
    }

    private function adminWithSalaryEdit(): User
    {
        $user = User::factory()->create();
        $guard = $user->guard_name ?? config('auth.defaults.guard', 'web');
        $perms = collect(['salary.view', 'salary.edit'])->map(fn ($p) =>
            Permission::firstOrCreate(['name' => $p, 'guard_name' => $guard]));
        $user->givePermissionTo($perms);

        return $user;
    }

    public function test_salary_form_post_creates_allowances_and_total(): void
    {
        $admin = $this->adminWithSalaryEdit();
        $emp = User::factory()->create();
        $jab = SalaryAllowanceType::create(['label' => 'Jabatan']);

        $this->actingAs($admin, config('backpack.base.guard'))
            ->post(backpack_url('salary'), [
                'user_id'         => (string) $emp->id,
                'basic_salary'    => 8_000_000,
                'overtime_amount' => 0,
                'overtime_type'   => 'flat',
                'fine_type'       => 'flat',
                'allowance'       => [$jab->id => 2_000_000],
            ])->assertRedirect();

        $this->assertSame(10_000_000, (int) Salary::where('user_id', $emp->id)->value('amount'));
        $this->assertDatabaseHas('employee_salary_allowances', [
            'user_id' => $emp->id, 'salary_allowance_type_id' => $jab->id, 'amount' => 2_000_000,
        ]);
    }
}
