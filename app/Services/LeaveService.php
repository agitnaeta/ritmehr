<?php

namespace App\Services;

use App\Models\Approval;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveRequestDate;
use App\Models\LeaveType;
use App\Models\NationalHoliday;
use App\Models\Notification;
use App\Models\SalaryRecap;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Leave requests, balances, and the bridge into payroll.
 *
 * The payroll bridge is the important part: before this module existed, any
 * day an employee was not present counted as an unpaid absence. Approved leave
 * must not be treated that way.
 */
class LeaveService
{
    public function __construct(
        private readonly PresenceService $presenceService,
        private readonly ApprovalService $approvalService,
        private readonly NotificationService $notifications,
    ) {
    }

    // ── Requesting ─────────────────────────────────────────

    /**
     * Create a leave request and push it into the approval chain.
     *
     * @throws \DomainException on any business-rule violation
     */
    public function requestLeave(
        User $user,
        LeaveType $type,
        $startDate,
        $endDate,
        ?string $reason = null,
        ?string $attachment = null,
    ): LeaveRequest {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->startOfDay();

        if ($end->lt($start)) {
            throw new \DomainException('Tanggal selesai tidak boleh sebelum tanggal mulai.');
        }

        if (! $type->is_active) {
            throw new \DomainException('Jenis cuti ini sedang tidak aktif.');
        }

        $workingDates = $this->workingDatesBetween($user, $start, $end);

        if (empty($workingDates)) {
            throw new \DomainException(
                'Rentang tanggal yang dipilih tidak mengandung hari kerja.'
            );
        }

        $totalDays = count($workingDates);

        if ($type->max_consecutive_days && $totalDays > $type->max_consecutive_days) {
            throw new \DomainException(
                "Maksimal {$type->max_consecutive_days} hari berturut-turut untuk {$type->name}."
            );
        }

        if ($type->requires_attachment && ! $attachment) {
            throw new \DomainException("Lampiran wajib untuk pengajuan {$type->name}.");
        }

        $this->assertNoOverlap($user, $start, $end);

        // Quota is checked here for fast feedback, and re-checked under a lock
        // when the request is finally approved.
        if ($type->hasQuota()) {
            $balance = $this->getBalance($user, $type, (int) $start->year);

            if (! $balance->canCover($totalDays)) {
                throw new \DomainException(
                    "Saldo {$type->name} tidak mencukupi: sisa {$balance->remainingDays()} hari, "
                    . "diajukan {$totalDays} hari."
                );
            }
        }

        return DB::transaction(function () use (
            $user, $type, $start, $end, $totalDays, $reason, $attachment, $workingDates
        ) {
            $request = LeaveRequest::create([
                'user_id'       => $user->id,
                'leave_type_id' => $type->id,
                'start_date'    => $start->toDateString(),
                'end_date'      => $end->toDateString(),
                'total_days'    => $totalDays,
                'reason'        => $reason,
                'attachment'    => $attachment,
                'status'        => LeaveRequest::STATUS_PENDING,
            ]);

            foreach ($workingDates as $date) {
                LeaveRequestDate::create([
                    'leave_request_id' => $request->id,
                    'date'             => $date,
                    'day_value'        => 1.0,
                ]);
            }

            $this->approvalService->submitForApproval($request, $user, 'leave');

            return $request->fresh(['dates', 'leaveType']);
        });
    }

    /**
     * Refuse a second request covering days already requested or approved.
     */
    private function assertNoOverlap(User $user, Carbon $start, Carbon $end): void
    {
        $clash = LeaveRequest::where('user_id', $user->id)
            ->blocking()
            ->overlapping($start->toDateString(), $end->toDateString())
            ->first();

        if ($clash) {
            throw new \DomainException(
                'Sudah ada pengajuan cuti pada rentang tanggal tersebut ('
                . $clash->periodLabel() . ', ' . $clash->statusLabel() . ').'
            );
        }
    }

    /**
     * Working dates in the range: weekends per the user's schedule and
     * national holidays are excluded, because they are not chargeable leave.
     *
     * @return string[] Y-m-d
     */
    public function workingDatesBetween(User $user, Carbon $start, Carbon $end): array
    {
        $offDays = $this->offDayNames($user);
        $holidays = $this->holidayDates($start, $end);

        $dates = [];

        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            $name = Str::lower($day->locale('id_ID')->isoFormat('dddd'));

            if ($offDays->contains($name)) {
                continue;
            }

            if (in_array($day->toDateString(), $holidays, true)) {
                continue;
            }

            $dates[] = $day->toDateString();
        }

