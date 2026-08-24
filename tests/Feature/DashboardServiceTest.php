<?php

namespace Tests\Feature;

use App\Models\ApprovalFlow;
use App\Models\ApprovalFlowStep;
use App\Models\Day;
use App\Models\Department;
use App\Models\LeaveType;
use App\Models\Loan;
use App\Models\LoanPayment;
use App\Models\Presence;
use App\Models\SalaryRecap;
use App\Models\Schedule;
use App\Models\ScheduleDayOff;
use App\Models\User;
use App\Services\ApprovalService;
use App\Services\DashboardService;
use App\Services\LeaveService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardServiceTest extends TestCase
{
    use RefreshDatabase;

    private DashboardService $dashboard;
    private Schedule $schedule;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dashboard = app(DashboardService::class);

        $this->schedule = Schedule::create([
            'name' => 'Reguler', 'in' => '08:00:00', 'out' => '17:00:00',
            'over_in' => '18:00:00', 'over_out' => '22:00:00',
        ]);

        foreach (['Sabtu', 'Minggu'] as $name) {
            $day = Day::create(['name' => $name]);
            ScheduleDayOff::create(['schedule_id' => $this->schedule->id, 'day' => $day->id]);
        }
    }

    private function user(string $name, array $attrs = []): User
    {
        return User::create(array_merge([
            'name'        => $name,
            'email'       => str($name)->slug() . '@example.test',
            'password'    => bcrypt('secret'),
            'schedule_id' => $this->schedule->id,
        ], $attrs));
    }

    /**
     * PresenceObserver recomputes is_late/is_overtime from the schedule on
     * save, so lateness is expressed through the clock-in time rather than by
     * setting the flag directly.
     */
    private function presence(User $user, Carbon $at, array $attrs = []): Presence
    {
        return Presence::create(array_merge([
            'user_id' => $user->id,
            'in'      => $at->copy()->setTime(8, 0),
            'out'     => $at->copy()->setTime(17, 0),
        ], $attrs));
    }

    /**
     * Clocks in $minutes after the 08:00 schedule so the observer marks it late.
     */
    private function latePresence(User $user, Carbon $at, int $minutes = 20): Presence
    {
        return Presence::create([
            'user_id' => $user->id,
            'in'      => $at->copy()->setTime(8, 0)->addMinutes($minutes),
            'out'     => $at->copy()->setTime(17, 0),
        ]);
    }

    /**
     * First Monday of the current month, so weekday-sensitive assertions do
     * not depend on when the suite happens to run.
     */
    private function firstMondayOfThisMonth(): Carbon
    {
        $day = now()->startOfMonth();

        while (! $day->isMonday()) {
            $day->addDay();
        }

        return $day;
    }

    // ── Today ──────────────────────────────────────────────

    public function test_today_snapshot_counts_present_late_and_absent(): void
    {
        $a = $this->user('Present On Time');
        $b = $this->user('Present Late');
        $this->user('Absent');

        $this->presence($a, now());
        $this->latePresence($b, now(), 15);

        $today = $this->dashboard->todaySnapshot(now(), false);

        $this->assertSame(3, $today['headcount']);
        $this->assertSame(2, $today['present']);
        $this->assertSame(1, $today['late']);
        $this->assertSame(1, $today['absent']);
    }

    public function test_approved_leave_is_not_counted_as_absent(): void
    {
        $flow = ApprovalFlow::create(['name' => 'Cuti', 'module' => 'leave', 'steps' => 1, 'is_active' => true]);
        ApprovalFlowStep::create([
            'approval_flow_id' => $flow->id, 'step_order' => 1,
            'approver_type' => ApprovalFlowStep::TYPE_MANAGER,
        ]);

        // Use a weekday so the day is chargeable leave. Resolve this first: the
        // presence row below must land on the same day the snapshot measures,
        // otherwise the run fails at weekends when $day jumps to Monday.
        $day = now()->isWeekend() ? now()->next(Carbon::MONDAY) : now();

        $manager = $this->user('Manager');
        $onLeave = $this->user('On Leave', ['manager_id' => $manager->id]);
        $present = $this->user('Present');
        $this->presence($present, $day);

        $type = LeaveType::create([
            'name' => 'Cuti Tahunan', 'code' => 'annual',
            'is_paid' => true, 'default_quota' => 12, 'is_active' => true,
        ]);

        $request = app(LeaveService::class)->requestLeave(
            $onLeave, $type, $day->toDateString(), $day->toDateString()
        );
        app(ApprovalService::class)->approve($request->approval, $manager);

        $today = $this->dashboard->todaySnapshot($day, false);

        $this->assertSame(1, $today['on_leave']);
        $this->assertSame(
            1,
            $today['absent'],
            'only the manager is unaccounted for — the person on leave is not absent'
        );
    }

    public function test_resigned_staff_are_out_of_the_headcount(): void
    {
        $this->user('Active');
        $this->user('Gone', ['employment_status' => User::STATUS_RESIGNED]);

        $this->assertSame(1, $this->dashboard->todaySnapshot(now(), false)['headcount']);
    }

    public function test_absent_count_never_goes_negative(): void
    {
        $user = $this->user('Double Scanner');
        // Two rows for the same person on the same day.
        $this->presence($user, now());
        Presence::create(['user_id' => $user->id, 'in' => now()->setTime(13, 0)]);

        $today = $this->dashboard->todaySnapshot(now(), false);

        $this->assertSame(1, $today['present'], 'distinct people, not rows');
        $this->assertSame(0, $today['absent']);
    }

    // ── Month ──────────────────────────────────────────────

    public function test_month_snapshot_totals_payroll_and_loans(): void
    {
        $user = $this->user('Staff');

        $recap = SalaryRecap::create([
            'user_id' => $user->id, 'recap_month' => now()->format('m-Y'),
            'work_day' => 20, 'late_day' => 0, 'salary_amount' => 5_000_000,
            'overtime_amount' => 0, 'loan_cut' => 0, 'late_cut' => 0,
            'abstain_cut' => 0, 'abstain_count' => 0, 'received' => 0,
        ]);
        $recap->forceFill([
            'received' => 4_500_000, 'overtime_amount' => 300_000,
            'loan_cut' => 200_000, 'late_cut' => 50_000, 'abstain_cut' => 0,
        ])->saveQuietly();

        Loan::create(['user_id' => $user->id, 'amount' => 1_000_000, 'date' => now()->toDateString()]);
        LoanPayment::create(['user_id' => $user->id, 'amount' => 400_000, 'date' => now()->toDateString()]);

        $month = $this->dashboard->monthSnapshot(now()->format('m-Y'));

        $this->assertSame(1, $month['recaps']);
        $this->assertSame(4_500_000, $month['salary']);
        $this->assertSame(300_000, $month['overtime']);
        $this->assertSame(250_000, $month['deductions']);
        $this->assertSame(600_000, $month['loan_outstanding']);
    }

    // ── Rankings ───────────────────────────────────────────

    public function test_top_latecomers_are_ranked_by_frequency(): void
    {
        $worst = $this->user('Worst');
        $mild = $this->user('Mild');
        $this->user('Punctual');

        $mon = $this->firstMondayOfThisMonth();

        foreach (range(0, 3) as $d) {
            $this->latePresence($worst, $mon->copy()->addDays($d), 10);
        }
        $this->latePresence($mild, $mon->copy(), 5);

        $ranked = $this->dashboard->topLatecomers(now());

        $this->assertCount(2, $ranked);
        $this->assertSame('Worst', $ranked->first()['user']->name);
        $this->assertSame(4, $ranked->first()['count']);
        $this->assertSame(40, $ranked->first()['minutes']);
    }

    public function test_no_latecomers_yields_an_empty_ranking(): void
    {
        $user = $this->user('Punctual');
        $this->presence($user, now());

        $this->assertCount(0, $this->dashboard->topLatecomers(now()));
    }

    // ── Headcount ──────────────────────────────────────────

    public function test_headcount_splits_by_department_and_status(): void
    {
        $it = Department::create(['name' => 'IT', 'code' => 'IT']);
        $hr = Department::create(['name' => 'HR', 'code' => 'HR']);

        $this->user('Dev One', ['department_id' => $it->id]);
        $this->user('Dev Two', ['department_id' => $it->id]);
        $this->user('HR One', ['department_id' => $hr->id]);
        $this->user('Floating');
        $this->user('Left', ['employment_status' => User::STATUS_RESIGNED]);

        $headcount = $this->dashboard->headcount();

        $this->assertSame(4, $headcount['total']);
        $this->assertSame(1, $headcount['unassigned']);
        $this->assertSame(4, $headcount['by_status']['active'] ?? 0);
        $this->assertSame(1, $headcount['by_status']['resigned'] ?? 0);

        $itRow = $headcount['by_department']->firstWhere('name', 'IT');
        $this->assertSame(2, $itRow['count']);
    }

    // ── Reports ────────────────────────────────────────────

    public function test_attendance_report_summarises_each_employee(): void
    {
        $user = $this->user('Staff');

        // Anchored to weekdays: a scan on a scheduled off day is automatically
        // treated as overtime, which would skew the counts.
        $mon = $this->firstMondayOfThisMonth();

        $this->presence($user, $mon);
        $this->latePresence($user, $mon->copy()->addDay(), 20);
        // Clock out past the overtime threshold so the observer flags it.
        Presence::create([
            'user_id' => $user->id,
            'in'      => $mon->copy()->addDays(2)->setTime(8, 0),
            'out'     => $mon->copy()->addDays(2)->setTime(19, 0),
        ]);

        $rows = $this->dashboard->attendanceReport($mon->copy()->startOfMonth());
        $row = $rows->firstWhere(fn ($r) => $r['user']->id === $user->id);

        $this->assertSame(3, $row['present']);
        $this->assertSame(1, $row['late']);
        $this->assertSame(20, $row['late_minutes']);
        $this->assertSame(1, $row['overtime']);
    }

    public function test_attendance_report_can_be_scoped_to_a_department(): void
    {
        $it = Department::create(['name' => 'IT', 'code' => 'IT']);
        $this->user('In IT', ['department_id' => $it->id]);
        $this->user('Elsewhere');

        $rows = $this->dashboard->attendanceReport(now()->startOfMonth(), $it->id);

        $this->assertCount(1, $rows);
        $this->assertSame('In IT', $rows->first()['user']->name);
    }

    public function test_loan_report_lists_only_outstanding_balances(): void
    {
        $owing = $this->user('Still Owes');
        $settled = $this->user('Settled Up');

        Loan::create(['user_id' => $owing->id, 'amount' => 1_000_000, 'date' => now()->toDateString()]);
        LoanPayment::create(['user_id' => $owing->id, 'amount' => 300_000, 'date' => now()->toDateString()]);

        Loan::create(['user_id' => $settled->id, 'amount' => 500_000, 'date' => now()->toDateString()]);
        LoanPayment::create(['user_id' => $settled->id, 'amount' => 500_000, 'date' => now()->toDateString()]);

        $rows = $this->dashboard->loanReport();

        $this->assertCount(1, $rows);
        $this->assertSame('Still Owes', $rows->first()['user']->name);
        $this->assertSame(700_000, $rows->first()['outstanding']);
    }

    public function test_attendance_trend_returns_one_entry_per_month(): void
    {
        $this->user('Staff');

        $trend = $this->dashboard->attendanceTrend(6);

        $this->assertCount(6, $trend);
        $this->assertArrayHasKey('label', $trend->first());
        $this->assertArrayHasKey('rate', $trend->first());
        // The final entry is the current month.
        $this->assertSame(now()->locale('id_ID')->isoFormat('MMM YY'), $trend->last()['label']);
    }

    public function test_trend_rate_is_capped_at_one_hundred_percent(): void
    {
        $user = $this->user('Overachiever');

        // More presence rows than there are weekdays.
        foreach (range(0, 27) as $d) {
            Presence::create([
                'user_id' => $user->id,
                'in'      => now()->startOfMonth()->addDays($d)->setTime(8, 0),
            ]);
        }

        $trend = $this->dashboard->attendanceTrend(1);

        $this->assertLessThanOrEqual(100, $trend->first()['rate']);
    }
}
