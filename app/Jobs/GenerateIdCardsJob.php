<?php

namespace App\Jobs;

use App\Models\PrintJob;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;

/**
 * UM-11 — Generate PDF kartu ID batch besar di background (queue worker).
 *
 * Menghindari browser hang/timeout untuk ribuan kartu: render PDF di worker,
 * simpan ke storage, lalu user mengunduh saat siap (polling status).
 */
class GenerateIdCardsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    public function __construct(public int $printJobId)
    {
    }

    public function handle(): void
    {
        $job = PrintJob::find($this->printJobId);
        if (! $job) {
            return;
        }

        $job->forceFill([
            'status'     => PrintJob::STATUS_PROCESSING,
            'started_at' => now(),
        ])->saveQuietly();

        try {
            $users = User::query()
                ->whereIn('id', $job->target_ids ?? [])
                ->get();

            // Bangun PDF via controller helper (satu sumber kebenaran layout).
            $controller = App::make(\App\Http\Controllers\Admin\UserCrudController::class);
            $pdf = $controller->buildIdCardPdf($users);

            if (! $pdf) {
                $job->forceFill([
                    'status'      => PrintJob::STATUS_FAILED,
                    'message'     => 'Profil perusahaan belum lengkap (logo / template kartu). Lengkapi dulu.',
                    'finished_at' => now(),
                ])->saveQuietly();
                return;
            }

            $relPath = 'print/id-cards-' . $job->id . '.pdf';
            Storage::disk('local')->put($relPath, $pdf->output());

            $job->forceFill([
                'status'      => PrintJob::STATUS_DONE,
                'processed'   => $users->count(),
                'file_path'   => $relPath,
                'finished_at' => now(),
            ])->saveQuietly();
        } catch (\Throwable $e) {
            $job->forceFill([
                'status'      => PrintJob::STATUS_FAILED,
                'message'     => 'Gagal membuat PDF: ' . $e->getMessage(),
                'finished_at' => now(),
            ])->saveQuietly();
            throw $e;
        }
    }

    public function failed(\Throwable $e): void
    {
        $job = PrintJob::find($this->printJobId);
        if ($job && ! $job->isFinished()) {
            $job->forceFill([
                'status'      => PrintJob::STATUS_FAILED,
                'message'     => 'Job gagal: ' . $e->getMessage(),
                'finished_at' => now(),
            ])->saveQuietly();
        }
    }
}
