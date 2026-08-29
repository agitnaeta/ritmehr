<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\Candidate;
use App\Models\JobOpening;
use App\Services\CvExtractionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * M17-3 — CV text extraction (pymupdf) feeding search + AI matching.
 */
class CvExtractionTest extends TestCase
{
    use RefreshDatabase;

    private string $fixturePdf;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixturePdf = base_path('tests/browser/fixtures/dummy-cv.pdf');
    }

    private function candidate(): Candidate
    {
        return Candidate::create([
            'name' => 'Budi', 'email' => 'budi' . uniqid() . '@x.test', 'password' => 'password123',
        ]);
    }

    private function opening(): JobOpening
    {
        return JobOpening::create([
            'title' => 'Backend Engineer', 'vacancies' => 1,
            'status' => JobOpening::STATUS_OPEN, 'is_published' => true, 'published_at' => now(),
        ]);
    }

    public function test_extractor_reads_real_pdf_text(): void
    {
        if (! is_file($this->fixturePdf)) {
            $this->markTestSkipped('fixture PDF missing');
        }

        $text = app(CvExtractionService::class)->runExtractor($this->fixturePdf);

        $this->assertNotNull($text);
        $this->assertStringContainsString('Laravel', $text);
        $this->assertStringContainsString('Budi Santoso', $text);
    }

    public function test_extractor_returns_null_for_missing_file(): void
    {
        $text = app(CvExtractionService::class)->extractFromDisk('nope/missing.pdf');
        $this->assertNull($text);
    }

    public function test_extract_for_persists_cv_text(): void
    {
        // Put the real fixture onto the fake local disk.
        Storage::fake('local');
        $rel = 'applicant-cv/1/cv.pdf';
        Storage::disk('local')->put($rel, file_get_contents($this->fixturePdf));

        $c = $this->candidate();
        $o = $this->opening();
        $app = Applicant::create([
            'job_opening_id' => $o->id, 'candidate_id' => $c->id,
            'name' => $c->name, 'stage' => 'applied', 'cv_path' => $rel,
        ]);

        $text = app(CvExtractionService::class)->extractFor($app);

        $this->assertNotNull($text);
        $this->assertStringContainsString('Laravel', $app->fresh()->cv_text);
    }

    public function test_applying_extracts_cv_text_inline(): void
    {
        Storage::fake('local');
        $c = $this->candidate();
        $o = $this->opening();

        // Use the real fixture bytes so pymupdf has something to parse.
        $upload = new UploadedFile($this->fixturePdf, 'cv.pdf', 'application/pdf', null, true);

        $this->actingAs($c, 'candidate')
            ->post(route('career.apply', $o->slug), ['cv' => $upload])
            ->assertRedirect(route('career.dashboard'));

        $app = Applicant::where('candidate_id', $c->id)->first();
        $this->assertNotNull($app->cv_text);
        $this->assertStringContainsString('Laravel', $app->cv_text);
    }

    public function test_applying_dispatches_the_extraction_job(): void
    {
        \Illuminate\Support\Facades\Queue::fake();

        Storage::fake('local');
        $c = $this->candidate();
        $o = $this->opening();

        $upload = new UploadedFile($this->fixturePdf, 'cv.pdf', 'application/pdf', null, true);

        $this->actingAs($c, 'candidate')
            ->post(route('career.apply', $o->slug), ['cv' => $upload])
            ->assertRedirect(route('career.dashboard'));

        // ST-01/PERF-1: ekstraksi CV kini di-queue (tak blok request lamaran).
        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\ExtractCvJob::class);
    }

    public function test_backfill_command_extracts_pending(): void
    {
        Storage::fake('local');
        $rel = 'applicant-cv/1/cv.pdf';
        Storage::disk('local')->put($rel, file_get_contents($this->fixturePdf));

        $c = $this->candidate();
        $o = $this->opening();
        Applicant::create([
            'job_opening_id' => $o->id, 'candidate_id' => $c->id,
            'name' => $c->name, 'stage' => 'applied', 'cv_path' => $rel, 'cv_text' => null,
        ]);

        $this->artisan('recruitment:extract-cv')->assertExitCode(0);

        $this->assertStringContainsString('Laravel', Applicant::first()->fresh()->cv_text);
    }
}
