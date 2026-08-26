<?php

namespace App\Console\Commands;

use App\Models\Applicant;
use App\Services\CvExtractionService;
use Illuminate\Console\Command;

/**
 * M17-3 — Backfill cv_text for applications that have a CV file but no
 * extracted text yet (e.g. uploaded before extraction existed, or where the
 * inline extraction failed transiently).
 */
class ExtractCvText extends Command
{
    protected $signature = 'recruitment:extract-cv {--limit=100 : Max applications to process}';

    protected $description = 'Extract text from applicant CVs into cv_text (pymupdf).';

    public function handle(CvExtractionService $extractor): int
    {
        $limit = (int) $this->option('limit');

        $pending = Applicant::whereNotNull('cv_path')
            ->whereNull('cv_purged_at')
            ->where(function ($q) {
                $q->whereNull('cv_text')->orWhere('cv_text', '');
            })
            ->limit($limit)
            ->get();

        if ($pending->isEmpty()) {
            $this->info('No CVs pending extraction.');
            return self::SUCCESS;
        }

        $ok = 0;
        $fail = 0;
        foreach ($pending as $app) {
            $text = $extractor->extractFor($app);
            if ($text) {
                $ok++;
                $this->line("  ✓ #{$app->id} {$app->name} (" . strlen($text) . ' chars)');
            } else {
                $fail++;
                $this->warn("  ✗ #{$app->id} {$app->name} — extraction failed");
            }
        }

        $this->info("Done. Extracted {$ok}, failed {$fail}.");

        return self::SUCCESS;
    }
}
