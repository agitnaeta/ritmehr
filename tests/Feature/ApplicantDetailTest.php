<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\Candidate;
use App\Models\Interview;
use App\Models\JobOpening;
use App\Models\User;
use App\Services\RecruitmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * M18-3 — Applicant detail JSON endpoint powering the pipeline drawer.
 */
class ApplicantDetailTest extends TestCase
{
    use RefreshDatabase;

    private function viewer(): User
    {
        $guard = config('backpack.base.guard', 'backpack');
        $perm = Permission::firstOrCreate(['name' => 'recruitment.view', 'guard_name' => $guard]);
        $u = User::factory()->create();
        $u->givePermissionTo($perm);
        return $u;
    }

    private function applicant(array $attrs = []): Applicant
    {
        $c = Candidate::create([
            'name' => 'Budi Santoso', 'email' => 'b' . uniqid() . '@x.test', 'password' => 'password123',
        ]);
        $o = JobOpening::create([
            'title' => 'Backend Engineer', 'vacancies' => 1, 'status' => 'open',
            'is_published' => true, 'published_at' => now(),
        ]);
        return Applicant::create(array_merge([
            'job_opening_id' => $o->id, 'candidate_id' => $c->id,
            'name' => $c->name, 'stage' => Applicant::STAGE_APPLIED,
        ], $attrs));
    }

    public function test_detail_requires_view_permission(): void
    {
        $a = $this->applicant();
        $blocked = User::factory()->create();

        $this->actingAs($blocked, config('backpack.base.guard'))
            ->getJson(backpack_url("recruitment/applicant/{$a->id}/detail"))
            ->assertForbidden();
    }

    public function test_detail_returns_profile_and_flags(): void
    {
        $a = $this->applicant([
            'ai_score' => 87,
            'ai_model' => 'gpt-test',
            'ai_reasoning' => ['summary' => 'Kuat', 'criteria' => [
                ['name' => 'Laravel', 'score' => 90, 'reason' => 'ok', 'evidence' => '5th'],
            ]],
            'cv_path' => 'applicant-cv/1/cv.pdf',
        ]);

        $res = $this->actingAs($this->viewer(), config('backpack.base.guard'))
            ->getJson(backpack_url("recruitment/applicant/{$a->id}/detail"));

        $res->assertOk()
            ->assertJsonPath('name', 'Budi Santoso')
            ->assertJsonPath('ai_score', 87)
            ->assertJsonPath('has_cv', true)
            ->assertJsonPath('ai_reasoning.summary', 'Kuat')
            ->assertJsonPath('ai_reasoning.criteria.0.name', 'Laravel');

        $this->assertStringContainsString("/cv", $res->json('cv_url'));
    }

    public function test_detail_includes_interviews_and_timeline(): void
    {
        $a = $this->applicant();
        // Create a stage transition (writes a log).
        app(RecruitmentService::class)->moveStage($a, Applicant::STAGE_SCREENING);

        Interview::create([
            'applicant_id' => $a->id,
            'scheduled_at' => now()->addDay(),
            'mode' => Interview::MODE_ONLINE,
            'status' => Interview::STATUS_SCHEDULED,
        ]);

        $res = $this->actingAs($this->viewer(), config('backpack.base.guard'))
            ->getJson(backpack_url("recruitment/applicant/{$a->id}/detail"));

        $res->assertOk()
            ->assertJsonCount(1, 'interviews')
            ->assertJsonCount(1, 'timeline')
            ->assertJsonPath('timeline.0.to', 'Seleksi Berkas');
    }

    public function test_detail_cv_url_null_when_no_cv(): void
    {
        $a = $this->applicant(['cv_path' => null]);

        $this->actingAs($this->viewer(), config('backpack.base.guard'))
            ->getJson(backpack_url("recruitment/applicant/{$a->id}/detail"))
            ->assertOk()
            ->assertJsonPath('has_cv', false)
            ->assertJsonPath('cv_url', null);
    }
}
