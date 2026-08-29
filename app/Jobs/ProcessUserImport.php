<?php

namespace App\Jobs;

use App\Imports\UserImport;
use App\Models\ImportJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

/**
 * UM-09 — Proses import karyawan di background (queue worker).
 *
 * Menjalankan UserImport terhadap file yang sudah diunggah, memperbarui
 * baris ImportJob (progress + baris gagal), sehingga request web tidak
 * diblokir dan halaman status bisa polling progress.
 */
class ProcessUserImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Batas waktu job (detik). Import besar butuh waktu; beri kelonggaran. */
    public int $timeout = 1800;

    public function __construct(public int $importJobId)
    {
    }

    public function handle(): void
    {
        $job = ImportJob::find($this->importJobId);
        if (! $job) {
            return;
        }

        $absPath = Storage::disk('local')->path($job->file_path);
        if (! is_file($absPath)) {
            $job->forceFill([
                'status'      => ImportJob::STATUS_FAILED,
                'message'     => 'File impor tidak ditemukan (mungkin sudah kadaluarsa).',
                'finished_at' => now(),
            ])->saveQuietly();
            return;
        }

        $job->forceFill([
            'status'     => ImportJob::STATUS_PROCESSING,
            'started_at' => now(),
        ])->saveQuietly();

        try {
            $import = new UserImport($job);
            Excel::import($import, $absPath);
            $import->finalizeJob();

            // Bersihkan file setelah selesai.
            Storage::disk('local')->delete($job->file_path);
        } catch (\Throwable $e) {
            $job->forceFill([
                'status'      => ImportJob::STATUS_FAILED,
                'message'     => 'Gagal memproses import: ' . $e->getMessage(),
                'finished_at' => now(),
            ])->saveQuietly();
            throw $e; // biarkan masuk failed_jobs untuk diagnosa
        }
    }

    /** Dipanggil bila job gagal permanen. */
    public function failed(\Throwable $e): void
    {
        $job = ImportJob::find($this->importJobId);
        if ($job && ! $job->isFinished()) {
            $job->forceFill([
                'status'      => ImportJob::STATUS_FAILED,
                'message'     => 'Job gagal: ' . $e->getMessage(),
                'finished_at' => now(),
            ])->saveQuietly();
        }
    }
}
