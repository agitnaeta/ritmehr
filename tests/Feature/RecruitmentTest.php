<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\Department;
use App\Models\Interview;
use App\Models\JobOpening;
use App\Models\Position;
use App\Models\User;
use App\Services\RecruitmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * M09 — Recruitment pipeline: service logic + route guards + hire→user flow.
 */
class RecruitmentTest extends TestCase
{
    use RefreshDatabase;

    private RecruitmentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(RecruitmentService::class);
    }

    private function user(string $name, array $attrs = []): User
    {
        return User::create(array_merge([
            'name'     => $name,
            'email'    => str($name)->slug() . uniqid() . '@example.test',
            'password' => bcrypt('secret'),
        ], $attrs));
    }

    private function opening(array $attrs = []): JobOpening
    {
        return JobOpening::create(array_merge([
            'title'     => 'Backend Engineer',
            'vacancies' => 2,
            'status'    => JobOpening::STATUS_OPEN,
        ], $attrs));
    }

    private function applicant(JobOpening $o, array $attrs = []): Applicant
    {
        return Applicant::create(array_merge([
            'job_opening_id' => $o->id,
            'name'           => 'Pelamar ' . uniqid(),
            'email'          => 'pelamar' . uniqid() . '@example.test',
            'stage'          => Applicant::STAGE_APPLIED,
        ], $attrs));
    }

    /** A role-less user with direct permissions (CheckIfAdmin lets role-less through). */
    private function userWith(array $permissions): User
    {
        $guard = config('backpack.base.guard', 'backpack');
        $perms = collect($permissions)->map(fn ($p) =>
            Permission::firstOrCreate(['name' => $p, 'guard_name' => $guard]));

        $user = $this->user('Recruiter ' . uniqid());
        $user->givePermissionTo($perms);

        return $user;
    }

    // ── Service: hire ──────────────────────────────────────

    public function test_hiring_creates_a_user_and_links_it_back(): void
    {
        $dept = Department::create(['name' => 'IT', 'code' => 'IT']);
        $pos = Position::create(['name' => 'Engineer']);
        $opening = $this->opening(['department_id' => $dept->id, 'position_id' => $pos->id]);
        $applicant = $this->applicant($opening, ['stage' => Applicant::STAGE_OFFER, 'name' => 'Sari']);

        $user = $this->service->hire($applicant);

        $this->assertNotNull($user->id);
        $this->assertSame('Sari', $user->name);
        $this->assertSame($dept->id, $user->department_id, 'inherits the opening department');
        $this->assertSame($pos->id, $user->position_id);
        $this->assertSame(User::STATUS_PROBATION, $user->employment_status);

        $applicant->refresh();
        $this->assertSame(Applicant::STAGE_HIRED, $applicant->stage);
        $this->assertSame($user->id, $applicant->hired_user_id);
        $this->assertNotNull($applicant->hired_at);
    }

    public function test_hiring_is_idempotent(): void
    {
        $applicant = $this->applicant($this->opening(), ['stage' => Applicant::STAGE_OFFER]);

        $first = $this->service->hire($applicant);
        $second = $this->service->hire($applicant->fresh());

        $this->assertSame($first->id, $second->id, 'the same user is returned, not a duplicate');
        $this->assertSame(1, User::where('name', $applicant->name)->count());
    }

    public function test_applicant_without_email_gets_a_placeholder_login(): void
    {
        $applicant = $this->applicant($this->opening(), [
            'stage' => Applicant::STAGE_OFFER, 'email' => null,
        ]);

        $user = $this->service->hire($applicant);

        $this->assertNotEmpty($user->email);
        $this->assertStringContainsString('@recruit.local', $user->email);
    }

    // ── Service: stage moves ───────────────────────────────

    public function test_move_stage_advances_the_applicant(): void
    {
        $applicant = $this->applicant($this->opening());

        $this->service->moveStage($applicant, Applicant::STAGE_SCREENING);

        $this->assertSame(Applicant::STAGE_SCREENING, $applicant->fresh()->stage);
    }

    public function test_moving_to_hired_provisions_a_user(): void
    {
        $applicant = $this->applicant($this->opening(), ['stage' => Applicant::STAGE_OFFER]);

        $this->service->moveStage($applicant, Applicant::STAGE_HIRED);

        $this->assertNotNull($applicant->fresh()->hired_user_id);
    }

    public function test_a_hired_applicant_cannot_be_moved_back(): void
    {
        $applicant = $this->applicant($this->opening(), ['stage' => Applicant::STAGE_OFFER]);
        $this->service->hire($applicant);

        $this->expectException(\DomainException::class);
        $this->service->moveStage($applicant->fresh(), Applicant::STAGE_SCREENING);
    }

    public function test_unknown_stage_is_rejected(): void
    {
        $applicant = $this->applicant($this->opening());

        $this->expectException(\InvalidArgumentException::class);
        $this->service->moveStage($applicant, 'nonsense');
    }

    // ── Model helpers ──────────────────────────────────────

    public function test_remaining_vacancies_accounts_for_hires(): void
    {
        $opening = $this->opening(['vacancies' => 2]);
        $a1 = $this->applicant($opening, ['stage' => Applicant::STAGE_OFFER]);
        $this->service->hire($a1);

        $this->assertSame(1, $opening->fresh()->remainingVacancies());
        $this->assertSame(1, $opening->fresh()->hiredCount());
    }

    // ── Route guards ───────────────────────────────────────

    public function test_pipeline_requires_recruitment_view(): void
    {
        $blocked = $this->userWith(['presence.view']);

        $this->actingAs($blocked, config('backpack.base.guard'))
            ->get(backpack_url('recruitment/pipeline'))
            ->assertStatus(403);
    }

    public function test_pipeline_opens_with_recruitment_view(): void
    {
        $allowed = $this->userWith(['recruitment.view']);
        $opening = $this->opening();
        $this->applicant($opening, ['stage' => Applicant::STAGE_APPLIED]);

        $this->actingAs($allowed, config('backpack.base.guard'))
            ->get(backpack_url('recruitment/pipeline'))
            ->assertOk()
            ->assertSee('Papan Pipeline');
    }

    public function test_hire_endpoint_requires_recruitment_edit(): void
    {
        $viewer = $this->userWith(['recruitment.view']); // view but not edit
        $applicant = $this->applicant($this->opening(), ['stage' => Applicant::STAGE_OFFER]);

        $this->actingAs($viewer, config('backpack.base.guard'))
            ->post(backpack_url('recruitment/applicant/' . $applicant->id . '/hire'))
            ->assertStatus(403);

        $this->assertNull($applicant->fresh()->hired_user_id);
    }

    public function test_hire_endpoint_provisions_user_with_edit_permission(): void
    {
        $editor = $this->userWith(['recruitment.view', 'recruitment.edit']);
        $applicant = $this->applicant($this->opening(), ['stage' => Applicant::STAGE_OFFER]);

        $this->actingAs($editor, config('backpack.base.guard'))
            ->post(backpack_url('recruitment/applicant/' . $applicant->id . '/hire'))
            ->assertRedirect();

        $this->assertNotNull($applicant->fresh()->hired_user_id);
    }

    public function test_move_stage_endpoint_updates_applicant(): void
    {
        $editor = $this->userWith(['recruitment.view', 'recruitment.edit']);
        $applicant = $this->applicant($this->opening());

        $this->actingAs($editor, config('backpack.base.guard'))
            ->postJson(backpack_url('recruitment/applicant/' . $applicant->id . '/stage'), [
                'stage' => Applicant::STAGE_INTERVIEW,
            ])
            ->assertOk()
            ->assertJson(['ok' => true, 'stage' => Applicant::STAGE_INTERVIEW]);
    }

    public function test_interview_calendar_renders(): void
    {
        $allowed = $this->userWith(['recruitment.view']);
        $applicant = $this->applicant($this->opening());
        Interview::create([
            'applicant_id' => $applicant->id,
            'scheduled_at' => now()->setTime(10, 0),
            'mode'         => Interview::MODE_ONLINE,
            'status'       => Interview::STATUS_SCHEDULED,
        ]);

        $this->actingAs($allowed, config('backpack.base.guard'))
            ->get(backpack_url('recruitment/calendar'))
            ->assertOk()
            ->assertSee('Jadwal Wawancara');
    }
}
