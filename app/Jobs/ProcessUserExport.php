<?php

namespace App\Jobs;

use App\Exports\UserExport;
use App\Models\ExportJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Facades\Excel;

/**
 * UM-10 — Export karyawan ke XLSX di background (queue worker).
 *
 * Menulis file ke disk 'local' lalu men-set status ExportJob=done supaya
 * user bisa mengunduh via halaman status (tanpa memblokir request).
 */
class ProcessUserExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    public function __construct(public int $exportJobId)
    {
    }

    public function handle(): void
    {
        $job = ExportJob::find($this->exportJobId);
        if (! $job) {
            return;
        }

        $job->forceFill([
            'status'     => ExportJob::STATUS_PROCESSING,
            'started_at' => now(),
        ])->saveQuietly();

        try {
            $relPath = 'exports/user-' . $job->id . '.xlsx';

            // UserExport di-scope via viewerId (queue-safe) = pemilik job.
            Excel::store(new UserExport(null, $job->user_id), $relPath, 'local');

            $job->forceFill([
                'status'      => ExportJob::STATUS_DONE,
                'file_path'   => $relPath,
                'finished_at' => now(),
                'expires_at'  => now()->addDay(), // retensi 24 jam
            ])->saveQuietly();
        } catch (\Throwable $e) {
            $job->forceFill([
                'status'      => ExportJob::STATUS_FAILED,
                'message'     => 'Gagal membuat file export: ' . $e->getMessage(),
                'finished_at' => now(),
            ])->saveQuietly();
            throw $e;
        }
    }

    public function failed(\Throwable $e): void
    {
        $job = ExportJob::find($this->exportJobId);
        if ($job && ! $job->isFinished()) {
            $job->forceFill([
                'status'      => ExportJob::STATUS_FAILED,
                'message'     => 'Job gagal: ' . $e->getMessage(),
                'finished_at' => now(),
            ])->saveQuietly();
        }
    }
}
