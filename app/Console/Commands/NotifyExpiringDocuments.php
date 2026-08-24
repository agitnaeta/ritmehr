<?php

namespace App\Console\Commands;

use App\Services\DocumentService;
use Illuminate\Console\Command;

class NotifyExpiringDocuments extends Command
{
    protected $signature = 'documents:notify-expiring {--days=30 : Look this far ahead}';

    protected $description = 'Warn staff and HR about employee documents nearing expiry';

    public function handle(DocumentService $documents): int
    {
        $days = (int) $this->option('days');
        $count = $documents->notifyExpiring($days);

        $this->info("Raised alerts for {$count} document(s) expiring within {$days} days.");

        return self::SUCCESS;
    }
}
