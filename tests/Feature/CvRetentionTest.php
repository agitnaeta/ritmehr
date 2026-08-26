<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\Candidate;
use App\Models\JobOpening;
use App\Models\User;
use App\Services\RecruitmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * M17-5 — CV retention: reject deletes the CV permanently (policy), the
 * candidate account survives, and the daily purge cleans up stragglers past
 * the retention window.
 */
class CvRetentionTest extends TestCase
{
    use RefreshDatabase;

    private function scenario(): array
    {
        $c = Candidate::create([
            'name' => 'Budi', 'email' => 'budi' . uniqid() . '@x.test', 'password' => 'password123',
        ]);
        $o = JobOpening::create([
            'title' => 'Engineer', 'vacancies' => 1, 'status' => 'open',
            'is_published' => true, 'published_at' => now(),
        ]);
        $rel = 'applicant-cv/' . $c->id . '/cv.pdf';
        Storage::disk('local')->put($rel, 'PDF BYTES');
        $a = Applicant::create([
            'job_opening_id' => $o->id, 'candidate_id' => $c->id,
            'name' => $c->name, 'stage' => 'applied', 'cv_path' => $rel, 'cv_text' => 'laravel',
        ]);

        return [$c, $o, $a, $rel];
    }

    public function test_reject_deletes_cv_permanently(): void
    {
        Storage::fake('local');
        [$c, $o, $a, $rel] = $this->scenario();
        Storage::disk('local')->assertExists($rel);

        app(RecruitmentService::class)->reject($a);

        $a->refresh();
        $this->assertSame(Applicant::STAGE_REJECTED, $a->stage);
        $this->assertNotNull($a->rejected_at);
        $this->assertNotNull($a->cv_purged_at);
        $this->assertNull($a->cv_path);
        Storage::disk('local')->assertMissing($rel);
    }

    public function test_reject_keeps_candidate_account(): void
    {
        Storage::fake('local');
        [$c, $o, $a] = $this->scenario();

        app(RecruitmentService::class)->reject($a);

        // Candidate account + application row survive (audit + anti re-apply).
        $this->assertDatabaseHas('candidates', ['id' => $c->id]);
        $this->assertDatabaseHas('applicants', ['id' => $a->id, 'stage' => 'rejected']);
    }

    public function test_rejected_candidate_still_cannot_reapply(): void
    {
        Storage::fake('local');
        [$c, $o, $a] = $this->scenario();
        app(RecruitmentService::class)->reject($a);

        // The unique (candidate_id, job_opening_id) still blocks a new row.
        $this->expectException(\Illuminate\Database\QueryException::class);
        Applicant::create([
            'job_opening_id' => $o->id, 'candidate_id' => $c->id,
            'name' => $c->name, 'stage' => 'applied',
        ]);
    }

    public function test_purge_command_respects_retention_window(): void
    {
        Storage::fake('local');
        app(\App\Services\SettingService::class)->set('recruitment_cv_retention_days', 30);

        // One rejected 40 days ago (should purge), one 5 days ago (should stay).
        [$c1, $o1, $old, $relOld] = $this->scenario();
        $old->forceFill([
            'stage' => 'rejected', 'rejected_at' => now()->subDays(40), 'cv_purged_at' => null,
        ])->save();

        [$c2, $o2, $recent, $relRecent] = $this->scenario();
        $recent->forceFill([
            'stage' => 'rejected', 'rejected_at' => now()->subDays(5), 'cv_purged_at' => null,
        ])->save();

        $this->artisan('recruitment:purge-cvs')->assertExitCode(0);

        // Old one purged.
        $this->assertNull($old->fresh()->cv_path);
        $this->assertNotNull($old->fresh()->cv_purged_at);
        Storage::disk('local')->assertMissing($relOld);

        // Recent one untouched.
        $this->assertNotNull($recent->fresh()->cv_path);
        $this->assertNull($recent->fresh()->cv_purged_at);
        Storage::disk('local')->assertExists($relRecent);
    }

