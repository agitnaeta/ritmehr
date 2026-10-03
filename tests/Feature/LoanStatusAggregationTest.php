<?php

namespace Tests\Feature;

use App\Models\Loan;
use App\Models\LoanPayment;
use App\Models\User;
use App\Repositories\LoanRepository;
use App\Services\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Only approved loans are real debt. Pending / rejected / cancelled requests
 * must never leak into balances, reports or repayment validation.
 */
class LoanStatusAggregationTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $name = 'Staff'): User
    {
        return User::create([
            'name'     => $name,
            'email'    => str($name)->slug() . '@example.test',
            'password' => bcrypt('secret'),
        ]);
    }

    private function loan(User $user, int $amount, string $status): Loan
    {
        return Loan::create([
            'user_id' => $user->id,
            'amount'  => $amount,
            'date'    => now()->toDateString(),
            'status'  => $status,
        ]);
    }

    /** One approved loan plus one of every non-debt status. */
    private function mixedLoans(User $user): void
    {
        $this->loan($user, 1_000_000, Loan::STATUS_APPROVED);
        $this->loan($user, 5_000_000, Loan::STATUS_PENDING);
        $this->loan($user, 7_000_000, Loan::STATUS_REJECTED);
        $this->loan($user, 9_000_000, Loan::STATUS_CANCELLED);
    }

    public function test_loans_created_without_a_status_are_approved(): void
    {
        $user = $this->user();

        $loan = Loan::create(['user_id' => $user->id, 'amount' => 100_000, 'date' => now()->toDateString()]);

        $this->assertSame(Loan::STATUS_APPROVED, $loan->fresh()->status);
        $this->assertSame(1, Loan::approved()->count());
    }

    public function test_approved_and_pending_scopes_split_by_status(): void
    {
        $this->mixedLoans($this->user());

        $this->assertSame(1_000_000, (int) Loan::approved()->sum('amount'));
        $this->assertSame(5_000_000, (int) Loan::pending()->sum('amount'));
    }

    public function test_dashboard_month_snapshot_ignores_non_approved_loans(): void
    {
        $user = $this->user();
        $this->mixedLoans($user);
        LoanPayment::create(['user_id' => $user->id, 'amount' => 400_000, 'date' => now()->toDateString()]);

        $snapshot = app(DashboardService::class)->monthSnapshot();

        $this->assertSame(600_000, $snapshot['loan_outstanding']);
    }

    public function test_dashboard_loan_report_ignores_non_approved_loans(): void
    {
        $user = $this->user();
        $this->mixedLoans($user);

        $rows = app(DashboardService::class)->loanReport();

        $this->assertCount(1, $rows);
        $this->assertSame(1_000_000, $rows->first()['borrowed']);
        $this->assertSame(1_000_000, $rows->first()['outstanding']);
    }

    public function test_dashboard_loan_report_omits_users_with_only_a_pending_request(): void
    {
        $this->loan($this->user(), 5_000_000, Loan::STATUS_PENDING);

        $this->assertCount(0, app(DashboardService::class)->loanReport());
    }

    public function test_loan_recap_ignores_non_approved_loans(): void
    {
        $user = $this->user();
        $this->mixedLoans($user);
        LoanPayment::create(['user_id' => $user->id, 'amount' => 250_000, 'date' => now()->toDateString()]);

        $row = LoanRepository::recap()->firstWhere('id', $user->id);

        $this->assertSame(1_000_000, (int) $row->kasbon);
        $this->assertSame(750_000, (int) $row->selisih);
    }

    public function test_loan_detail_ignores_non_approved_loans(): void
    {
        $user = $this->user();
        $this->mixedLoans($user);

        $detail = LoanRepository::detail($user);

        $this->assertCount(1, $detail['loan']);
        $this->assertSame(1_000_000, (int) $detail['total']);
    }

    public function test_portal_outstanding_balance_ignores_non_approved_loans(): void
    {
        $user = $this->user();
        $this->mixedLoans($user);
        LoanPayment::create(['user_id' => $user->id, 'amount' => 300_000, 'date' => now()->toDateString()]);

        $this->actingAs($user, config('backpack.base.guard'))
            ->get('/my/loan')
            ->assertOk()
            ->assertViewHas('outstanding', 700_000);
    }

    public function test_portal_total_card_counts_only_approved_loans(): void
    {
        $user = $this->user();
        $this->loan($user, 1_234_000, Loan::STATUS_APPROVED);
        $this->loan($user, 8_888_000, Loan::STATUS_PENDING);

        $this->actingAs($user, config('backpack.base.guard'))
            ->get('/my/loan')
            ->assertOk()
            ->assertSee('1.234.000')
            // the pending request is still listed for the employee, but not summed
            ->assertSee('8.888.000')
            ->assertDontSee('10.122.000')
            ->assertViewHas('loans', fn ($loans) => $loans->count() === 2);
    }

    public function test_admin_delete_guard_ignores_non_approved_loans(): void
    {
        $guard = 'backpack';
        $perm = \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'loan.delete', 'guard_name' => $guard]);
        $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => $guard]);
        $role->givePermissionTo($perm);
        $admin = $this->user('Admin');
        $admin->assignRole($role);

        $staff = $this->user('Staff');
        $target = $this->loan($staff, 1_000_000, Loan::STATUS_APPROVED);
        $this->loan($staff, 500_000, Loan::STATUS_APPROVED);
        // A pending request must not count as "other debt" that the
        // payments could be re-attributed to.
        $this->loan($staff, 5_000_000, Loan::STATUS_PENDING);
        LoanPayment::create(['user_id' => $staff->id, 'amount' => 1_000_000, 'date' => now()->toDateString()]);

        $this->actingAs($admin, $guard)
            ->delete(backpack_url('loan/' . $target->id))
            ->assertStatus(422);

        $this->assertNotNull($target->fresh());
    }

    public function test_repayment_validation_ignores_non_approved_loans(): void
    {
        $user = $this->user();
        // Only a pending request exists: there is nothing to repay yet.
        $this->loan($user, 5_000_000, Loan::STATUS_PENDING);

        $request = new \App\Http\Requests\LoanPaymentRequest();
        $request->merge(['user_id' => $user->id, 'amount' => 100_000, 'date' => now()->toDateString()]);

        $validator = validator(
            ['amount' => 100_000],
            ['amount' => $request->rules()['amount']]
        );

        $this->assertTrue($validator->fails());
        $this->assertStringContainsString(
            'tidak punya sisa kasbon',
            implode(' ', $validator->errors()->get('amount'))
        );
    }
}
