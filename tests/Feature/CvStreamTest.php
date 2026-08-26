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
 * M18-1 — Authorized inline CV streaming so HR can read a CV without downloading.
 */
class CvStreamTest extends TestCase
{
    use RefreshDatabase;

    private function seedApplicantWithCv(): Applicant
    {
        Storage::fake('local');
        $c = Candidate::create([
            'name' => 'Budi', 'email' => 'budi' . uniqid() . '@x.test', 'password' => 'password123',
        ]);
        $o = JobOpening::create([
            'title' => 'Engineer', 'vacancies' => 1, 'status' => 'open',
            'is_published' => true, 'published_at' => now(),
        ]);
        $rel = 'applicant-cv/' . $c->id . '/cv.pdf';
        Storage::disk('local')->put($rel, '%PDF-1.4 fake');
        return Applicant::create([
            'job_opening_id' => $o->id, 'candidate_id' => $c->id,
            'name' => $c->name, 'stage' => 'applied', 'cv_path' => $rel,
        ]);
    }

    /** A user with direct recruitment.view permission and NO role (treated as admin). */
    private function viewer(): User
    {
        $guard = config('backpack.base.guard', 'backpack');
        $perm = Permission::firstOrCreate(['name' => 'recruitment.view', 'guard_name' => $guard]);
        $u = User::factory()->create();
        $u->givePermissionTo($perm);
        return $u;
    }

    public function test_cv_stream_requires_authentication(): void
    {
        $app = $this->seedApplicantWithCv();
        $this->get(backpack_url("recruitment/applicant/{$app->id}/cv"))
            ->assertRedirect();
    }

    public function test_authorized_user_streams_cv_inline(): void
    {
        $app = $this->seedApplicantWithCv();

        $res = $this->actingAs($this->viewer(), config('backpack.base.guard'))
            ->get(backpack_url("recruitment/applicant/{$app->id}/cv"));

        $res->assertOk();
        $res->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('inline', $res->headers->get('content-disposition'));
    }

    public function test_stream_404_when_cv_missing(): void
    {
        $app = $this->seedApplicantWithCv();
        $app->update(['cv_path' => null]);

        $this->actingAs($this->viewer(), config('backpack.base.guard'))
            ->get(backpack_url("recruitment/applicant/{$app->id}/cv"))
            ->assertNotFound();
    }
}
