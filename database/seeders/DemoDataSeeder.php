<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\CompanyProfile;
use App\Models\Day;
use App\Models\Department;
use App\Models\EmployeeTaxProfile;
use App\Models\LeaveType;
use App\Models\Loan;
use App\Models\LoanPayment;
use App\Models\Position;
use App\Models\Presence;
use App\Models\Salary;
use App\Models\Schedule;
use App\Models\ScheduleDayOff;
use App\Models\User;
use App\Services\ApprovalService;
use App\Services\LeaveService;
use App\Services\TaxService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Demo/staging data — a small company with a full month of history.
 *
 * NOT for production: it creates users with a known password.
 */
class DemoDataSeeder extends Seeder
{
    private const PASSWORD = 'password';

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->error('Refusing to seed demo data in production.');

            return;
        }

        $schedule = $this->schedule();
        $branch = $this->branch();
        [$it, $hr, $ops] = $this->departments();
        $positions = $this->positions($it, $hr, $ops);

        $people = $this->people($schedule, $branch, $it, $hr, $ops, $positions);

        $calendar = $this->attendance($people);
        $this->leave($people, $calendar);
        $this->loans($people, $calendar);
        $this->payroll($people, $calendar);

        $this->command?->newLine();
        $this->command?->info('Demo data ready. Log in at /admin/login:');
        $this->command?->table(
            ['Email', 'Password', 'Role'],
            [
                ['siti@demo.test',   self::PASSWORD, 'super_admin'],
                ['rina@demo.test',   self::PASSWORD, 'hr_admin'],
                ['budi@demo.test',   self::PASSWORD, 'manager'],
                ['ahmad@demo.test',  self::PASSWORD, 'employee'],
                ['dewi@demo.test',   self::PASSWORD, 'employee'],
            ]
        );
    }

    private function schedule(): Schedule
    {
        $schedule = Schedule::firstOrCreate(
            ['name' => 'Reguler 08-17'],
            ['in' => '08:00:00', 'out' => '17:00:00',
             'over_in' => '18:00:00', 'over_out' => '22:00:00']
        );

        foreach (['Sabtu', 'Minggu'] as $name) {
            $day = Day::firstOrCreate(['name' => $name]);
            ScheduleDayOff::firstOrCreate([
                'schedule_id' => $schedule->id,
                'day'         => $day->id,
            ]);
        }

        return $schedule;
    }

    private function branch(): Branch
    {
        CompanyProfile::firstOrCreate(['id' => 1], [
            'name' => 'PT Demo Nusantara', 'address' => 'Jakarta', 'phone' => '021-555-0100',
        ]);

        return Branch::firstOrCreate(
            ['code' => 'JKT'],
            [
                'company_profile_id' => 1,
                'name'               => 'Kantor Jakarta',
                'address'            => 'Jl. Merdeka No. 1, Jakarta Pusat',
                'lat'                => -6.1753924,
                'lng'                => 106.8271528,
                'radius_meters'      => 150,
                'is_active'          => true,
            ]
        );
    }

    /** @return Department[] */
    private function departments(): array
    {
        $ho = Department::firstOrCreate(['code' => 'HO'], ['name' => 'Head Office']);

        return [
            Department::firstOrCreate(['code' => 'IT'], ['name' => 'Teknologi', 'parent_id' => $ho->id]),
            Department::firstOrCreate(['code' => 'HRD'], ['name' => 'HRD', 'parent_id' => $ho->id]),
            Department::firstOrCreate(['code' => 'OPS'], ['name' => 'Operasional', 'parent_id' => $ho->id]),
        ];
    }

    private function positions(Department $it, Department $hr, Department $ops): array
    {
        return [
            'director'   => Position::firstOrCreate(['name' => 'Direktur'], ['level' => 5]),
            'manager'    => Position::firstOrCreate(['name' => 'Manager'], ['level' => 4, 'department_id' => $it->id]),
            'hr_officer' => Position::firstOrCreate(['name' => 'HR Officer'], ['level' => 3, 'department_id' => $hr->id]),
            'staff'      => Position::firstOrCreate(['name' => 'Staff'], ['level' => 1, 'department_id' => $ops->id]),
        ];
    }

    /** @return array<string, User> */
    private function people(
        Schedule $schedule, Branch $branch,
        Department $it, Department $hr, Department $ops, array $positions
    ): array {
        $siti = $this->user('Siti Rahayu', 'siti@demo.test', [
            'schedule_id' => $schedule->id, 'branch_id' => $branch->id,
            'department_id' => $hr->id, 'position_id' => $positions['director']->id,
            'employee_id' => 'EMP-001', 'join_date' => '2019-03-01',
            'phone' => '081200000001',
        ], 'super_admin');

        $budi = $this->user('Budi Santoso', 'budi@demo.test', [
            'schedule_id' => $schedule->id, 'branch_id' => $branch->id,
            'department_id' => $it->id, 'position_id' => $positions['manager']->id,
            'employee_id' => 'EMP-002', 'join_date' => '2020-06-15',
            'manager_id' => $siti->id, 'phone' => '081200000002',
        ], 'manager');

        $rina = $this->user('Rina Kartika', 'rina@demo.test', [
            'schedule_id' => $schedule->id, 'branch_id' => $branch->id,
            'department_id' => $hr->id, 'position_id' => $positions['hr_officer']->id,
            'employee_id' => 'EMP-003', 'join_date' => '2021-01-11',
            'manager_id' => $siti->id, 'phone' => '081200000003',
        ], 'hr_admin');

        $ahmad = $this->user('Ahmad Fauzi', 'ahmad@demo.test', [
            'schedule_id' => $schedule->id, 'branch_id' => $branch->id,
            'department_id' => $it->id, 'position_id' => $positions['staff']->id,
            'employee_id' => 'EMP-004', 'join_date' => '2023-02-01',
            'manager_id' => $budi->id, 'phone' => '081200000004',
        ], 'employee');

        $dewi = $this->user('Dewi Lestari', 'dewi@demo.test', [
            'schedule_id' => $schedule->id, 'branch_id' => $branch->id,
            'department_id' => $ops->id, 'position_id' => $positions['staff']->id,
            'employee_id' => 'EMP-005', 'join_date' => '2026-03-01',
            'manager_id' => $budi->id, 'phone' => '081200000005',
        ], 'employee');

        $it->update(['head_user_id' => $budi->id]);
        $hr->update(['head_user_id' => $rina->id]);

        $salaries = [
            [$siti, 25_000_000], [$budi, 15_000_000], [$rina, 9_000_000],
            [$ahmad, 7_500_000], [$dewi, 6_000_000],
        ];

        foreach ($salaries as [$user, $amount]) {
            Salary::updateOrCreate(['user_id' => $user->id], [
                'amount'                 => $amount,
                'overtime_amount'        => 75_000,
                'overtime_type'          => 'flat',
                'unpaid_leave_deduction' => (int) round($amount / 22),
                'fine_type'              => 'minute',
                'fine_per_minute'        => 1_000,
                'fine'                   => 0,
                'extra_time'             => 500,
                'extra_time_rule'        => 1,
            ]);

            EmployeeTaxProfile::updateOrCreate(['user_id' => $user->id], [
                'npwp'       => $user->id === 5 ? null : '01.234.567.8-01' . $user->id . '.000',
                'tax_status' => $user->id <= 2 ? 'K/2' : 'TK/0',
                'tax_method' => 'gross',
            ]);
        }

        return compact('siti', 'budi', 'rina', 'ahmad', 'dewi');
    }

    private function user(string $name, string $email, array $attrs, string $role): User
    {
        $user = User::updateOrCreate(
            ['email' => $email],
            array_merge($attrs, [
                'name'              => $name,
                'password'          => Hash::make(self::PASSWORD),
                'employment_status' => User::STATUS_ACTIVE,
            ])
        );

        $user->syncRoles([$role]);

        return $user;
    }

    /**
     * A full previous month of attendance.
     *
     * The previous month is used deliberately: a salary recap always measures
     * against the whole month, so a part-way-through current month reads as
     * mass absence and the payroll figures are meaningless.
     *
     * The story it sets up:
     *   - Ahmad is on approved PAID leave for 3 days  -> no absence, no deduction
     *   - Dewi is simply absent for 2 days            -> absence, deducted
     */
    private function attendance(array $people): array
    {
        $month = now()->subMonthNoOverflow()->startOfMonth();
        $monthEnd = $month->copy()->endOfMonth();

        $workdays = [];
        for ($d = $month->copy(); $d->lte($monthEnd); $d->addDay()) {
            if (! $d->isWeekend()) {
                $workdays[] = $d->copy();
            }
        }

        // Ahmad: three consecutive working days off (approved leave).
        $ahmadLeave = array_slice($workdays, 10, 3);
        $ahmadLeaveDates = array_map(fn ($d) => $d->toDateString(), $ahmadLeave);

        // Dewi: two working days simply missing, with no leave request.
        $dewiAbsent = [$workdays[4]->toDateString(), $workdays[5]->toDateString()];

        $lateDays = [
            'Ahmad Fauzi'  => [$workdays[1]->toDateString(), $workdays[8]->toDateString()],
            'Dewi Lestari' => [$workdays[2]->toDateString()],
        ];
        $overtimeDays = [
            'Budi Santoso' => [$workdays[7]->toDateString(), $workdays[15]->toDateString()],
        ];

        foreach ($people as $user) {
            foreach ($workdays as $day) {
                $date = $day->toDateString();

                if ($user->name === 'Ahmad Fauzi' && in_array($date, $ahmadLeaveDates, true)) {
                    continue;
                }

                if ($user->name === 'Dewi Lestari' && in_array($date, $dewiAbsent, true)) {
                    continue;
                }

                $isLate = in_array($date, $lateDays[$user->name] ?? [], true);
                $isOvertime = in_array($date, $overtimeDays[$user->name] ?? [], true);

                $in = $day->copy()->setTime(8, 0);
                if ($isLate) {
                    $in->addMinutes(15);
                }

                $out = $day->copy()->setTime($isOvertime ? 19 : 17, $isOvertime ? 30 : 5);

                Presence::firstOrCreate(
                    ['user_id' => $user->id, 'in' => $in->format('Y-m-d H:i:s')],
                    [
                        'out'       => $out->format('Y-m-d H:i:s'),
                        'lat'       => -6.1753924,
                        'lng'       => 106.8271528,
                        'branch_id' => $user->branch_id,
                    ]
                );
            }
        }

        $this->command?->info(
            'Attendance seeded for ' . $month->format('m-Y')
            . ' (' . count($workdays) . ' workdays).'
        );

        return [
            'month'       => $month,
            'ahmad_leave' => $ahmadLeave,
            'dewi_absent' => $dewiAbsent,
        ];
    }

    /**
     * Approved leave that lines up with Ahmad's attendance gap, plus one
     * request still sitting in the manager's inbox.
     */
    private function leave(array $people, array $calendar): void
    {
        $leaveService = app(LeaveService::class);
        $approvals = app(ApprovalService::class);

        $annual = LeaveType::where('code', 'annual')->first();
        $sick = LeaveType::where('code', 'sick')->first();

        if (! $annual || ! $sick) {
            $this->command?->warn('Leave types missing — run HrisSeeder first.');

            return;
        }

        $leaveService->generateYearlyBalances((int) $calendar['month']->year);
        $leaveService->generateYearlyBalances((int) now()->year);

        $leaveDays = $calendar['ahmad_leave'];
        $start = $leaveDays[0];
        $end = end($leaveDays);

        try {
            $request = $leaveService->requestLeave(
                $people['ahmad'], $annual, $start->toDateString(), $end->toDateString(),
                'Acara keluarga'
            );
            // The default flow is two steps: manager, then HR. Walk both so
            // the request actually reaches "approved".
            $approvals->approve($request->approval, $people['budi'], 'Disetujui, silakan.');
            $approvals->approve($request->approval->fresh(), $people['rina'], 'Saldo mencukupi.');

            $this->command?->info(
                'Approved PAID leave: Ahmad, ' . $request->periodLabel()
                . ' (status: ' . $request->fresh()->status . ')'
            );
        } catch (\Throwable $e) {
            $this->command?->warn('Leave (approved) skipped: ' . $e->getMessage());
        }

        // Pending: Dewi, next week — sits in Budi's approval inbox.
        try {
            $next = now()->addWeek()->startOfWeek();
            $pending = $leaveService->requestLeave(
                $people['dewi'], $sick, $next->toDateString(),
                $next->copy()->addDay()->toDateString(),
                'Demam', 'demo/surat-dokter.pdf'
            );
            $this->command?->info('Pending leave: Dewi, ' . $pending->periodLabel());
        } catch (\Throwable $e) {
            $this->command?->warn('Leave (pending) skipped: ' . $e->getMessage());
        }
    }

    private function loans(array $people, array $calendar): void
    {
        Loan::firstOrCreate(
            ['user_id' => $people['ahmad']->id, 'date' => $calendar['month']->toDateString()],
            ['amount' => 3_000_000]
        );

        LoanPayment::firstOrCreate(
            ['user_id' => $people['ahmad']->id, 'date' => $calendar['month']->copy()->addDays(15)->toDateString()],
            ['amount' => 1_000_000]
        );

        $this->command?->info('Seeded a loan with one repayment (sisa Rp 2.000.000).');
    }

    /**
     * Force a payroll recalculation, then layer tax/BPJS on top.
     */
    private function payroll(array $people, array $calendar): void
    {
        $tax = app(TaxService::class);
        $recapMonth = $calendar['month']->format('m-Y');

        foreach ($people as $user) {
            $recap = \App\Models\SalaryRecap::where('user_id', $user->id)
                ->where('recap_month', $recapMonth)
                ->first();

            if (! $recap) {
                continue;
            }

            // Force a fresh payroll pass now that leave exists, then layer
            // tax and BPJS on top.
            app(\App\Services\SalaryService::class)->calculateSalaryRecap($recap);
            $tax->applyToRecap($recap->fresh());
        }

        $this->command?->info("Applied tax & BPJS to salary recaps for {$recapMonth}.");
    }
}