    public function test_purge_dry_run_changes_nothing(): void
    {
        Storage::fake('local');
        [$c, $o, $a, $rel] = $this->scenario();
        $a->forceFill(['stage' => 'rejected', 'rejected_at' => now()->subDays(60)])->save();

        $this->artisan('recruitment:purge-cvs --dry-run')->assertExitCode(0);

        // Nothing deleted on a dry run.
        $this->assertNotNull($a->fresh()->cv_path);
        Storage::disk('local')->assertExists($rel);
    }

    public function test_reject_endpoint_requires_edit_permission(): void
    {
        [$c, $o, $a] = $this->scenario();

        // Unauthenticated → redirected to admin login, CV untouched.
        $this->post(backpack_url("recruitment/applicant/{$a->id}/reject"))
            ->assertRedirect();
        $this->assertSame('applied', $a->fresh()->stage);
    }

    // ── M18-6: ghosting purge + archive ────────────────────

    public function test_ghosted_cv_purged_after_opening_closed(): void
    {
        Storage::fake('local');
        app(\App\Services\SettingService::class)->set('recruitment_ghost_retention_days', 90);

        [$c, $o, $a, $rel] = $this->scenario();
        // Never rejected, never hired; opening closed 120 days ago.
        $o->forceFill(['status' => 'closed', 'closed_at' => now()->subDays(120)])->save();

        $this->artisan('recruitment:purge-cvs')->assertExitCode(0);

        $this->assertNull($a->fresh()->cv_path);
        $this->assertNotNull($a->fresh()->cv_purged_at);
        Storage::disk('local')->assertMissing($rel);
    }

    public function test_ghosted_cv_kept_when_opening_recently_closed(): void
    {
        Storage::fake('local');
        app(\App\Services\SettingService::class)->set('recruitment_ghost_retention_days', 90);

        [$c, $o, $a, $rel] = $this->scenario();
        $o->forceFill(['status' => 'closed', 'closed_at' => now()->subDays(10)])->save();

        $this->artisan('recruitment:purge-cvs')->assertExitCode(0);

        // Within window → still there.
        $this->assertNotNull($a->fresh()->cv_path);
        Storage::disk('local')->assertExists($rel);
    }

    public function test_hired_cv_never_ghost_purged(): void
    {
        Storage::fake('local');
        app(\App\Services\SettingService::class)->set('recruitment_ghost_retention_days', 90);

        [$c, $o, $a, $rel] = $this->scenario();
        $u = User::factory()->create();
        $a->forceFill(['stage' => 'hired', 'hired_user_id' => $u->id])->save();
        $o->forceFill(['status' => 'closed', 'closed_at' => now()->subDays(200)])->save();

        $this->artisan('recruitment:purge-cvs')->assertExitCode(0);

        // Hired applicants keep their CV in applicant-cv.
        $this->assertNotNull($a->fresh()->cv_path);
        Storage::disk('local')->assertExists($rel);
    }

    public function test_reject_with_archive_uses_configured_disk(): void
    {
        Storage::fake('local');
        Storage::fake('archive_test');
        app(\App\Services\SettingService::class)->set('recruitment_reject_action', 'archive');
        app(\App\Services\SettingService::class)->set('recruitment_archive_disk', 'archive_test');

        [$c, $o, $a, $rel] = $this->scenario();

        app(RecruitmentService::class)->reject($a);

        $a->refresh();
        // CV moved to cold storage on the configured disk; not purged.
        $this->assertNull($a->cv_purged_at);
        $this->assertStringStartsWith('cold/', $a->cv_path);
        Storage::disk('archive_test')->assertExists($a->cv_path);
        Storage::disk('local')->assertMissing($rel);
    }

    public function test_reject_archive_falls_back_to_active_provider_when_blank(): void
    {
        Storage::fake('local');
        app(\App\Services\SettingService::class)->set('recruitment_reject_action', 'archive');
        app(\App\Services\SettingService::class)->set('recruitment_archive_disk', ''); // blank → active provider (local by default)

        [$c, $o, $a, $rel] = $this->scenario();

        app(RecruitmentService::class)->reject($a);

        $a->refresh();
        $this->assertNull($a->cv_purged_at);
        $this->assertStringStartsWith('cold/', $a->cv_path);
        // Active provider defaults to local disk → cold copy exists there.
        Storage::disk('local')->assertExists($a->cv_path);
    }
}
