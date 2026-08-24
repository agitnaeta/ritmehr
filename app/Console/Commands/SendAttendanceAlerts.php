<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Models\Presence;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Console\Command;

/**
 * Morning/evening attendance alerts for HR.
 *
 * Scheduled twice a day (see Console\Kernel) with a different --type each time.
 */
class SendAttendanceAlerts extends Command
{
    protected $signature = 'notify:attendance
                            {--type=checkin : checkin | checkout | late}
                            {--date= : Y-m-d, defaults to today}';

    protected $description = 'Alert HR about missing check-ins, missing check-outs, or lateness';

    public function handle(NotificationService $notifications): int
    {
        $date = $this->option('date') ? \Carbon\Carbon::parse($this->option('date')) : now();
        $type = $this->option('type');

        $employees = User::employed()->get();

        if ($employees->isEmpty()) {
            $this->info('No employed users — nothing to check.');

            return self::SUCCESS;
        }

        [$notificationType, $names] = match ($type) {
            'checkin'  => [Notification::MISSING_CHECKIN, $this->missingCheckIn($employees, $date)],
            'checkout' => [Notification::MISSING_CHECKOUT, $this->missingCheckOut($employees, $date)],
            'late'     => [Notification::LATE_ALERT, $this->lateToday($date)],
            default    => [null, collect()],
        };

        if ($notificationType === null) {
            $this->error("Unknown --type [{$type}]. Use checkin, checkout, or late.");

            return self::FAILURE;
        }

        if ($names->isEmpty()) {
            $this->info("Nothing to report for [{$type}] on {$date->toDateString()}.");

            return self::SUCCESS;
        }

        $reached = $notifications->notifyRole('hr_admin', $notificationType, [
            'count' => $names->count(),
            'date'  => $date->toDateString(),
            'names' => $names->values()->all(),
        ]);

        $this->info("Reported {$names->count()} employee(s) to {$reached} HR user(s).");

        return self::SUCCESS;
    }

    /**
     * Employees with no presence row at all for the day.
     */
    private function missingCheckIn($employees, $date)
    {
        $present = Presence::whereDate('in', $date->toDateString())
            ->pluck('user_id')
            ->map(fn ($id) => (string) $id)
            ->all();

        return $employees
            ->reject(fn (User $u) => in_array((string) $u->id, $present, true))
            ->pluck('name');
    }

    /**
     * Employees who checked in but never checked out.
     */
    private function missingCheckOut($employees, $date)
    {
        $openUserIds = Presence::whereDate('in', $date->toDateString())
            ->whereNull('out')
            ->pluck('user_id')
            ->map(fn ($id) => (string) $id)
            ->all();

        return $employees
            ->filter(fn (User $u) => in_array((string) $u->id, $openUserIds, true))
            ->pluck('name');
    }

    private function lateToday($date)
    {
        return Presence::with('user')
            ->whereDate('in', $date->toDateString())
            ->where('is_late', true)
            ->get()
            ->map(fn (Presence $p) => $p->user?->name)
            ->filter();
    }
}
