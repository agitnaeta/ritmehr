<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Department;
use App\Models\LeaveRequest;
use App\Models\Loan;
use App\Models\LoanPayment;
use App\Models\Presence;
use App\Models\SalaryRecap;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Aggregations behind the admin dashboard and reports.
 *
 * Today's figures are cached briefly — the dashboard is the most-hit page in
 * the panel and these are all full-table scans.
 */
class DashboardService
{
    private const TODAY_CACHE_TTL = 300;   // seconds
    private const TREND_CACHE_TTL = 3600;

    /**
     * @return array{present:int, absent:int, late:int, outside:int, on_leave:int, headcount:int}
     */
    public function todaySnapshot(?Carbon $date = null, bool $useCache = true): array
    {
        $date ??= now();
        $key = 'dashboard.today.' . $date->toDateString();

        $compute = function () use ($date) {
            $headcount = User::employed()->count();

            $presences = Presence::whereDate('in', $date->toDateString())->get();
            $present = $presences->pluck('user_id')->unique()->count();

            $onLeave = LeaveRequest::approved()
                ->whereHas('dates', fn ($q) => $q->whereDate('date', $date->toDateString()))
                ->distinct('user_id')
                ->count('user_id');

            return [
                'headcount' => $headcount,
                'present'   => $present,
                'late'      => $presences->where('is_late', true)->count(),
                'outside'   => $presences->where('outside', true)->count(),
                'on_leave'  => $onLeave,
                // Not present and not on approved leave.
                'absent'    => max(0, $headcount - $present - $onLeave),
            ];
        };

        return $useCache
            ? Cache::remember($key, self::TODAY_CACHE_TTL, $compute)
            : $compute();
    }

    /**
     * Payroll and loan totals for a recap month (m-Y).
     *
     * @return array{salary:int, overtime:int, deductions:int, loan_outstanding:int, recaps:int}
     */
    public function monthSnapshot(?string $recapMonth = null): array
    {
        $recapMonth ??= now()->format('m-Y');

        $recaps = SalaryRecap::where('recap_month', $recapMonth)->get();

        $borrowed = (int) Loan::sum('amount');
        $repaid = (int) LoanPayment::sum('amount');

        return [
            'recaps'           => $recaps->count(),
            'salary'           => (int) $recaps->sum('received'),
            'overtime'         => (int) $recaps->sum('overtime_amount'),
            'deductions'       => (int) $recaps->sum(
                fn (SalaryRecap $r) => $r->loan_cut + $r->late_cut + $r->abstain_cut
            ),
            'loan_outstanding' => max(0, $borrowed - $repaid),
        ];
    }

    /**
     * Attendance rate per month for the last $months months.
     *
     * @return Collection<int, array{label:string, present:int, late:int, rate:float}>
     */
    public function attendanceTrend(int $months = 12): Collection
    {
        return Cache::remember(
            'dashboard.trend.' . $months . '.' . now()->format('Y-m-d'),
            self::TREND_CACHE_TTL,
            function () use ($months) {
                $headcount = max(1, User::employed()->count());
                $result = collect();

                for ($i = $months - 1; $i >= 0; $i--) {
                    $month = now()->copy()->subMonths($i)->startOfMonth();

                    $presences = Presence::whereYear('in', $month->year)
                        ->whereMonth('in', $month->month)
                        ->get();

                    // Approximate working days: weekdays in the month.
                    $workingDays = $this->weekdaysIn($month);
                    $expected = max(1, $headcount * $workingDays);

                    $result->push([
                        'label'   => $month->locale('id_ID')->isoFormat('MMM YY'),
                        'present' => $presences->count(),
                        'late'    => $presences->where('is_late', true)->count(),
                        'rate'    => round(min(100, $presences->count() / $expected * 100), 1),
                    ]);
                }

                return $result;
            }
        );
    }

    /**
     * Employees with the most late arrivals in a month.
     */
    public function topLatecomers(?Carbon $month = null, int $limit = 5): Collection
    {
        $month ??= now();

        return Presence::with('user')
            ->whereYear('in', $month->year)
            ->whereMonth('in', $month->month)
            ->where('is_late', true)
            ->get()
            ->groupBy('user_id')
            ->map(fn ($rows) => [
                'user'    => $rows->first()->user,
                'count'   => $rows->count(),
                'minutes' => (int) $rows->sum('late_minute'),
            ])
            ->filter(fn ($row) => $row['user'] !== null)
            ->sortByDesc('count')
            ->take($limit)
            ->values();
    }

    /**
     * Who is on approved leave in a date range.
     */
    public function leaveThisWeek(?Carbon $from = null, ?Carbon $to = null): Collection
    {
        $from ??= now()->startOfWeek();
        $to ??= now()->endOfWeek();

        return LeaveRequest::with(['user', 'leaveType'])
            ->approved()
            ->overlapping($from->toDateString(), $to->toDateString())
            ->get();
    }

