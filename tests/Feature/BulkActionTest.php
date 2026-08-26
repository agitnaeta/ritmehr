<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\Candidate;
use App\Models\JobOpening;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * M18-5 — Bulk actions (reject / move stage) on selected applicants.
 */
class BulkActionTest extends TestCase
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

    private function opening(): JobOpening
    {
        return JobOpening::create([
            'title' => 'Engineer', 'vacancies' => 5, 'status' => 'open',
            'is_published' => true, 'published_at' => now(),
        ]);
    }

    private function applicant(JobOpening $o, array $attrs = []): Applicant
    {
        $c = Candidate::create([
            'name' => 'C' . uniqid(), 'email' => 'c' . uniqid() . '@x.test', 'password' => 'password123',
        ]);
        return Applicant::create(array_merge([
            'job_opening_id' => $o->id, 'candidate_id' => $c->id,
            'name' => $c->name, 'stage' => Applicant::STAGE_APPLIED,
        ], $attrs));
    }

    public function test_bulk_reject_rejects_all_selected(): void
    {
        Storage::fake('local');
        $o = $this->opening();
        $a1 = $this->applicant($o);
        $a2 = $this->applicant($o);
        $a3 = $this->applicant($o);

        $this->actingAs($this->editor(), config('backpack.base.guard'))
            ->postJson(backpack_url('recruitment/bulk'), [
                'ids' => [$a1->id, $a2->id], 'action' => 'reject',
            ])
            ->assertOk()
            ->assertJsonPath('count', 2);

        $this->assertSame('rejected', $a1->fresh()->stage);
        $this->assertSame('rejected', $a2->fresh()->stage);
        $this->assertSame('applied', $a3->fresh()->stage); // untouched
    }

    public function test_bulk_move_changes_stage(): void
    {
        $o = $this->opening();
        $a1 = $this->applicant($o);
        $a2 = $this->applicant($o);

        $this->actingAs($this->editor(), config('backpack.base.guard'))
            ->postJson(backpack_url('recruitment/bulk'), [
                'ids' => [$a1->id, $a2->id], 'action' => 'move', 'stage' => 'screening',
            ])
            ->assertOk()
            ->assertJsonPath('count', 2);

        $this->assertSame('screening', $a1->fresh()->stage);
        $this->assertSame('screening', $a2->fresh()->stage);
    }

    public function test_bulk_never_touches_hired(): void
    {
        $o = $this->opening();
        $hired = $this->applicant($o, ['stage' => Applicant::STAGE_HIRED, 'hired_user_id' => $this->editor()->id]);

        $this->actingAs($this->editor(), config('backpack.base.guard'))
            ->postJson(backpack_url('recruitment/bulk'), [
                'ids' => [$hired->id], 'action' => 'reject',
            ])
            ->assertOk()
            ->assertJsonPath('count', 0);

        $this->assertSame('hired', $hired->fresh()->stage);
    }

    public function test_bulk_requires_edit_permission(): void
    {
        $guard = config('backpack.base.guard', 'backpack');
        $viewer = User::factory()->create();
        $viewer->givePermissionTo(Permission::firstOrCreate(['name' => 'recruitment.view', 'guard_name' => $guard]));
        $o = $this->opening();
        $a = $this->applicant($o);

        $this->actingAs($viewer, config('backpack.base.guard'))
            ->postJson(backpack_url('recruitment/bulk'), [
                'ids' => [$a->id], 'action' => 'reject',
            ])
            ->assertForbidden();

        $this->assertSame('applied', $a->fresh()->stage);
    }

    public function test_bulk_validates_ids_required(): void
    {
        $this->actingAs($this->editor(), config('backpack.base.guard'))
            ->postJson(backpack_url('recruitment/bulk'), ['action' => 'reject', 'ids' => []])
            ->assertStatus(422);
    }
}
