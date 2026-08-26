<?php

namespace App\Services;

use App\Models\Applicant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * M17-3 — Extract plain text from an applicant's uploaded CV (PDF/DOCX/TXT)
 * using the bundled pymupdf script. Text feeds full-text search (M17-3) and
 * the embedding/LLM matching pipeline (M17-4/4b).
 *
 * Graceful by design: if extraction fails (missing python, unreadable file),
 * it logs and returns null rather than breaking the application flow.
 */
class CvExtractionService
{
    private const SCRIPT = 'scripts/extract_cv.py';

    /**
     * Extract text from the CV stored at $cvPath (on the local disk) and, if
     * an Applicant is given, persist it to cv_text.
     */
    public function extractFor(Applicant $applicant): ?string
    {
        if (! $applicant->cv_path) {
            return null;
        }

        $text = $this->extractFromDisk($applicant->cv_path);

        if ($text !== null && $text !== '') {
            $applicant->cv_text = $text;
            $applicant->saveQuietly();
        }

        return $text;
    }

    /**
     * Extract text from a file path on the 'local' disk. Returns null on any
     * failure (never throws into the caller).
     */
    public function extractFromDisk(string $relativePath): ?string
    {
        $disk = Storage::disk('local');

        if (! $disk->exists($relativePath)) {
            Log::channel('daily_log')->warning("CV not found for extraction: {$relativePath}");
            return null;
        }

        $absolute = $disk->path($relativePath);

        return $this->runExtractor($absolute);
    }

    /**
     * Run the python extractor against an absolute file path.
     */
    public function runExtractor(string $absolutePath): ?string
    {
        $python = $this->pythonBinary();
        $script = base_path(self::SCRIPT);

        try {
            $process = new Process([$python, $script, $absolutePath]);
            $process->setTimeout(30);
            $process->run();

            if (! $process->isSuccessful()) {
                Log::channel('daily_log')->warning('CV extract failed: ' . $process->getErrorOutput());
                return null;
            }

            $text = trim($process->getOutput());

            return $text !== '' ? $text : null;
        } catch (\Throwable $e) {
            Log::channel('daily_log')->warning('CV extract exception: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Resolve the python binary. Configurable via setting/env so it works on
     * hosts where python lives elsewhere.
     */
    private function pythonBinary(): string
    {
        return (string) (setting('cv_python_bin', null) ?: env('PYTHON_BIN', 'python3'));
    }
}