        return $dates;
    }

    public function calculateLeaveDays(User $user, $startDate, $endDate): int
    {
        return count($this->workingDatesBetween(
            $user,
            Carbon::parse($startDate)->startOfDay(),
            Carbon::parse($endDate)->startOfDay()
        ));
    }

    /**
     * A user with no schedule has no configured off days — treat every day as
     * workable rather than crashing.
     */
    private function offDayNames(User $user): Collection
    {
        try {
            if (! $user->schedule) {
                return collect();
            }

            return $this->presenceService->useOffDays($user);
        } catch (\Throwable $e) {
            Log::warning('[Leave] could not resolve off days', [
                'user_id' => $user->id,
                'message' => $e->getMessage(),
            ]);

            return collect();
        }
    }

    /**
     * @return string[] Y-m-d
     */
    private function holidayDates(Carbon $start, Carbon $end): array
    {
        return NationalHoliday::whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->pluck('date')
            ->map(fn ($d) => Carbon::parse($d)->toDateString())
            ->all();
    }

    // ── Approval outcomes ──────────────────────────────────

    /**
     * Called when the approval chain fully approves the request: mark it
     * approved and spend the balance atomically.
     */
    public function finaliseApproval(LeaveRequest $request, Approval $approval): void
    {
        DB::transaction(function () use ($request, $approval) {
            $fresh = LeaveRequest::whereKey($request->getKey())->lockForUpdate()->firstOrFail();

            // Guard against a double-fire re-spending the balance.
            if ($fresh->status === LeaveRequest::STATUS_APPROVED) {
                return;
            }

            $type = $fresh->leaveType;

            if ($type && $type->hasQuota()) {
                $this->consumeBalance($fresh, (float) $fresh->total_days);
            }

            // reorder() clears the relation's own step_order sort — without it
            // the "latest" action would still resolve to step 1.
            $lastAction = $approval->actions()
                ->reorder()
                ->orderByDesc('acted_at')
                ->orderByDesc('step_order')
                ->first();

            $fresh->forceFill([
                'status'      => LeaveRequest::STATUS_APPROVED,
                'approved_by' => $lastAction?->acted_by,
                'approved_at' => now(),
            ])->save();

            $request->setRawAttributes($fresh->getAttributes(), true);
        });

        $this->notifyOutcome($request, true);
        $this->warnIfBalanceLow($request);
    }

    /**
     * Spend $days from the matching balance, under a row lock so two
     * concurrent approvals cannot both pass the sufficiency check.
     *
     * @throws \DomainException when the balance no longer covers the request
     */
    private function consumeBalance(LeaveRequest $request, float $days): void
    {
        $year = Carbon::parse($request->start_date)->year;

        $balance = LeaveBalance::where('user_id', $request->user_id)
            ->where('leave_type_id', $request->leave_type_id)
            ->where('year', $year)
            ->lockForUpdate()
            ->first();

        if (! $balance) {
            $balance = $this->getBalance(
                $request->user,
                $request->leaveType,
                $year
            );
            $balance = LeaveBalance::whereKey($balance->id)->lockForUpdate()->first();
        }

        if (! $balance->canCover($days)) {
            throw new \DomainException(
                'Saldo cuti tidak lagi mencukupi saat persetujuan diproses (sisa '
                . $balance->remainingDays() . ' hari).'
            );
        }

        $balance->used = (int) $balance->used + (int) ceil($days);
        $balance->save();
    }

    /**
     * Give back days when an approved request is later cancelled.
     */
    public function releaseBalance(LeaveRequest $request): void
    {
        if (! $request->leaveType?->hasQuota()) {
            return;
        }

        DB::transaction(function () use ($request) {
            $year = Carbon::parse($request->start_date)->year;

            $balance = LeaveBalance::where('user_id', $request->user_id)
                ->where('leave_type_id', $request->leave_type_id)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if (! $balance) {
                return;
            }

            $balance->used = max(0, (int) $balance->used - (int) ceil((float) $request->total_days));
            $balance->save();
        });
    }

    /**
     * Cancel a request. Approved requests give their balance back.
     *
     * @throws \DomainException when the request is already resolved
     */
    public function cancel(LeaveRequest $request, User $actor): LeaveRequest
    {
        if (in_array($request->status, [
            LeaveRequest::STATUS_CANCELLED,
            LeaveRequest::STATUS_REJECTED,
        ], true)) {
            throw new \DomainException('Pengajuan ini sudah tidak aktif.');
        }

        if ((int) $request->user_id !== (int) $actor->id) {
            throw new \DomainException('Hanya pemohon yang bisa membatalkan pengajuan.');
        }

        $wasApproved = $request->isApprovedLeave();

        if ($request->approval && $request->approval->isPending()) {
            $this->approvalService->cancel($request->approval, $actor);
        }

        $request->forceFill(['status' => LeaveRequest::STATUS_CANCELLED])->save();

        if ($wasApproved) {
            $this->releaseBalance($request);
        }

        return $request;
    }

    public function notifyOutcome(LeaveRequest $request, bool $approved): void
    {
        try {
            $request->loadMissing(['user', 'leaveType']);

            if (! $request->user) {
                return;
            }

            $this->notifications->notify(
                $request->user,
                $approved ? Notification::LEAVE_APPROVED : Notification::LEAVE_REJECTED,
                [
                    'leave_request_id' => $request->id,
                    'leave_type'       => $request->leaveType?->name,
                    'start_date'       => Carbon::parse($request->start_date)->toDateString(),
                    'end_date'         => Carbon::parse($request->end_date)->toDateString(),
                    'reason'           => $request->rejection_reason,
                ]
            );
        } catch (\Throwable $e) {
            Log::error('[Leave] outcome notification failed', [
                'leave_request_id' => $request->id,
                'message'          => $e->getMessage(),
            ]);
        }
    }

    private function warnIfBalanceLow(LeaveRequest $request, int $threshold = 3): void
    {
        try {
            $type = $request->leaveType;

            if (! $type?->hasQuota() || ! $request->user) {
                return;
            }

            $balance = $this->getBalance($request->user, $type, Carbon::parse($request->start_date)->year);

            if ($balance->remainingDays() <= $threshold) {
                $this->notifications->notify($request->user, Notification::LEAVE_BALANCE_LOW, [
                    'leave_type' => $type->name,
                    'remaining'  => $balance->remainingDays(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('[Leave] low-balance warning failed', ['message' => $e->getMessage()]);
        }
    }

    // ── Balances ───────────────────────────────────────────

    /**
     * The user's balance for a type/year, created from the type default if
     * it does not exist yet.
     */
    public function getBalance(User $user, LeaveType $type, ?int $year = null): LeaveBalance
    {
        $year ??= (int) now()->year;

        return LeaveBalance::firstOrCreate(
            [
                'user_id'       => $user->id,
                'leave_type_id' => $type->id,
                'year'          => $year,
            ],
            [
                'quota'      => $type->default_quota ?? 0,
                'used'       => 0,
                'carry_over' => 0,
            ]
        )->refresh();
    }

    /**
     * Create missing balances for every employed user and quota-bearing type.
     *
     * @return int how many rows were created
     */
    public function generateYearlyBalances(int $year): int
    {
        $types = LeaveType::active()->whereNotNull('default_quota')->get();
        $users = User::employed()->get();
        $created = 0;

        foreach ($users as $user) {
            foreach ($types as $type) {
                $exists = LeaveBalance::where('user_id', $user->id)
                    ->where('leave_type_id', $type->id)
                    ->where('year', $year)
                    ->exists();

                if ($exists) {
                    continue;
                }

                LeaveBalance::create([
                    'user_id'       => $user->id,
                    'leave_type_id' => $type->id,
                    'year'          => $year,
                    'quota'         => $this->proratedQuota($user, $type, $year),
                    'used'          => 0,
                    'carry_over'    => 0,
                ]);

                $created++;
            }
        }

        return $created;
    }

    /**
     * Someone who joined mid-year gets a proportional quota for that year.
     */
    private function proratedQuota(User $user, LeaveType $type, int $year): int
    {
        $full = (int) ($type->default_quota ?? 0);

        if (! $user->join_date) {
            return $full;
        }

        $join = Carbon::parse($user->join_date);

        if ((int) $join->year < $year) {
            return $full;
        }

        if ((int) $join->year > $year) {
            return 0;
        }

        $monthsRemaining = 12 - ((int) $join->month - 1);

        return (int) floor($full * $monthsRemaining / 12);
    }

    /**
     * Roll unused days from one year into the next, capped at $maxCarry.
     *
     * @return int how many balances received carry-over
     */
    public function carryOver(int $fromYear, int $toYear, ?int $maxCarry = null): int
    {
        $applied = 0;

        $previous = LeaveBalance::with(['leaveType', 'user'])
            ->forYear($fromYear)
            ->get();

        foreach ($previous as $old) {
            $remaining = $old->remainingDays();

            if ($remaining <= 0 || ! $old->leaveType || ! $old->user) {
                continue;
            }

            $carry = $maxCarry !== null ? min($remaining, $maxCarry) : $remaining;

            $target = LeaveBalance::firstOrCreate(
                [
                    'user_id'       => $old->user_id,
                    'leave_type_id' => $old->leave_type_id,
                    'year'          => $toYear,
                ],
                [
                    'quota'      => $old->leaveType->default_quota ?? 0,
                    'used'       => 0,
                    'carry_over' => 0,
                ]
            );

            // Overwrite rather than accumulate, so re-running is idempotent.
            $target->carry_over = $carry;
            $target->save();

            $applied++;
        }

        return $applied;
    }

    // ── Payroll bridge ─────────────────────────────────────

    /**
     * Approved leave days falling inside the recap month, split by whether the
     * leave type is paid.
     *
     * @return array{paid: int, unpaid: int, total: int}
     */
    public function approvedLeaveDaysInMonth($userId, Carbon $month): array
    {
        $start = $month->copy()->startOfMonth()->toDateString();
        $end = $month->copy()->endOfMonth()->toDateString();

        $rows = LeaveRequestDate::query()
            ->join('leave_requests', 'leave_requests.id', '=', 'leave_request_dates.leave_request_id')
            ->join('leave_types', 'leave_types.id', '=', 'leave_requests.leave_type_id')
            ->where('leave_requests.user_id', $userId)
            ->where('leave_requests.status', LeaveRequest::STATUS_APPROVED)
            ->whereBetween('leave_request_dates.date', [$start, $end])
            ->selectRaw('leave_types.is_paid as is_paid, SUM(leave_request_dates.day_value) as days')
            ->groupBy('leave_types.is_paid')
            ->get();

        $paid = 0;
        $unpaid = 0;

        foreach ($rows as $row) {
            if ((bool) $row->is_paid) {
                $paid += (int) round((float) $row->days);
            } else {
                $unpaid += (int) round((float) $row->days);
            }
        }

        return ['paid' => $paid, 'unpaid' => $unpaid, 'total' => $paid + $unpaid];
    }

    /**
     * Same figures, keyed off a SalaryRecap's recap_month (m-Y).
     *
     * @return array{paid: int, unpaid: int, total: int}
     */
    public function approvedLeaveDaysForRecap(SalaryRecap $recap): array
    {
        try {
            $month = Carbon::createFromFormat('m-Y', $recap->recap_month);
        } catch (\Throwable $e) {
            Log::warning('[Leave] unparseable recap_month', [
                'salary_recap_id' => $recap->id,
                'recap_month'     => $recap->recap_month,
            ]);

            return ['paid' => 0, 'unpaid' => 0, 'total' => 0];
        }

        return $this->approvedLeaveDaysInMonth($recap->user_id, $month);
    }

    // ── Reporting ──────────────────────────────────────────

    /**
     * Approved leave overlapping a date range, for the calendar view.
     */
    public function calendarEntries($from, $to, ?int $departmentId = null): Collection
    {
        $query = LeaveRequest::with(['user', 'leaveType'])
            ->approved()
            ->overlapping(Carbon::parse($from)->toDateString(), Carbon::parse($to)->toDateString());

        if ($departmentId) {
            $query->whereHas('user', fn ($q) => $q->where('department_id', $departmentId));
        }

        return $query->get();
    }
}
