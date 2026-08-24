<?php

namespace App\Console\Commands;

use App\Services\LeaveService;
use Illuminate\Console\Command;

/**
 * Start-of-year leave setup: create the new year's balances and optionally
 * carry unused days over from the previous year.
 *
 * Both operations are idempotent, so the scheduled run is safe to repeat.
 */
class GenerateLeaveBalances extends Command
{
    protected $signature = 'leave:generate-balances
                            {--year= : Target year, defaults to the current one}
                            {--carry-over : Also carry unused days from the previous year}
                            {--max-carry= : Cap on carried days (unlimited if omitted)}';

    protected $description = 'Generate yearly leave balances for all employed staff';

    public function handle(LeaveService $leaveService): int
    {
        $year = (int) ($this->option('year') ?: now()->year);

        if ($year < 2000 || $year > 2100) {
            $this->error("Refusing to work with year [{$year}].");

            return self::FAILURE;
        }

        $created = $leaveService->generateYearlyBalances($year);
        $this->info("Created {$created} leave balance(s) for {$year}.");

        if ($this->option('carry-over')) {
            $maxCarry = $this->option('max-carry');
            $applied = $leaveService->carryOver(
                $year - 1,
                $year,
                $maxCarry !== null ? (int) $maxCarry : null
            );
            $this->info("Applied carry-over to {$applied} balance(s) from " . ($year - 1) . ".");
        }

        return self::SUCCESS;
    }
}
