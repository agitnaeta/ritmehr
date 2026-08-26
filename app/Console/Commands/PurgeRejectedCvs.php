<?php

namespace App\Console\Commands;

use App\Models\Applicant;
use App\Models\JobOpening;
use App\Services\Matching\MatchingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * M17-5 + M18-6 — CV retention safety net. Two purge conditions, both driven by
 * config (no hardcoded windows):
 *
 *   1. Rejected retention  — rejected applicants past `recruitment_cv_retention_days`
 *      (default 30) still holding a CV.
 *   2. Ghosting retention  — non-hired applicants whose opening has been CLOSED
 *      for more than `recruitment_ghost_retention_days` (default 90) but were
 *      never explicitly rejected. Closes the "CV numpuk selamanya" hole.
 *
 * Idempotent: already-purged rows (cv_purged_at set / cv_path null) are skipped.
 */
class PurgeRejectedCvs extends Command
{
    protected $signature = 'recruitment:purge-cvs {--days= : Override rejected retention days} {--dry-run : Report only}';

    protected $description = 'Purge CVs past their retention window (rejected + ghosted applicants).';

    public function handle(MatchingService $matcher): int
    {
        $rejectDays = (int) ($this->option('days') ?: setting('recruitment_cv_retention_days') ?: 30);
        $ghostDays  = (int) (setting('recruitment_ghost_retention_days') ?: 90);
        $dry = (bool) $this->option('dry-run');

        // ── 1. Rejected past retention ─────────────────────────
        $rejected = Applicant::whereNotNull('rejected_at')
            ->where('rejected_at', '<=', now()->subDays($rejectDays))
            ->whereNull('cv_purged_at')
            ->whereNotNull('cv_path')
            ->get();

        // ── 2. Ghosted: non-hired, never rejected, opening closed long ago ──
        $closedOpeningIds = JobOpening::where('status', JobOpening::STATUS_CLOSED)
            ->where(function ($q) use ($ghostDays) {
                $q->where('closed_at', '<=', now()->subDays($ghostDays))
                  ->orWhere('updated_at', '<=', now()->subDays($ghostDays));
            })
            ->pluck('id');

        $ghosted = Applicant::whereIn('job_opening_id', $closedOpeningIds)
            ->whereNull('hired_user_id')
            ->whereNull('rejected_at')
            ->whereNull('cv_purged_at')
            ->whereNotNull('cv_path')
            ->get();

        $all = $rejected->concat($ghosted)->unique('id');

        if ($all->isEmpty()) {
            $this->info("No CVs to purge (rejected>{$rejectDays}d, ghosted>{$ghostDays}d).");
            return self::SUCCESS;
        }

        $purged = 0;
        foreach ($all as $app) {
            $reason = $app->rejected_at ? 'rejected' : 'ghosted';
            $this->line(($dry ? '[dry] ' : '') . "Purging CV of #{$app->id} {$app->name} ({$reason})");

            if ($dry) {
                continue;
            }

            try {
                if ($app->cv_path) {
                    // Delete from both hot and (if archived) cold — best effort.
                    Storage::disk('local')->delete($app->cv_path);
                }
                $matcher->forget($app);

                $app->forceFill([
                    'cv_path'      => null,
                    'cv_text'      => null,
                    'cv_purged_at' => now(),
                ])->saveQuietly();

                $purged++;
            } catch (\Throwable $e) {
                Log::channel('daily_log')->warning("Purge CV failed for #{$app->id}: " . $e->getMessage());
                $this->warn("  failed: {$e->getMessage()}");
            }
        }

        $this->info($dry
            ? "Dry run: {$all->count()} would be purged ({$rejected->count()} rejected, {$ghosted->count()} ghosted)."
            : "Purged {$purged} CV(s).");

        return self::SUCCESS;
    }
}
