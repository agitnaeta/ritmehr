<?php

namespace Tests\Feature;

use App\Models\ApprovalFlow;
use App\Models\ApprovalFlowStep;
use App\Models\Day;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\NationalHoliday;
use App\Models\Schedule;
use App\Models\ScheduleDayOff;
use App\Models\User;
use App\Services\ApprovalService;
use App\Services\LeaveService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LeaveManagementTest extends TestCase
{
    use RefreshDatabase;

    private LeaveService $leave;
    private ApprovalService $approvals;
    private Schedule $schedule;

    protected function setUp(): void
    {
        parent::setUp();

        $this->leave = app(LeaveService::class);
        $this->approvals = app(ApprovalService::class);

        $this->schedule = Schedule::create([
            'name'     => 'Reguler',
            'in'       => '08:00:00',
            'out'      => '17:00:00',
            'over_in'  => '18:00:00',
            'over_out' => '22:00:00',
        ]);

        // Saturday + Sunday off.
        foreach (['Sabtu', 'Minggu'] as $dayName) {
            $day = Day::create(['name' => $dayName]);
            ScheduleDayOff::create([
                'schedule_id' => $this->schedule->id,
                'day'         => $day->id,
            ]);
        }

        // Manager -> HR two-step flow, matching the default seeder.
        $flow = ApprovalFlow::create([
            'name' => 'Cuti', 'module' => 'leave', 'steps' => 1, 'is_active' => true,
        ]);
        ApprovalFlowStep::create([
            'approval_flow_id' => $flow->id,
            'step_order'       => 1,
            'approver_type'    => ApprovalFlowStep::TYPE_MANAGER,
        ]);
    }

    private function user(string $name, ?int $managerId = null, array $attrs = []): User
    {
        return User::create(array_merge([
            'name'        => $name,
            'email'       => str($name)->slug() . '@example.test',
            'password'    => bcrypt('secret'),
            'schedule_id' => $this->schedule->id,
            'manager_id'  => $managerId,
        ], $attrs));
    }

    private function annualType(array $overrides = []): LeaveType
    {
        return LeaveType::create(array_merge([
            'name'          => 'Cuti Tahunan',
            'code'          => 'annual',
            'is_paid'       => true,
            'default_quota' => 12,
            'is_active'     => true,
        ], $overrides));
    }

    // ── Day counting ───────────────────────────────────────

    public function test_leave_days_skip_weekends(): void
    {
        $user = $this->user('Staff');

        // Mon 2026-09-07 .. Sun 2026-09-13 => 5 working days.
        $days = $this->leave->calculateLeaveDays($user, '2026-09-07', '2026-09-13');

        $this->assertSame(5, $days);
    }

    public function test_leave_days_skip_national_holidays(): void
    {
        $user = $this->user('Staff');
        NationalHoliday::create(['date' => '2026-09-09', 'info' => 'Hari Libur']);

        $days = $this->leave->calculateLeaveDays($user, '2026-09-07', '2026-09-11');

        $this->assertSame(4, $days, 'Mon-Fri minus one holiday');
    }

    public function test_a_range_that_is_entirely_weekend_is_rejected(): void
    {
        $user = $this->user('Staff');
        $type = $this->annualType();

        $this->expectException(\DomainException::class);
        // Sat + Sun
        $this->leave->requestLeave($user, $type, '2026-09-12', '2026-09-13');
    }

    // ── Requesting ─────────────────────────────────────────

    public function test_request_creates_per_day_rows_and_enters_approval(): void
    {
        $manager = $this->user('Manager');
        $staff = $this->user('Staff', $manager->id);
        $type = $this->annualType();

        $request = $this->leave->requestLeave($staff, $type, '2026-09-07', '2026-09-09', 'Liburan');

        $this->assertSame(LeaveRequest::STATUS_PENDING, $request->status);
        $this->assertEquals(3.0, (float) $request->total_days);
        $this->assertCount(3, $request->dates);
        $this->assertNotNull($request->approval);
        $this->assertTrue($request->approval->isPending());
    }

    public function test_end_before_start_is_rejected(): void
    {
        $staff = $this->user('Staff');
        $type = $this->annualType();

        $this->expectException(\DomainException::class);
        $this->leave->requestLeave($staff, $type, '2026-09-10', '2026-09-07');
    }

    public function test_insufficient_balance_is_rejected_up_front(): void
    {
        $manager = $this->user('Manager');
        $staff = $this->user('Staff', $manager->id);
        $type = $this->annualType(['default_quota' => 2]);

        $this->expectException(\DomainException::class);
        // 5 working days against a quota of 2.
        $this->leave->requestLeave($staff, $type, '2026-09-07', '2026-09-11');
    }

    public function test_overlapping_request_is_rejected(): void
    {
        $manager = $this->user('Manager');
        $staff = $this->user('Staff', $manager->id);
        $type = $this->annualType();

        $this->leave->requestLeave($staff, $type, '2026-09-07', '2026-09-09');

        $this->expectException(\DomainException::class);
        $this->leave->requestLeave($staff, $type, '2026-09-09', '2026-09-10');
    }

    public function test_max_consecutive_days_is_enforced(): void
    {
        $manager = $this->user('Manager');
        $staff = $this->user('Staff', $manager->id);
        $type = $this->annualType(['code' => 'permission', 'max_consecutive_days' => 2]);

        $this->expectException(\DomainException::class);
        $this->leave->requestLeave($staff, $type, '2026-09-07', '2026-09-11');
    }

    public function test_attachment_is_required_when_the_type_demands_it(): void
    {
        $manager = $this->user('Manager');
        $staff = $this->user('Staff', $manager->id);
        $type = $this->annualType([
            'code' => 'sick', 'default_quota' => null, 'requires_attachment' => true,
        ]);

        $this->expectException(\DomainException::class);
        $this->leave->requestLeave($staff, $type, '2026-09-07', '2026-09-08');
    }

    // ── Approval and balance ───────────────────────────────

    public function test_approval_marks_the_request_and_spends_the_balance(): void
    {
        $manager = $this->user('Manager');
        $staff = $this->user('Staff', $manager->id);
        $type = $this->annualType();

        $request = $this->leave->requestLeave($staff, $type, '2026-09-07', '2026-09-09');
        $this->approvals->approve($request->approval, $manager);

        $request->refresh();
        $this->assertSame(LeaveRequest::STATUS_APPROVED, $request->status);
        $this->assertSame($manager->id, $request->approved_by);
        $this->assertNotNull($request->approved_at);

        $balance = $this->leave->getBalance($staff, $type, 2026);
        $this->assertSame(3, $balance->used);
        $this->assertSame(9, $balance->remainingDays());
    }

    public function test_rejection_marks_the_request_and_leaves_balance_untouched(): void
    {
        $manager = $this->user('Manager');
        $staff = $this->user('Staff', $manager->id);
        $type = $this->annualType();

        $request = $this->leave->requestLeave($staff, $type, '2026-09-07', '2026-09-09');
        $this->approvals->reject($request->approval, $manager, 'Sedang sibuk');

        $request->refresh();
        $this->assertSame(LeaveRequest::STATUS_REJECTED, $request->status);
        $this->assertSame('Sedang sibuk', $request->rejection_reason);
        $this->assertSame(0, $this->leave->getBalance($staff, $type, 2026)->used);
    }

    public function test_cancelling_an_approved_request_returns_the_balance(): void
    {
        $manager = $this->user('Manager');
        $staff = $this->user('Staff', $manager->id);
        $type = $this->annualType();

        $request = $this->leave->requestLeave($staff, $type, '2026-09-07', '2026-09-09');
        $this->approvals->approve($request->approval, $manager);
        $this->assertSame(3, $this->leave->getBalance($staff, $type, 2026)->used);

        $this->leave->cancel($request->fresh(), $staff);

        $this->assertSame(LeaveRequest::STATUS_CANCELLED, $request->fresh()->status);
        $this->assertSame(0, $this->leave->getBalance($staff, $type, 2026)->used);
    }

    public function test_only_the_requester_can_cancel(): void
    {
        $manager = $this->user('Manager');
        $staff = $this->user('Staff', $manager->id);
        $type = $this->annualType();

        $request = $this->leave->requestLeave($staff, $type, '2026-09-07', '2026-09-09');

        $this->expectException(\DomainException::class);
        $this->leave->cancel($request, $manager);
    }

    public function test_unquota_type_does_not_touch_balances(): void
    {
        $manager = $this->user('Manager');
        $staff = $this->user('Staff', $manager->id);
        $sick = $this->annualType(['code' => 'sick', 'default_quota' => null, 'name' => 'Sakit']);

        $request = $this->leave->requestLeave($staff, $sick, '2026-09-07', '2026-09-08');
        $this->approvals->approve($request->approval, $manager);

        $this->assertSame(LeaveRequest::STATUS_APPROVED, $request->fresh()->status);
        $this->assertDatabaseCount('leave_balances', 0);
    }

    public function test_final_approver_is_recorded_not_the_first(): void
    {
        // Two-step flow: manager, then HR. approved_by must be the person who
        // completed the chain, not whoever happened to act first.
        $hrRole = Role::create(['name' => 'hr_admin', 'guard_name' => 'web']);
        $manager = $this->user('Manager');
        $staff = $this->user('Staff', $manager->id);
        $hr = $this->user('HR');
        $hr->assignRole($hrRole);

        ApprovalFlow::query()->delete();
        $flow = ApprovalFlow::create([
            'name' => 'Cuti 2 Step', 'module' => 'leave', 'steps' => 2, 'is_active' => true,
        ]);
        ApprovalFlowStep::create([
            'approval_flow_id' => $flow->id, 'step_order' => 1,
            'approver_type' => ApprovalFlowStep::TYPE_MANAGER,
        ]);
        ApprovalFlowStep::create([
            'approval_flow_id' => $flow->id, 'step_order' => 2,
            'approver_type' => ApprovalFlowStep::TYPE_ROLE, 'approver_role_id' => $hrRole->id,
        ]);

        $type = $this->annualType();
        $request = $this->leave->requestLeave($staff, $type, '2026-09-07', '2026-09-09');

        $this->approvals->approve($request->approval, $manager, 'Step 1 ok');
        $this->approvals->approve($request->approval->fresh(), $hr, 'Step 2 ok');

        $this->assertSame(
            $hr->id,
            $request->fresh()->approved_by,
            'approved_by must be the final approver, not the first'
        );
    }

    public function test_rejection_reason_comes_from_the_rejecting_step(): void
    {
        $hrRole = Role::create(['name' => 'hr_admin', 'guard_name' => 'web']);
        $manager = $this->user('Manager');
        $staff = $this->user('Staff', $manager->id);
        $hr = $this->user('HR');
        $hr->assignRole($hrRole);

        ApprovalFlow::query()->delete();
        $flow = ApprovalFlow::create([
            'name' => 'Cuti 2 Step', 'module' => 'leave', 'steps' => 2, 'is_active' => true,
        ]);
        ApprovalFlowStep::create([
            'approval_flow_id' => $flow->id, 'step_order' => 1,
            'approver_type' => ApprovalFlowStep::TYPE_MANAGER,
        ]);
        ApprovalFlowStep::create([
            'approval_flow_id' => $flow->id, 'step_order' => 2,
            'approver_type' => ApprovalFlowStep::TYPE_ROLE, 'approver_role_id' => $hrRole->id,
        ]);

        $request = $this->leave->requestLeave($staff, $this->annualType(), '2026-09-07', '2026-09-09');

        $this->approvals->approve($request->approval, $manager, 'Manager setuju');
        $this->approvals->reject($request->approval->fresh(), $hr, 'Saldo tidak cukup');

        $fresh = $request->fresh();
        $this->assertSame(LeaveRequest::STATUS_REJECTED, $fresh->status);
        $this->assertSame('Saldo tidak cukup', $fresh->rejection_reason,
            'the reason must come from the step that rejected, not an earlier approval note');
    }

    // ── Balance generation and carry-over ──────────────────

    public function test_yearly_balance_generation_prorates_mid_year_joiners(): void
    {
        $type = $this->annualType(['default_quota' => 12]);

        $this->user('Full Year', null, ['join_date' => '2025-01-01']);
        $this->user('Joined July', null, ['join_date' => '2026-07-01']);
        $this->user('Next Year', null, ['join_date' => '2027-01-01']);

        $created = $this->leave->generateYearlyBalances(2026);

        $this->assertSame(3, $created);
        $this->assertSame(12, LeaveBalance::whereHas('user', fn ($q) => $q->where('name', 'Full Year'))->first()->quota);
        // July onwards = 6 of 12 months.
        $this->assertSame(6, LeaveBalance::whereHas('user', fn ($q) => $q->where('name', 'Joined July'))->first()->quota);
        $this->assertSame(0, LeaveBalance::whereHas('user', fn ($q) => $q->where('name', 'Next Year'))->first()->quota);
    }

    public function test_yearly_generation_is_idempotent(): void
    {
        $this->annualType();
        $this->user('Staff', null, ['join_date' => '2025-01-01']);

        $this->assertSame(1, $this->leave->generateYearlyBalances(2026));
        $this->assertSame(0, $this->leave->generateYearlyBalances(2026));
        $this->assertDatabaseCount('leave_balances', 1);
    }

    public function test_carry_over_moves_unused_days_and_respects_the_cap(): void
    {
        $type = $this->annualType();
        $staff = $this->user('Staff');

        LeaveBalance::create([
            'user_id' => $staff->id, 'leave_type_id' => $type->id,
            'year' => 2025, 'quota' => 12, 'used' => 4,
        ]);

        $this->leave->carryOver(2025, 2026, 5);

        $next = LeaveBalance::where('user_id', $staff->id)->where('year', 2026)->first();
        $this->assertSame(5, $next->carry_over, '8 unused days capped at 5');
        $this->assertSame(17, $next->fresh()->remainingDays(), '12 quota + 5 carried');
    }

    public function test_carry_over_is_idempotent(): void
    {
        $type = $this->annualType();
        $staff = $this->user('Staff');

        LeaveBalance::create([
            'user_id' => $staff->id, 'leave_type_id' => $type->id,
            'year' => 2025, 'quota' => 12, 'used' => 4,
        ]);

        $this->leave->carryOver(2025, 2026, 5);
        $this->leave->carryOver(2025, 2026, 5);

        $next = LeaveBalance::where('user_id', $staff->id)->where('year', 2026)->first();
        $this->assertSame(5, $next->carry_over, 're-running must not accumulate');
    }

    public function test_remaining_column_accounts_for_carry_over(): void
    {
        $type = $this->annualType();
        $staff = $this->user('Staff');

        $balance = LeaveBalance::create([
            'user_id'    => $staff->id,
            'leave_type_id' => $type->id,
            'year'       => 2026,
            'quota'      => 12,
            'used'       => 3,
            'carry_over' => 4,
        ]);

        $this->assertSame(13, $balance->fresh()->remaining, '12 + 4 - 3');
    }

    // ── Payroll bridge ─────────────────────────────────────

    public function test_approved_leave_days_are_split_paid_and_unpaid(): void
    {
        $manager = $this->user('Manager');
        $staff = $this->user('Staff', $manager->id);

        $paidType = $this->annualType();
        $unpaidType = $this->annualType([
            'code' => 'unpaid', 'name' => 'Tanpa Gaji',
            'is_paid' => false, 'default_quota' => null,
        ]);

        $a = $this->leave->requestLeave($staff, $paidType, '2026-09-07', '2026-09-09'); // 3 days
        $this->approvals->approve($a->approval, $manager);

        $b = $this->leave->requestLeave($staff, $unpaidType, '2026-09-14', '2026-09-15'); // 2 days
        $this->approvals->approve($b->approval, $manager);

        $result = $this->leave->approvedLeaveDaysInMonth($staff->id, Carbon::parse('2026-09-01'));

        $this->assertSame(3, $result['paid']);
        $this->assertSame(2, $result['unpaid']);
        $this->assertSame(5, $result['total']);
    }

    public function test_pending_leave_does_not_count_towards_payroll(): void
    {
        $manager = $this->user('Manager');
        $staff = $this->user('Staff', $manager->id);
        $type = $this->annualType();

        $this->leave->requestLeave($staff, $type, '2026-09-07', '2026-09-09');

        $result = $this->leave->approvedLeaveDaysInMonth($staff->id, Carbon::parse('2026-09-01'));

        $this->assertSame(0, $result['total'], 'only approved leave counts');
    }

    public function test_leave_in_another_month_is_not_counted(): void
    {
        $manager = $this->user('Manager');
        $staff = $this->user('Staff', $manager->id);
        $type = $this->annualType();

        $r = $this->leave->requestLeave($staff, $type, '2026-09-07', '2026-09-09');
        $this->approvals->approve($r->approval, $manager);

        $october = $this->leave->approvedLeaveDaysInMonth($staff->id, Carbon::parse('2026-10-01'));

        $this->assertSame(0, $october['total']);
    }

    public function test_leave_spanning_a_month_boundary_is_attributed_per_day(): void
    {
        $manager = $this->user('Manager');
        $staff = $this->user('Staff', $manager->id);
        $type = $this->annualType(['default_quota' => 30]);

        // Mon 2026-09-28 .. Fri 2026-10-02 => Sep: 28,29,30 (3), Oct: 1,2 (2)
        $r = $this->leave->requestLeave($staff, $type, '2026-09-28', '2026-10-02');
        $this->approvals->approve($r->approval, $manager);

        $sep = $this->leave->approvedLeaveDaysInMonth($staff->id, Carbon::parse('2026-09-01'));
        $oct = $this->leave->approvedLeaveDaysInMonth($staff->id, Carbon::parse('2026-10-01'));

        $this->assertSame(3, $sep['total']);
        $this->assertSame(2, $oct['total']);
    }
}