    /**
     * Headcount split by department and by employment status.
     *
     * @return array{by_department:Collection, by_status:array, by_branch:Collection, total:int}
     */
    public function headcount(): array
    {
        $byStatus = User::query()
            ->selectRaw('employment_status, COUNT(*) as total')
            ->groupBy('employment_status')
            ->pluck('total', 'employment_status')
            ->all();

        $byDepartment = Department::withCount(['users as employed_count' => fn ($q) => $q->employed()])
            ->orderByDesc('employed_count')
            ->get()
            ->map(fn (Department $d) => ['name' => $d->name, 'count' => (int) $d->employed_count]);

        $byBranch = Branch::withCount(['users as employed_count' => fn ($q) => $q->employed()])
            ->orderByDesc('employed_count')
            ->get()
            ->map(fn (Branch $b) => ['name' => $b->name, 'count' => (int) $b->employed_count]);

        return [
            'by_department' => $byDepartment,
            'by_branch'     => $byBranch,
            'by_status'     => $byStatus,
            'total'         => User::employed()->count(),
            'unassigned'    => User::employed()->whereNull('department_id')->count(),
        ];
    }

    /**
     * Attendance summary per employee for a month, for the report page.
     */
    public function attendanceReport(Carbon $month, ?int $departmentId = null, ?int $branchId = null): Collection
    {
        $users = User::employed()->with(['department', 'branch']);

        if ($departmentId) {
            $users->where('department_id', $departmentId);
        }

        if ($branchId) {
            $users->where('branch_id', $branchId);
        }

        $users = $users->orderBy('name')->get();

        $presences = Presence::whereYear('in', $month->year)
            ->whereMonth('in', $month->month)
            ->get()
            ->groupBy('user_id');

        $leaveDays = app(LeaveService::class);

        return $users->map(function (User $user) use ($presences, $month, $leaveDays) {
            $rows = $presences->get((string) $user->id) ?? $presences->get($user->id) ?? collect();
            $leave = $leaveDays->approvedLeaveDaysInMonth($user->id, $month);

            return [
                'user'        => $user,
                'department'  => $user->department?->name ?? '—',
                'branch'      => $user->branch?->name ?? '—',
                'present'     => $rows->count(),
                'late'        => $rows->where('is_late', true)->count(),
                'late_minutes' => (int) $rows->sum('late_minute'),
                'overtime'    => $rows->where('is_overtime', true)->count(),
                'outside'     => $rows->where('outside', true)->count(),
                'leave_paid'  => $leave['paid'],
                'leave_unpaid' => $leave['unpaid'],
            ];
        });
    }

    /**
     * Outstanding loan balance per employee.
     */
    public function loanReport(): Collection
    {
        $borrowed = Loan::selectRaw('user_id, SUM(amount) as total')
            ->groupBy('user_id')->pluck('total', 'user_id');

        $repaid = LoanPayment::selectRaw('user_id, SUM(amount) as total')
            ->groupBy('user_id')->pluck('total', 'user_id');

        return User::with('department')
            ->whereIn('id', $borrowed->keys()->map(fn ($k) => (int) $k)->all() ?: [0])
            ->get()
            ->map(function (User $user) use ($borrowed, $repaid) {
                $b = (int) ($borrowed[$user->id] ?? $borrowed[(string) $user->id] ?? 0);
                $r = (int) ($repaid[$user->id] ?? $repaid[(string) $user->id] ?? 0);

                return [
                    'user'        => $user,
                    'department'  => $user->department?->name ?? '—',
                    'borrowed'    => $b,
                    'repaid'      => $r,
                    'outstanding' => max(0, $b - $r),
                ];
            })
            ->filter(fn ($row) => $row['outstanding'] > 0)
            ->sortByDesc('outstanding')
            ->values();
    }

    /**
     * Salary totals per month, optionally scoped to a department.
     */
    public function salaryReport(string $recapMonth, ?int $departmentId = null): Collection
    {
        $query = SalaryRecap::with('user.department')->where('recap_month', $recapMonth);

        if ($departmentId) {
            $query->whereHas('user', fn ($q) => $q->where('department_id', $departmentId));
        }

        return $query->get()->map(fn (SalaryRecap $r) => [
            'user'       => $r->user,
            'department' => $r->user?->department?->name ?? '—',
            'work_day'   => (int) $r->work_day,
            'salary'     => (int) $r->salary_amount,
            'overtime'   => (int) $r->overtime_amount,
            'deductions' => (int) ($r->loan_cut + $r->late_cut + $r->abstain_cut),
            'pph21'      => (int) $r->pph21,
            'received'   => (int) $r->received,
            'paid'       => (bool) $r->paid,
        ])->sortBy(fn ($row) => $row['user']?->name ?? '')->values();
    }

    /**
     * Weekday count, used as an approximation of working days when computing
     * an org-wide attendance rate.
     */
    private function weekdaysIn(Carbon $month): int
    {
        $count = 0;
        $day = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        while ($day->lte($end)) {
            if (! $day->isWeekend()) {
                $count++;
            }
            $day->addDay();
        }

        return $count;
    }

    /**
     * Dashboard figures are cached; call this after data changes if a figure
     * must be immediately fresh.
     */
    public function flushCache(?Carbon $date = null): void
    {
        $date ??= now();
        Cache::forget('dashboard.today.' . $date->toDateString());
    }
}
