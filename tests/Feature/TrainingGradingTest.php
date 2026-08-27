<?php

namespace Tests\Feature;

use App\Models\Training;
use App\Models\TrainingEnrollment;
use App\Models\TrainingQuestion;
use App\Models\User;
use App\Services\TrainingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * M11-1 — Training grading service: auto-score, pass/fail by KKM, attempt
 * limit → locked, certificate issuance, idempotent enroll.
 */
class TrainingGradingTest extends TestCase
{
    use RefreshDatabase;

    private TrainingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TrainingService::class);
    }

    private function user(string $name = 'Peserta'): User
    {
        return User::create([
            'name'     => $name,
            'email'    => str($name)->slug() . uniqid() . '@example.test',
            'password' => bcrypt('secret'),
        ]);
    }

    private function training(array $attrs = []): Training
    {
        return Training::create(array_merge([
            'title'         => 'Pelatihan K3',
            'passing_score' => 70,
            'max_attempts'  => 3,
            'status'        => Training::STATUS_PUBLISHED,
        ], $attrs));
    }

    /** Add N questions where correct answer is always 'a'. */
    private function seedQuestions(Training $t, int $n): array
    {
        $q = [];
        for ($i = 1; $i <= $n; $i++) {
            $q[] = TrainingQuestion::create([
                'training_id'    => $t->id,
                'position'       => $i,
                'question'       => "Soal $i?",
                'option_a'       => 'Benar',
                'option_b'       => 'Salah',
                'option_c'       => 'Salah',
                'option_d'       => 'Salah',
                'correct_option' => 'a',
            ]);
        }

        return $q;
    }

    private function enroll(Training $t, User $u): TrainingEnrollment
    {
        return TrainingEnrollment::create([
            'training_id' => $t->id,
            'user_id'     => $u->id,
            'status'      => TrainingEnrollment::STATUS_ENROLLED,
        ]);
    }

    public function test_all_correct_scores_100_and_passes(): void
    {
        $t = $this->training();
        $qs = $this->seedQuestions($t, 4);
        $e = $this->enroll($t, $this->user());

        $answers = collect($qs)->mapWithKeys(fn ($q) => [$q->id => 'a'])->all();
        $graded = $this->service->grade($e, $answers);

        $this->assertSame(100, $graded->score);
        $this->assertSame(TrainingEnrollment::STATUS_PASSED, $graded->status);
        $this->assertNotNull($graded->passed_at);
    }

    public function test_score_below_kkm_fails(): void
    {
        $t = $this->training(['passing_score' => 70]);
        $qs = $this->seedQuestions($t, 4);
        $e = $this->enroll($t, $this->user());

        // 2/4 correct = 50 < 70
        $answers = [$qs[0]->id => 'a', $qs[1]->id => 'a', $qs[2]->id => 'b', $qs[3]->id => 'b'];
        $graded = $this->service->grade($e, $answers);

        $this->assertSame(50, $graded->score);
        $this->assertSame(TrainingEnrollment::STATUS_FAILED, $graded->status);
        $this->assertNull($graded->passed_at);
    }

    public function test_exactly_at_kkm_passes(): void
    {
        $t = $this->training(['passing_score' => 75]);
        $qs = $this->seedQuestions($t, 4);
        $e = $this->enroll($t, $this->user());

        // 3/4 = 75 == KKM → pass
        $answers = [$qs[0]->id => 'a', $qs[1]->id => 'a', $qs[2]->id => 'a', $qs[3]->id => 'b'];
        $graded = $this->service->grade($e, $answers);

        $this->assertSame(75, $graded->score);
        $this->assertSame(TrainingEnrollment::STATUS_PASSED, $graded->status);
    }

    public function test_passing_issues_a_certificate(): void
    {
        $t = $this->training();
        $qs = $this->seedQuestions($t, 2);
        $e = $this->enroll($t, $this->user());

        $graded = $this->service->grade($e, [$qs[0]->id => 'a', $qs[1]->id => 'a']);

        $this->assertNotNull($graded->certificate_no);
        $this->assertStringStartsWith('CERT/', $graded->certificate_no);
        $this->assertNotNull($graded->certificate_issued_at);
    }

    public function test_attempts_increment_and_lock_after_max(): void
    {
        $t = $this->training(['max_attempts' => 3]);
        $qs = $this->seedQuestions($t, 2);
        $e = $this->enroll($t, $this->user());
        $wrong = [$qs[0]->id => 'b', $qs[1]->id => 'b']; // 0

        $this->service->grade($e, $wrong);
        $this->assertSame(TrainingEnrollment::STATUS_FAILED, $e->fresh()->status);
        $this->assertSame(1, $e->fresh()->attempts);

        $this->service->grade($e->fresh(), $wrong);
        $this->assertSame(2, $e->fresh()->attempts);
        $this->assertSame(TrainingEnrollment::STATUS_FAILED, $e->fresh()->status);

        $this->service->grade($e->fresh(), $wrong);
        $this->assertSame(3, $e->fresh()->attempts);
        $this->assertSame(TrainingEnrollment::STATUS_LOCKED, $e->fresh()->status, '3rd fail locks');
    }

    public function test_locked_enrollment_cannot_be_graded_again(): void
    {
        $t = $this->training(['max_attempts' => 1]);
        $qs = $this->seedQuestions($t, 2);
        $e = $this->enroll($t, $this->user());

        $this->service->grade($e, [$qs[0]->id => 'b', $qs[1]->id => 'b']); // fail → locked (max 1)
        $this->assertSame(TrainingEnrollment::STATUS_LOCKED, $e->fresh()->status);

        $this->expectException(\DomainException::class);
        $this->service->grade($e->fresh(), [$qs[0]->id => 'a', $qs[1]->id => 'a']);
    }

    public function test_reset_attempts_unlocks(): void
    {
        $t = $this->training(['max_attempts' => 1]);
        $qs = $this->seedQuestions($t, 2);
        $e = $this->enroll($t, $this->user());
        $this->service->grade($e, [$qs[0]->id => 'b', $qs[1]->id => 'b']);

        $reset = $this->service->resetAttempts($e->fresh());

        $this->assertSame(TrainingEnrollment::STATUS_ENROLLED, $reset->status);
        $this->assertSame(0, $reset->attempts);
        $this->assertNull($reset->score);
    }

    public function test_passed_enrollment_cannot_be_regraded(): void
    {
        $t = $this->training();
        $qs = $this->seedQuestions($t, 2);
        $e = $this->enroll($t, $this->user());
        $this->service->grade($e, [$qs[0]->id => 'a', $qs[1]->id => 'a']);

        $this->expectException(\DomainException::class);
        $this->service->grade($e->fresh(), [$qs[0]->id => 'a', $qs[1]->id => 'a']);
    }

    public function test_enroll_is_idempotent(): void
    {
        $t = $this->training();
        $u1 = $this->user('A');
        $u2 = $this->user('B');

        $first = $this->service->enroll($t, [$u1->id, $u2->id]);
        $second = $this->service->enroll($t, [$u1->id, $u2->id]); // same → no new

        $this->assertSame(2, $first);
        $this->assertSame(0, $second);
        $this->assertSame(2, TrainingEnrollment::where('training_id', $t->id)->count());
    }

    public function test_grading_without_questions_throws(): void
    {
        $t = $this->training();
        $e = $this->enroll($t, $this->user());

        $this->expectException(\DomainException::class);
        $this->service->grade($e, []);
    }

    public function test_archive_and_restore(): void
    {
        $t = $this->training();

        $this->service->archive($t);
        $this->assertSame(Training::STATUS_ARCHIVED, $t->fresh()->status);
        $this->assertNotNull($t->fresh()->archived_at);
        $this->assertSame(0, Training::active()->where('id', $t->id)->count());

        $this->service->restore($t->fresh());
        $this->assertSame(Training::STATUS_DRAFT, $t->fresh()->status);
        $this->assertNull($t->fresh()->archived_at);
    }

    public function test_youtube_embed_url_normalisation(): void
    {
        $t = $this->training();
        $m = \App\Models\TrainingMaterial::create([
            'training_id' => $t->id, 'position' => 1, 'title' => 'Video',
            'video_url' => 'https://youtu.be/abc123XYZ',
        ]);

        $this->assertSame('https://www.youtube.com/embed/abc123XYZ', $m->youtubeEmbedUrl());
    }
}
