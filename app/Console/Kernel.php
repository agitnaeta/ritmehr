<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('backup:run --only-db')->dailyAt("23:00");
        $schedule->command('db:seed RecalculatePresence')->dailyAt("23:30");
        $schedule->command('audit:prune --days=90')->monthlyOn(1, '02:00');

        // Attendance alerts to HR (M3) — weekdays only, no point on a Sunday.
        $schedule->command('notify:attendance --type=checkin')->weekdays()->at('08:15');
        $schedule->command('notify:attendance --type=late')->weekdays()->at('09:30');
        $schedule->command('notify:attendance --type=checkout')->weekdays()->at('17:00');

        // Weekly nudge for anyone sitting on approvals.
        $schedule->command('notify:approval-digest')->mondays()->at('08:00');

        // Documents nearing expiry (contracts, ID cards).
        $schedule->command('documents:notify-expiring --days=30')->weekly()->mondays()->at('07:30');

        // New year's leave balances, carrying over at most 6 unused days.
        $schedule->command('leave:generate-balances --carry-over --max-carry=6')
                 ->yearlyOn(1, 1, '01:00');

        // M17-5 — purge CVs of rejected applicants past the retention window.
        $schedule->command('recruitment:purge-cvs')->dailyAt('02:30');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
