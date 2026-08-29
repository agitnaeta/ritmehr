# ST-01 — `app/Jobs/ExtractCvJob.php`

**Fokus:** PERF-1 — pindahkan ekstraksi CV ke queue (file BARU)
**Severity:** 🟠 Tinggi
**Status:** [ ] TODO — commit: `______`
**File (satu-satunya) yang disentuh:** `app/Jobs/ExtractCvJob.php`

---

## Masalah
`CareerController::apply()` memanggil `CvExtractionService::extractFor()` sinkron
(spawn Python, timeout 30s) di dalam request publik + `QUEUE_CONNECTION=sync`. Pelamar
menunggu, rawan timeout/DoS.

## Langkah (file baru)
```php
<?php

namespace App\Jobs;

use App\Models\Applicant;
use App\Services\CvExtractionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExtractCvJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;
    public int $tries = 2;

    public function __construct(public int $applicantId) {}

    public function handle(CvExtractionService $svc): void
    {
        if ($a = Applicant::find($this->applicantId)) {
            $svc->extractFor($a);
        }
    }
}
```
> Task pendamping (file lain, dibuat terpisah bila mau ketat 1-file):
> - `CareerController.php:93` → ganti `app(CvExtractionService::class)->extractFor($application);`
>   menjadi `ExtractCvJob::dispatch($application->id);`
> - `.env` → `QUEUE_CONNECTION=database` (+ `php artisan queue:table && migrate`) & jalankan worker.

## Cek per file
- [ ] `QUEUE_CONNECTION=sync` → job jalan inline (test lama tetap hijau).
- [ ] `QUEUE_CONNECTION=database` + `queue:work` → lamaran balas instan, `cv_text` terisi
      setelah worker memproses.

---

## Verifikasi
- [ ] `php -l app/Jobs/ExtractCvJob.php` bersih (kalau file PHP)
- [ ] `php -d memory_limit=2G -d xdebug.mode=off vendor/bin/phpunit --no-coverage` → tetap hijau (baseline)
- [ ] `node tests/browser/crud-suite.mjs` → tetap hijau (baseline 146)
- [ ] Verifikasi manual di browser sesuai bagian "Cek" di atas
- [ ] Flip `Status:` ke `[x] DONE` + isi commit SHA setelah semua centang
