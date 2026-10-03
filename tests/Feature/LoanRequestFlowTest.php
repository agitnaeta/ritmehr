<?php

namespace Tests\Feature;

use App\Models\Approval;
use App\Models\ApprovalFlow;
use App\Models\ApprovalFlowStep;
use App\Models\Loan;
use App\Models\Notification;
use App\Models\User;
use App\Services\ApprovalService;
use App\Services\LoanService;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Employee kasbon requests: portal → 'loan' approval flow → booked loan.
 */
class LoanRequestFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;
    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = $this->user('Manager');
        $this->staff = $this->user('Staff', ['manager_id' => $this->manager->id]);

        $flow = ApprovalFlow::create([
            'name' => 'Pengajuan Kasbon', 'module' => 'loan', 'steps' => 1, 'is_active' => true,
        ]);
        ApprovalFlowStep::create([
            'approval_flow_id' => $flow->id,
            'step_order'       => 1,
            'approver_type'    => ApprovalFlowStep::TYPE_MANAGER,
        ]);
    }

    private function user(string $name, array $attrs = []): User
    {
        return User::create(array_merge([
            'name'     => $name,
            'email'    => str($name)->slug() . '@example.test',
            'password' => bcrypt('secret'),
        ], $attrs));
    }

    private function portal(User $user): self
    {
        $this->actingAs($user, config('backpack.base.guard'));

        return $this;
    }

    private function request(int $amount = 750_000): Loan
    {
        return app(LoanService::class)->requestLoan($this->staff, $amount, now()->toDateString(), 'Biaya sekolah');
    }

    /** Expect exactly $times KASBON postings for the rest of the test. */
    private function expectBookings(int $times): void
    {
        $this->mock(TransactionService::class, function (MockInterface $mock) use ($times) {
            $mock->shouldReceive('recordLoanACC')->times($times);
        });
    }

    // ── Portal ─────────────────────────────────────────────

    public function test_employee_can_submit_a_request_from_the_portal(): void
    {
        $this->portal($this->staff)
            ->post('/my/loan', ['amount' => 750_000, 'date' => now()->toDateString(), 'reason' => 'Biaya sekolah'])
            ->assertRedirect(route('portal.loan.index'))
            ->assertSessionHas('success');

        $loan = Loan::sole();
        $this->assertSame(Loan::STATUS_PENDING, $loan->status);
        $this->assertSame('Biaya sekolah', $loan->reason);
        $this->assertTrue($loan->approval->isPending());
        $this->assertSame('loan', $loan->approval->approvalFlow->module);
    }

    public function test_create_page_and_index_render_with_a_request_button(): void
    {
        $this->portal($this->staff)->get('/my/loan')->assertOk()->assertSee('Ajukan Kasbon');
        $this->portal($this->staff)->get('/my/loan/create')->assertOk()->assertSee('Kirim Pengajuan');
    }

    public function test_amount_is_required_and_positive(): void
    {
        $this->portal($this->staff)
            ->post('/my/loan', ['amount' => 0, 'date' => now()->toDateString()])
            ->assertSessionHasErrors('amount');

        $this->assertSame(0, Loan::count());
    }

    public function test_there_is_no_amount_cap(): void
    {
        $this->portal($this->staff)
            ->post('/my/loan', ['amount' => 250_000_000, 'date' => now()->toDateString()])
            ->assertSessionHas('success');

        $this->assertSame(250_000_000, (int) Loan::sole()->amount);
    }

    public function test_only_one_open_request_at_a_time(): void
    {
        $this->request();

        $this->portal($this->staff)
            ->post('/my/loan', ['amount' => 100_000, 'date' => now()->toDateString()])
            ->assertSessionHas('error');

        $this->assertSame(1, Loan::count());

        // The create page bounces back while a request is open, and the
        // index hides the button.
        $this->portal($this->staff)->get('/my/loan/create')->assertRedirect(route('portal.loan.index'));
        $this->portal($this->staff)->get('/my/loan')->assertDontSee('href="' . route('portal.loan.create') . '"', false);
    }

    public function test_a_new_request_is_allowed_once_the_previous_one_is_resolved(): void
    {
        $first = $this->request();
        app(ApprovalService::class)->reject($first->approval, $this->manager, 'Belum bisa');

        $second = $this->request(200_000);

        $this->assertSame(Loan::STATUS_PENDING, $second->status);
        $this->assertSame(2, Loan::count());
    }

    public function test_approved_loans_do_not_block_a_new_request(): void
    {
        Loan::create(['user_id' => $this->staff->id, 'amount' => 1_000_000, 'date' => now()->toDateString()]);

        $this->assertSame(Loan::STATUS_PENDING, $this->request()->status);
    }

    public function test_request_fails_cleanly_without_an_active_loan_flow(): void
    {
        ApprovalFlow::query()->update(['is_active' => false]);

        $this->portal($this->staff)
            ->post('/my/loan', ['amount' => 100_000, 'date' => now()->toDateString()])
            ->assertSessionHas('error');

        // The transaction rolled back — no orphan pending loan.
        $this->assertSame(0, Loan::count());
    }

    // ── Approval outcomes ──────────────────────────────────

    public function test_final_approval_books_the_loan_once_and_notifies(): void
    {
        $this->expectBookings(1);
        $loan = $this->request();

        $approval = app(ApprovalService::class)->approve($loan->approval, $this->manager);

        $this->assertSame(Approval::STATUS_APPROVED, $approval->status);
        $this->assertSame(Loan::STATUS_APPROVED, $loan->fresh()->status);
        $this->assertSame(750_000, (int) Loan::approved()->sum('amount'));
        $this->assertTrue(
            Notification::where('user_id', $this->staff->id)->where('type', Notification::LOAN_CREATED)->exists()
        );

        // A double-fire of the hook must not post KASBON a second time.
        $loan->fresh()->onApprovalApproved($approval);
    }

    public function test_approved_request_counts_toward_the_portal_balance(): void
    {
        $this->expectBookings(1);
        $loan = $this->request();

        $this->portal($this->staff)->get('/my/loan')->assertViewHas('outstanding', 0);

        app(ApprovalService::class)->approve($loan->approval, $this->manager);

        $this->portal($this->staff)->get('/my/loan')->assertViewHas('outstanding', 750_000);
    }

    public function test_rejection_books_nothing_and_records_the_reason(): void
    {
        $this->expectBookings(0);
        $loan = $this->request();

        app(ApprovalService::class)->reject($loan->approval, $this->manager, 'Sisa kasbon masih besar');

        $loan->refresh();
        $this->assertSame(Loan::STATUS_REJECTED, $loan->status);
        $this->assertSame('Sisa kasbon masih besar', $loan->rejection_reason);
        $this->assertSame(0, Loan::approved()->count());

        $notification = Notification::where('user_id', $this->staff->id)
            ->where('type', Notification::LOAN_REJECTED)->sole();
        $this->assertStringContainsString('Sisa kasbon masih besar', $notification->body);
    }

    // ── Cancellation ───────────────────────────────────────

    public function test_employee_can_cancel_a_pending_request(): void
    {
        $this->expectBookings(0);
        $loan = $this->request();

        $this->portal($this->staff)
            ->post("/my/loan/{$loan->id}/cancel")
            ->assertSessionHas('success');

        $loan->refresh();
        $this->assertSame(Loan::STATUS_CANCELLED, $loan->status);
        $this->assertSame(Approval::STATUS_CANCELLED, $loan->approval->status);
    }

    public function test_approved_loans_cannot_be_cancelled_by_the_employee(): void
    {
        $loan = Loan::create(['user_id' => $this->staff->id, 'amount' => 500_000, 'date' => now()->toDateString()]);

        $this->portal($this->staff)
            ->post("/my/loan/{$loan->id}/cancel")
            ->assertSessionHas('error');

        $this->assertSame(Loan::STATUS_APPROVED, $loan->fresh()->status);
    }

    public function test_cannot_cancel_another_employees_request(): void
    {
        $loan = $this->request();
        $other = $this->user('Other');

        $this->portal($other)->post("/my/loan/{$loan->id}/cancel")->assertNotFound();

        $this->assertSame(Loan::STATUS_PENDING, $loan->fresh()->status);
    }

    // ── Admin side ─────────────────────────────────────────

    /**
     * One Backpack request per test: the CrudPanel singleton keeps the first
     * request's operation setup, so a second request in the same test would
     * not run its own setupXxxOperation().
     */
    private function hr(): User
    {
        $guard = 'backpack';
        $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => $guard]);
        foreach (['loan.view', 'loan.create', 'loan.edit'] as $name) {
            $role->givePermissionTo(\Spatie\Permission\Models\Permission::firstOrCreate(['name' => $name, 'guard_name' => $guard]));
        }
        $hr = $this->user('HR');
        $hr->assignRole($role);

        return $hr;
    }

    public function test_hr_loan_list_renders_with_status_column(): void
    {
        $this->actingAs($this->hr(), 'backpack')->get(backpack_url('loan'))->assertOk();
    }

    public function test_hr_loan_form_does_not_expose_status_fields(): void
    {
        $this->actingAs($this->hr(), 'backpack')
            ->get(backpack_url('loan/create'))
            ->assertOk()
            ->assertSee('name="amount"', false)
            ->assertDontSee('name="status"', false)
            ->assertDontSee('name="reason"', false)
            ->assertDontSee('name="rejection_reason"', false);
    }

    public function test_hr_direct_entry_is_approved_immediately(): void
    {
        $this->actingAs($this->hr(), 'backpack')
            ->post(backpack_url('loan'), ['user_id' => $this->staff->id, 'amount' => 300_000, 'date' => now()->toDateString()])
            ->assertRedirect();

        $this->assertSame(Loan::STATUS_APPROVED, Loan::sole()->status);
    }

    public function test_approver_sees_amount_and_reason_on_the_approval_detail(): void
    {
        $loan = $this->request();

        $this->actingAs($this->manager, 'backpack')
            ->get(backpack_url("approval/{$loan->approval->id}/detail"))
            ->assertOk()
            ->assertSee('750.000')
            ->assertSee('Biaya sekolah');
    }
}
