<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;

class PruneAuditLogs extends Command
{
    protected $signature = 'audit:prune {--days=90 : Days to keep}';

    protected $description = 'Delete audit log entries older than the specified number of days';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoff = now()->subDays($days);

        $deleted = AuditLog::where('created_at', '<', $cutoff)->delete();

        $this->info("Pruned {$deleted} audit log(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
