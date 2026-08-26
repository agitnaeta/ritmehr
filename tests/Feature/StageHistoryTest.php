<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\ApplicantStageLog;
use App\Models\Candidate;
use App\Models\JobOpening;
use App\Models\User;
use App\Services\RecruitmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * M18-2 — Stage history: every pipeline transition is logged for the timeline.
 */
class StageHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function service(): RecruitmentService
    {
        return app(RecruitmentService::class);
    }

    private function applicant(array $attrs = []): Applicant
    {
        $c = Candidate::create([
            'name' => 'Budi', 'email' => 'b' . uniqid() . '@x.test', 'password' => 'password123',
        ]);
        $o = JobOpening::create([
            'title' => 'Engineer', 'vacancies' => 1, 'status' => 'open',
            'is_published' => true, 'published_at' => now(),
        ]);
        return Applicant::create(array_merge([
            'job_opening_id' => $o->id, 'candidate_id' => $c->id,
            'name' => $c->name, 'stage' => Applicant::STAGE_APPLIED,
        ], $attrs));
    }

    public function test_move_stage_writes_a_log(): void
    {
        $a = $this->applicant();
        $this->service()->moveStage($a, Applicant::STAGE_SCREENING);

        $this->assertDatabaseHas('applicant_stage_logs', [
            'applicant_id' => $a->id,
            'from_stage'   => 'applied',
            'to_stage'     => 'screening',
        ]);
    }

    public function test_no_op_transition_is_not_logged(): void
    {
        $a = $this->applicant(['stage' => Applicant::STAGE_SCREENING]);
        $this->service()->moveStage($a, Applicant::STAGE_SCREENING);

        $this->assertSame(0, ApplicantStageLog::where('applicant_id', $a->id)->count());
    }

    public function test_hire_logs_transition_to_hired(): void
    {
        $a = $this->applicant(['stage' => Applicant::STAGE_OFFER]);
        $this->service()->hire($a);

        $this->assertDatabaseHas('applicant_stage_logs', [
            'applicant_id' => $a->id,
            'from_stage'   => 'offer',
            'to_stage'     => 'hired',
        ]);
    }

    public function test_reject_logs_transition(): void
    {
        Storage::fake('local');
        $a = $this->applicant(['stage' => Applicant::STAGE_INTERVIEW]);
        $this->service()->reject($a);

        $this->assertDatabaseHas('applicant_stage_logs', [
            'applicant_id' => $a->id,
            'from_stage'   => 'interview',
            'to_stage'     => 'rejected',
        ]);
    }

    public function test_log_records_actor_when_authenticated(): void
    {
        $actor = User::factory()->create();
        $this->actingAs($actor, config('backpack.base.guard'));

        $a = $this->applicant();
        $this->service()->moveStage($a, Applicant::STAGE_SCREENING);

        $log = ApplicantStageLog::where('applicant_id', $a->id)->first();
        $this->assertSame($actor->id, $log->actor_id);
    }

    public function test_stage_logs_relation_returns_newest_first(): void
    {
        $a = $this->applicant();
        $this->service()->moveStage($a, Applicant::STAGE_SCREENING);
        $this->service()->moveStage($a->fresh(), Applicant::STAGE_INTERVIEW);

        $logs = $a->fresh()->stageLogs;
        $this->assertSame('interview', $logs->first()->to_stage);
        $this->assertSame(2, $logs->count());
    }
}
