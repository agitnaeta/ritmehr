<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SalaryRecap;
use App\Services\SalaryService;

class RecalculateSalary extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'salary:recalculate
        {--month= : Recap month in mm-YYYY format (default: all months)}
        {--user=* : Specific user IDs (default: all users)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculate salary for specific users and/or months';

    /**
     * Execute the console command.
     */
    public function handle(SalaryService $salaryService)
    {
        $month = $this->option('month');
        $userIds = $this->option('user');

        $query = SalaryRecap::query();

        if ($month) {
            $query->where('recap_month', $month);
        }

        if (!empty($userIds)) {
            $query->whereIn('user_id', $userIds);
        }

        $recaps = $query->get();

        if ($recaps->isEmpty()) {
            $this->warn('No salary recaps found matching the criteria.');
            return;
        }

        $this->info("Recalculating {$recaps->count()} salary recap(s)...");

        $bar = $this->output->createProgressBar($recaps->count());
        $bar->start();

        foreach ($recaps as $recap) {
            $salaryService->calculateSalaryRecap($recap);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Done — {$recaps->count()} recap(s) recalculated.");
    }
}
