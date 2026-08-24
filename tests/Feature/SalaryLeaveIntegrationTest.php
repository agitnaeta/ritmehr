<?php

namespace Tests\Feature;

use App\Models\ApprovalFlow;
use App\Models\ApprovalFlowStep;
use App\Models\Day;
use App\Models\LeaveType;
use App\Models\Salary;
use App\Models\SalaryRecap;
use App\Models\Schedule;
use App\Models\ScheduleDayOff;
use App\Models\User;
use App\Services\ApprovalService;
use App\Services\LeaveService;
use App\Services\SalaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The regression this module exists to fix: before leave management, every day
 * an employee was not present counted as an unpaid absence, so approved leave
 * silently docked their pay.
 */
class SalaryLeaveIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private SalaryService $salary;
    private LeaveService $leave;
    private ApprovalService $approvals;
    private Schedule $schedule;
    private User $manager;
    private User $staff;

    /** September 2026 has 22 weekdays (no national holidays seeded). */
    private const SEPT_WORKDAYS = 22;
    private const DEDUCTION_PER_DAY = 100_000;

    protected function setUp(): void
    {
        parent::setUp();

        $this->salary = app(SalaryService::class);
        $this->leave = app(LeaveService::class);
        $this->approvals = app(ApprovalService::class);

        $this->schedule = Schedule::create([
            'name' => 'Reguler', 'in' => '08:00:00', 'out' => '17:00:00',
            'over_in' => '18:00:00', 'over_out' => '22:00:00',
        ]);

        foreach (['Sabtu', 'Minggu'] as $name) {
            $day = Day::create(['name' => $name]);
            ScheduleDayOff::create(['schedule_id' => $this->schedule->id, 'day' => $day->id]);
        }

        $flow = ApprovalFlow::create([
            'name' => 'Cuti', 'module' => 'leave', 'steps' => 1, 'is_active' => true,
        ]);
        ApprovalFlowStep::create([
            'approval_flow_id' => $flow->id,
            'step_order'       => 1,
            'approver_type'    => ApprovalFlowStep::TYPE_MANAGER,
        ]);

        $this->manager = $this->makeUser('Manager');
        $this->staff = $this->makeUser('Staff', $this->manager->id);

        Salary::create([
            'user_id'                => $this->staff->id,
            'amount'                 => 5_000_000,
            'overtime_amount'        => 50_000,
            'overtime_type'          => 'flat',
            'unpaid_leave_deduction' => self::DEDUCTION_PER_DAY,
            'fine_type'              => 'flat',
            'fine'                   => 0,
            'fine_per_minute'        => 0,
        ]);
    }

    private function makeUser(string $name, ?int $managerId = null): User
    {
        return User::create([
            'name'        => $name,
            'email'       => str($name)->slug() . '@example.test',
            'password'    => bcrypt('secret'),
            'schedule_id' => $this->schedule->id,
            'manager_id'  => $managerId,
        ]);
    }

    /**
     * SalaryRecapObserver recalculates work_day from presence rows on save, so
     * the attendance figure is written quietly afterwards. These tests target
     * the absence/deduction calculation itself, not the observer.
     */
    private function recap(int $workDays): SalaryRecap
    {
        $recap = SalaryRecap::create([
            'user_id'         => $this->staff->id,
            'recap_month'     => '09-2026',
            'work_day'        => 0,
            'late_day'        => 0,
            'salary_amount'   => 5_000_000,
            'overtime_amount' => 0,
            'loan_cut'        => 0,
            'late_cut'        => 0,
            'abstain_cut'     => 0,
            'abstain_count'   => 0,
            'received'        => 0,
        ]);

        $recap->work_day = $workDays;
        $recap->saveQuietly();

        return $recap;
    }

    private function leaveType(bool $paid, string $code): LeaveType
    {
        return LeaveType::create([
            'name'          => $paid ? 'Cuti Tahunan' : 'Cuti Tanpa Gaji',
            'code'          => $code,
            'is_paid'       => $paid,
            'default_quota' => 30,
            'is_active'     => true,
        ]);
    }

    private function approveLeave(LeaveType $type, string $start, string $end): void
    {
        $request = $this->leave->requestLeave($this->staff, $type, $start, $end);
        $this->approvals->approve($request->approval, $this->manager);
    }

    // ── Baseline ───────────────────────────────────────────

    public function test_september_2026_has_the_expected_workday_count(): void
    {
        $recap = $this->recap(0);

        $this->assertSame(self::SEPT_WORKDAYS, $this->salary->availableWorkDays($recap));
    }

    public function test_full_attendance_incurs_no_deduction(): void
    {
        $recap = $this->recap(self::SEPT_WORKDAYS);
        $salary = Salary::where('user_id', $this->staff->id)->first();

        $this->assertSame(0, $this->salary->getAbstain($recap));
        $this->assertSame(0, $this->salary->unpaidLeaveDeduction($recap, $salary));
    }

    // ── The regression ─────────────────────────────────────

    public function test_approved_paid_leave_is_not_charged_as_absence(): void
    {
        $type = $this->leaveType(true, 'annual');
        // Mon 7 Sep .. Fri 11 Sep = 5 working days.
        $this->approveLeave($type, '2026-09-07', '2026-09-11');

        // Present on every other workday.
        $recap = $this->recap(self::SEPT_WORKDAYS - 5);
        $salary = Salary::where('user_id', $this->staff->id)->first();

        $this->assertSame(0, $this->salary->getAbstain($recap), 'paid leave is not an absence');
        $this->assertSame(
            0,
            $this->salary->unpaidLeaveDeduction($recap, $salary),
            'paid leave must not reduce pay — this is the bug the module fixes'
        );
    }

    public function test_unpaid_leave_is_still_deducted(): void
    {
        $type = $this->leaveType(false, 'unpaid');
        $this->approveLeave($type, '2026-09-07', '2026-09-11'); // 5 days

        $recap = $this->recap(self::SEPT_WORKDAYS - 5);
        $salary = Salary::where('user_id', $this->staff->id)->first();

        $this->assertSame(0, $this->salary->getAbstain($recap), 'unpaid leave is explained, not truancy');
        $this->assertSame(
            5 * self::DEDUCTION_PER_DAY,
            $this->salary->unpaidLeaveDeduction($recap, $salary),
            'unpaid leave still costs the employee'
        );
    }

    public function test_unexplained_absence_is_still_deducted(): void
    {
        $recap = $this->recap(self::SEPT_WORKDAYS - 3); // 3 days simply missing
        $salary = Salary::where('user_id', $this->staff->id)->first();

        $this->assertSame(3, $this->salary->getAbstain($recap));
        $this->assertSame(3 * self::DEDUCTION_PER_DAY, $this->salary->unpaidLeaveDeduction($recap, $salary));
    }

    public function test_paid_leave_unpaid_leave_and_truancy_combine_correctly(): void
    {
        $paid = $this->leaveType(true, 'annual');
        $unpaid = $this->leaveType(false, 'unpaid');

        $this->approveLeave($paid, '2026-09-07', '2026-09-09');    // 3 paid
        $this->approveLeave($unpaid, '2026-09-14', '2026-09-15');  // 2 unpaid

        // 22 workdays: 3 paid leave + 2 unpaid leave + 2 truant = 15 present.
        $recap = $this->recap(self::SEPT_WORKDAYS - 3 - 2 - 2);
        $salary = Salary::where('user_id', $this->staff->id)->first();

        $this->assertSame(2, $this->salary->getAbstain($recap), 'only the truant days');
        $this->assertSame(
            (2 + 2) * self::DEDUCTION_PER_DAY,
            $this->salary->unpaidLeaveDeduction($recap, $salary),
            '2 truant + 2 unpaid leave are chargeable'
        );
    }

    public function test_pending_leave_does_not_excuse_an_absence(): void
    {
        $type = $this->leaveType(true, 'annual');
        // Requested but never approved.
        $this->leave->requestLeave($this->staff, $type, '2026-09-07', '2026-09-11');

        $recap = $this->recap(self::SEPT_WORKDAYS - 5);
        $salary = Salary::where('user_id', $this->staff->id)->first();

        $this->assertSame(5, $this->salary->getAbstain($recap));
        $this->assertSame(5 * self::DEDUCTION_PER_DAY, $this->salary->unpaidLeaveDeduction($recap, $salary));
    }

    public function test_deduction_never_goes_negative_for_overattendance(): void
    {
        // More presence rows than workdays (weekend overtime shifts).
        $recap = $this->recap(self::SEPT_WORKDAYS + 4);
        $salary = Salary::where('user_id', $this->staff->id)->first();

        $this->assertSame(0, $this->salary->getAbstain($recap));
        $this->assertSame(0, $this->salary->unpaidLeaveDeduction($recap, $salary));
    }

    public function test_leave_beyond_the_recap_month_is_ignored(): void
    {
        $type = $this->leaveType(true, 'annual');
        $this->approveLeave($type, '2026-10-05', '2026-10-09'); // October

        $recap = $this->recap(self::SEPT_WORKDAYS - 5);
        $salary = Salary::where('user_id', $this->staff->id)->first();

        $this->assertSame(5, $this->salary->getAbstain($recap), 'October leave cannot excuse September');
        $this->assertSame(5 * self::DEDUCTION_PER_DAY, $this->salary->unpaidLeaveDeduction($recap, $salary));
    }

    public function test_unparseable_recap_month_degrades_to_no_leave(): void
    {
        $recap = $this->recap(0);
        $recap->forceFill(['recap_month' => 'not-a-month'])->saveQuietly();

        $this->assertSame(
            ['paid' => 0, 'unpaid' => 0, 'total' => 0],
            $this->leave->approvedLeaveDaysForRecap($recap)
        );
    }
}
