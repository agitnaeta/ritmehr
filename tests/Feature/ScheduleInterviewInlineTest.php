<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\Candidate;
use App\Models\Interview;
use App\Models\JobOpening;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * M18-4 — Schedule an interview inline from the drawer (no dropdown re-pick).
 */
class ScheduleInterviewInlineTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        $guard = config('backpack.base.guard', 'backpack');
        $u = User::factory()->create();
        foreach (['recruitment.view', 'recruitment.edit'] as $p) {
            $u->givePermissionTo(Permission::firstOrCreate(['name' => $p, 'guard_name' => $guard]));
        }
        return $u;
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
            'name' => $c->name, 'stage' => Applicant::STAGE_SCREENING,
        ], $attrs));
    }

    public function test_inline_schedule_creates_interview_linked_to_applicant(): void
    {
        $a = $this->applicant();

        $this->actingAs($this->editor(), config('backpack.base.guard'))
            ->postJson(backpack_url("recruitment/applicant/{$a->id}/interview"), [
                'scheduled_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
                'mode'         => 'online',
                'location'     => 'https://meet.example/x',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseHas('interviews', [
            'applicant_id' => $a->id,
            'mode'         => 'online',
            'status'       => 'scheduled',
        ]);
    }

    public function test_advance_stage_moves_applicant_to_interview(): void
    {
        $a = $this->applicant(['stage' => Applicant::STAGE_SCREENING]);

        $this->actingAs($this->editor(), config('backpack.base.guard'))
            ->postJson(backpack_url("recruitment/applicant/{$a->id}/interview"), [
                'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
                'mode'         => 'onsite',
                'advance_stage' => true,
            ])
            ->assertOk();

        $this->assertSame(Applicant::STAGE_INTERVIEW, $a->fresh()->stage);
    }

    public function test_schedule_requires_edit_permission(): void
    {
        $guard = config('backpack.base.guard', 'backpack');
        $viewer = User::factory()->create();
        $viewer->givePermissionTo(Permission::firstOrCreate(['name' => 'recruitment.view', 'guard_name' => $guard]));
        $a = $this->applicant();

        $this->actingAs($viewer, config('backpack.base.guard'))
            ->postJson(backpack_url("recruitment/applicant/{$a->id}/interview"), [
                'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
                'mode'         => 'onsite',
            ])
            ->assertForbidden();

        $this->assertSame(0, Interview::count());
    }

    public function test_validation_rejects_missing_datetime(): void
    {
        $a = $this->applicant();

        $this->actingAs($this->editor(), config('backpack.base.guard'))
            ->postJson(backpack_url("recruitment/applicant/{$a->id}/interview"), [
                'mode' => 'onsite',
            ])
            ->assertStatus(422);
    }
}
