<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\Candidate;
use App\Models\JobOpening;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * M17-1 — Recruitment 2.0 foundation: candidate accounts, careers portal,
 * apply-once guard, CV upload.
 */
class CareerPortalTest extends TestCase
{
    use RefreshDatabase;

    private function candidate(array $attrs = []): Candidate
    {
        return Candidate::create(array_merge([
            'name'     => 'Budi Pelamar',
            'email'    => 'budi' . uniqid() . '@example.test',
            'password' => 'password123',
        ], $attrs));
    }

    private function publishedOpening(array $attrs = []): JobOpening
    {
        return JobOpening::create(array_merge([
            'title'        => 'Backend Engineer',
            'vacancies'    => 1,
            'status'       => JobOpening::STATUS_OPEN,
            'is_published' => true,
            'published_at' => now(),
        ], $attrs));
    }

    // ── Auth ───────────────────────────────────────────────

    public function test_candidate_can_register(): void
    {
        $this->post(route('career.register.submit'), [
            'name'                  => 'Siti Kandidat',
            'email'                 => 'siti.kandidat@example.test',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('career.dashboard'));

        $this->assertDatabaseHas('candidates', ['email' => 'siti.kandidat@example.test']);
        $this->assertAuthenticatedAs(Candidate::first(), 'candidate');
    }

    public function test_candidate_password_is_hashed(): void
    {
        $c = $this->candidate();
        $this->assertNotSame('password123', $c->password);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('password123', $c->password));
    }

    public function test_candidate_can_login_and_logout(): void
    {
        $c = $this->candidate(['email' => 'login@example.test']);

        $this->post(route('career.login.submit'), [
            'email' => 'login@example.test', 'password' => 'password123',
        ])->assertRedirect();
        $this->assertAuthenticatedAs($c, 'candidate');

        $this->post(route('career.logout'))->assertRedirect(route('career.index'));
        $this->assertGuest('candidate');
    }

    public function test_wrong_password_is_rejected(): void
    {
        $this->candidate(['email' => 'x@example.test']);

        $this->from(route('career.login'))
            ->post(route('career.login.submit'), ['email' => 'x@example.test', 'password' => 'wrong'])
            ->assertRedirect(route('career.login'))
            ->assertSessionHasErrors('email');
        $this->assertGuest('candidate');
    }

    // ── Guard separation ───────────────────────────────────

    public function test_dashboard_requires_candidate_login(): void
    {
        $this->get(route('career.dashboard'))->assertRedirect(route('career.login'));
    }

    // ── Public listing ─────────────────────────────────────

    public function test_only_published_openings_are_public(): void
    {
        $pub = $this->publishedOpening(['title' => 'Public Role']);
        JobOpening::create([
            'title' => 'Secret Role', 'vacancies' => 1,
            'status' => JobOpening::STATUS_OPEN, 'is_published' => false,
        ]);

        $this->get(route('career.index'))
            ->assertOk()
            ->assertSee('Public Role')
            ->assertDontSee('Secret Role');
    }

    public function test_opening_gets_a_slug(): void
    {
        $o = $this->publishedOpening(['title' => 'Senior Laravel Dev']);
        $this->assertSame('senior-laravel-dev', $o->slug);

        $this->get(route('career.show', $o->slug))->assertOk()->assertSee('Senior Laravel Dev');
    }

    // ── Apply-once (R1) ────────────────────────────────────

    public function test_candidate_can_apply_with_cv(): void
    {
        Storage::fake('local');
        $c = $this->candidate();
        $o = $this->publishedOpening();

        $this->actingAs($c, 'candidate')
            ->post(route('career.apply', $o->slug), [
                'cv' => UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf'),
                'expected_salary' => 8000000,
                'cover_note' => 'Saya tertarik.',
            ])
            ->assertRedirect(route('career.dashboard'));

        $this->assertDatabaseHas('applicants', [
            'candidate_id' => $c->id, 'job_opening_id' => $o->id, 'stage' => 'applied',
        ]);
        $app = Applicant::first();
        $this->assertNotNull($app->cv_path);
        Storage::disk('local')->assertExists($app->cv_path);
    }

    public function test_candidate_cannot_apply_twice_to_same_opening(): void
    {
        Storage::fake('local');
        $c = $this->candidate();
        $o = $this->publishedOpening();

        Applicant::create([
            'job_opening_id' => $o->id, 'candidate_id' => $c->id,
            'name' => $c->name, 'stage' => 'applied',
        ]);

        $this->actingAs($c, 'candidate')
            ->from(route('career.show', $o->slug))
            ->post(route('career.apply', $o->slug), [
                'cv' => UploadedFile::fake()->create('cv2.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect(route('career.show', $o->slug))
            ->assertSessionHas('error');

        $this->assertSame(1, Applicant::where('candidate_id', $c->id)->where('job_opening_id', $o->id)->count());
    }

    public function test_candidate_can_apply_to_different_openings(): void
    {
        Storage::fake('local');
        $c = $this->candidate();
        $o1 = $this->publishedOpening(['title' => 'Role A']);
        $o2 = $this->publishedOpening(['title' => 'Role B']);

        foreach ([$o1, $o2] as $o) {
            $this->actingAs($c, 'candidate')->post(route('career.apply', $o->slug), [
                'cv' => UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf'),
            ])->assertRedirect(route('career.dashboard'));
        }

        $this->assertSame(2, Applicant::where('candidate_id', $c->id)->count());
    }

    public function test_apply_requires_candidate_auth(): void
    {
        $o = $this->publishedOpening();
        $this->post(route('career.apply', $o->slug), [])->assertRedirect(route('career.login'));
    }
}
