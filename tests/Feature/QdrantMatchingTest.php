<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\Candidate;
use App\Models\JobOpening;
use App\Services\Matching\EmbeddingManager;
use App\Services\Matching\MatchingService;
use App\Services\Matching\QdrantService;
use Tests\TestCase;

/**
 * M17-4 — Qdrant shortlist pipeline.
 *
 * These tests run against the LIVE local Qdrant container (fulfilling Capt's
 * "test API Qdrant langsung" requirement). They use a deterministic fake
 * embedding provider so we exercise the real vector store + scoring logic
 * without depending on an external embedding endpoint.
 *
 * If Qdrant is not reachable the tests skip (so CI without the container is green).
 *
 * NOTE: does NOT use RefreshDatabase — it talks to the real DB + Qdrant. It
 * cleans up after itself.
 */
class QdrantMatchingTest extends TestCase
{
    private QdrantService $qdrant;
    private array $cleanupApplicants = [];
    private array $cleanupOpenings = [];
    private array $cleanupCandidates = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->qdrant = app(QdrantService::class);

        if (! $this->qdrant->isUp()) {
            $this->markTestSkipped('Qdrant not reachable at ' . $this->qdrant->baseUrl());
        }

        // Bind a deterministic fake embedding: maps keywords → a small vector.
        $this->app->bind(EmbeddingManager::class, fn () => new class extends EmbeddingManager {
            public function provider(): string { return 'faketest'; }
            public function model(): string { return 'fake-dim8'; }
            public function embed(string $text): ?array
            {
                $text = strtolower($text);
                // 8-dim vector: each slot lights up for a keyword.
                $keys = ['laravel', 'react', 'python', 'devops', 'design', 'sales', 'finance', 'manager'];
                $v = [];
                foreach ($keys as $k) {
                    $v[] = substr_count($text, $k) > 0 ? 1.0 : 0.05;
                }
                return $v;
            }
        });
    }

    protected function tearDown(): void
    {
        // Clean DB rows + Qdrant collection created during the test.
        Applicant::whereIn('id', $this->cleanupApplicants)->delete();
        JobOpening::whereIn('id', $this->cleanupOpenings)->delete();
        Candidate::whereIn('id', $this->cleanupCandidates)->delete();

        parent::tearDown();
    }

    private function matcher(): MatchingService
    {
        return app(MatchingService::class);
    }

    private function makeCandidate(string $name): Candidate
    {
        $c = Candidate::create([
            'name' => $name, 'email' => strtolower($name) . uniqid() . '@x.test', 'password' => 'password123',
        ]);
        $this->cleanupCandidates[] = $c->id;
        return $c;
    }

    private function makeOpening(string $title, string $skills): JobOpening
    {
        $o = JobOpening::create([
            'title' => $title, 'vacancies' => 1, 'status' => 'open',
            'is_published' => true, 'published_at' => now(),
            'description' => $skills, 'required_skills' => explode(',', $skills),
        ]);
        $this->cleanupOpenings[] = $o->id;
        return $o;
    }

    private function makeApplication(Candidate $c, JobOpening $o, string $cvText): Applicant
    {
        $a = Applicant::create([
            'job_opening_id' => $o->id, 'candidate_id' => $c->id,
            'name' => $c->name, 'stage' => 'applied', 'cv_text' => $cvText,
        ]);
        $this->cleanupApplicants[] = $a->id;
        return $a;
    }

    public function test_qdrant_is_reachable(): void
    {
        $this->assertTrue($this->qdrant->isUp());
    }

    public function test_ingest_then_shortlist_scores_relevant_higher(): void
    {
        $matcher = $this->matcher();
        $opening = $this->makeOpening('Laravel Engineer', 'laravel,php,mysql');

        // Three applicants: strong (laravel), weak (design), medium (laravel+react).
        $strong = $this->makeApplication($this->makeCandidate('Strong'), $opening, 'Expert Laravel developer, 6 years PHP MySQL');
        $weak   = $this->makeApplication($this->makeCandidate('Weak'), $opening, 'Graphic design and sales background');
        $medium = $this->makeApplication($this->makeCandidate('Medium'), $opening, 'Laravel and React fullstack');

        // Ingest all into Qdrant.
        $this->assertTrue($matcher->ingest($strong));
        $this->assertTrue($matcher->ingest($weak));
        $this->assertTrue($matcher->ingest($medium));

        // Shortlist.
        $scored = $matcher->shortlist($opening);
        $this->assertGreaterThanOrEqual(3, $scored);

        $strong->refresh(); $weak->refresh(); $medium->refresh();

        // All got a score.
        $this->assertNotNull($strong->vector_score);
        $this->assertNotNull($weak->vector_score);

        // The Laravel CV must outrank the design CV.
        $this->assertGreaterThan(
            $weak->vector_score,
            $strong->vector_score,
            "Laravel CV ({$strong->vector_score}) should outrank design CV ({$weak->vector_score})"
        );

        // Opening marked as synced.
        $this->assertNotNull($opening->fresh()->vector_synced_at);
    }

    public function test_shortlist_filters_by_opening(): void
    {
        $matcher = $this->matcher();
        $o1 = $this->makeOpening('Laravel Role', 'laravel');
        $o2 = $this->makeOpening('Python Role', 'python');

        $a1 = $this->makeApplication($this->makeCandidate('L'), $o1, 'laravel expert');
        $a2 = $this->makeApplication($this->makeCandidate('P'), $o2, 'python expert');

        $matcher->ingest($a1);
        $matcher->ingest($a2);

        $matcher->shortlist($o1);

        // a1 (in o1) scored; a2 (in o2) must remain unscored by o1's shortlist.
        $this->assertNotNull($a1->fresh()->vector_score);
        $this->assertNull($a2->fresh()->vector_score);
    }

    public function test_forget_removes_vector(): void
    {
        $matcher = $this->matcher();
        $o = $this->makeOpening('Temp Role', 'devops');
        $a = $this->makeApplication($this->makeCandidate('Temp'), $o, 'devops kubernetes');

        $this->assertTrue($matcher->ingest($a));
        $matcher->forget($a); // should not throw

        // After forget, a fresh shortlist should not score it.
        $matcher->shortlist($o);
        $this->assertNull($a->fresh()->vector_score);
    }
}
