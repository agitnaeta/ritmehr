# ST-01 — `app/Jobs/ExtractCvJob.php`

**Fokus:** PERF-1 — pindahkan ekstraksi CV ke queue (file BARU)
**Severity:** 🟠 Tinggi
**Status:** [x] DONE — commit: `pending` (terverifikasi 2026-08-29)
**File utama:** `app/Jobs/ExtractCvJob.php` (baru)
**File pendamping:** `app/Http/Controllers/Career/CareerController.php` (dispatch), `tests/Feature/CvExtractionTest.php` (+1 test Queue::assertPushed)

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
- [x] `php -l app/Jobs/ExtractCvJob.php` + CareerController + test → bersih
- [x] Sync (test): job inline → CvExtractionTest **6/6** (termasuk inline `cv_text` terisi)
- [x] Async: `Queue::assertPushed(ExtractCvJob)` → job masuk antrian (tak blok request)
- [x] Full PHPUnit → 425 lulus / 2 baseline (naik 1 dari test dispatch baru)
- [x] `crud-suite.mjs` → **146 PASS**
- [x] Flip `Status:` ke `[x] DONE`

## PROOF (2026-08-29)

### 1. Sync (QUEUE_CONNECTION=sync, default & test) — perilaku lama dipertahankan
```
phpunit tests/Feature/CvExtractionTest.php -> OK (6 tests)
- test_applying_extracts_cv_text_inline: cv_text terisi seketika (job inline)
```

### 2. Async — job ter-dispatch, request tak blok
```
Queue::fake(); ExtractCvJob::dispatch($id);
Queue::assertPushed(ExtractCvJob::class, 1) -> PASS
job: timeout=60s, tries=2, implements ShouldQueue = YA
```

### 3. Test baru
`test_applying_dispatches_the_extraction_job` — Queue::fake + apply → assertPushed.
Mengunci perilaku queue supaya tak regres.

### Cara aktifkan async di PRODUKSI (belum diubah di .env — keputusan deploy)
```
QUEUE_CONNECTION=database
php artisan queue:table && php artisan migrate
php artisan queue:work   # (supervisor/systemd)
```

### Regresi
- crud-suite: **146/146**. PHPUnit: 425 lulus / 2 baseline time-dependent.
