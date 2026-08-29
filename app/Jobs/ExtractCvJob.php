<?php

namespace App\Jobs;

use App\Models\Applicant;
use App\Services\CvExtractionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * ST-01 / PERF-1 — Ekstraksi teks CV (spawn proses Python) dijalankan di queue,
 * bukan sinkron di dalam request lamaran publik.
 *
 * Dengan QUEUE_CONNECTION=sync (default & test) job berjalan inline sehingga
 * perilaku lama (cv_text terisi seketika) dipertahankan. Dengan driver async
 * (database/redis) request lamaran balas instan dan ekstraksi diproses worker.
 */
class ExtractCvJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;
    public int $tries = 2;

    public function __construct(public int $applicantId)
    {
    }

    public function handle(CvExtractionService $svc): void
    {
        if ($applicant = Applicant::find($this->applicantId)) {
            $svc->extractFor($applicant);
        }
    }
}
