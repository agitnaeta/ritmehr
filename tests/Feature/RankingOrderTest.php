<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\JobOpening;
use App\Models\User;
use App\Services\Matching\MatchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * M21 — Ranking order + rank map + stats for the recruitment ranking view.
 * AI scores ARE fabricated here on purpose: this tests the ORDERING logic
 * (ai_score → vector_score → date, NULL last), not the AI scoring itself.
 */
class RankingOrderTest extends TestCase
{
    use RefreshDatabase;

    private MatchingService $matcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->matcher = app(MatchingService::class);
    }

    private function opening(): JobOpening
    {
        return JobOpening::create([
            'title'     => 'QA Engineer',
            'vacancies' => 1,
            'status'    => JobOpening::STATUS_OPEN,
        ]);
    }

    private function applicant(JobOpening $o, string $name, array $attrs = []): Applicant
    {
        return Applicant::create(array_merge([
            'job_opening_id' => $o->id,
            'name'           => $name,
            'email'          => str($name)->slug() . uniqid() . '@example.test',
            'stage'          => Applicant::STAGE_APPLIED,
        ], $attrs));
    }

    /**
     * Seed a mixed field:
     *  - 3 with ai_score (91, 87, 82)
     *  - 1 with vector_score only (54)
     *  - 1 with nothing
     */
    private function seedMixed(JobOpening $o): array
    {
        return [
            'ai91'  => $this->applicant($o, 'Budi',  ['ai_score' => 91, 'vector_score' => 80]),
            'ai82'  => $this->applicant($o, 'Andi',  ['ai_score' => 82, 'vector_score' => 70]),
            'ai87'  => $this->applicant($o, 'Sari',  ['ai_score' => 87, 'vector_score' => 60]),
            'vec54' => $this->applicant($o, 'Eko',   ['ai_score' => null, 'vector_score' => 54]),
            'none'  => $this->applicant($o, 'Zulfa', ['ai_score' => null, 'vector_score' => null]),
        ];
    }

    public function test_ranked_by_ai_score_desc_then_vector_then_null_last(): void
    {
        $o = $this->opening();
        $a = $this->seedMixed($o);

        $ordered = $this->matcher->rankedApplicants($o->id, 'ai_score')
            ->pluck('id')->all();

        $this->assertSame([
            $a['ai91']->id,   // 91
            $a['ai87']->id,   // 87
            $a['ai82']->id,   // 82
            $a['vec54']->id,  // no ai_score, vector 54
            $a['none']->id,   // nothing → last
        ], $ordered);
    }

    public function test_rank_map_gives_1_to_n_canonical_positions(): void
    {
        $o = $this->opening();
        $a = $this->seedMixed($o);

        $map = $this->matcher->rankMap($o->id);

        $this->assertSame(1, $map[$a['ai91']->id]);
        $this->assertSame(2, $map[$a['ai87']->id]);
        $this->assertSame(3, $map[$a['ai82']->id]);
        $this->assertSame(4, $map[$a['vec54']->id]);
        $this->assertSame(5, $map[$a['none']->id]);
    }

    public function test_order_by_vector_score(): void
    {
        $o = $this->opening();
        $a = $this->seedMixed($o);

        $ordered = $this->matcher->rankedApplicants($o->id, 'vector_score')
            ->pluck('id')->all();

        // vector: 80(Budi), 70(Andi), 60(Sari), 54(Eko), null(Zulfa)
        $this->assertSame([
            $a['ai91']->id,
            $a['ai82']->id,
            $a['ai87']->id,
            $a['vec54']->id,
            $a['none']->id,
        ], $ordered);
    }

    public function test_order_by_name(): void
    {
        $o = $this->opening();
        $a = $this->seedMixed($o);

        $ordered = $this->matcher->rankedApplicants($o->id, 'name')
            ->pluck('name')->all();

        $this->assertSame(['Andi', 'Budi', 'Eko', 'Sari', 'Zulfa'], $ordered);
    }

    public function test_rejected_applicants_are_excluded(): void
    {
        $o = $this->opening();
        $this->applicant($o, 'Aktif', ['ai_score' => 70]);
        $this->applicant($o, 'Ditolak', [
            'ai_score' => 99, 'stage' => Applicant::STAGE_REJECTED, 'rejected_at' => now(),
        ]);

        $ordered = $this->matcher->rankedApplicants($o->id, 'ai_score')
            ->pluck('name')->all();

        $this->assertSame(['Aktif'], $ordered);
    }

    public function test_ranking_stats_counts(): void
    {
        $o = $this->opening();
        $this->seedMixed($o);

        $stats = $this->matcher->rankingStats($o->id);

        $this->assertSame(5, $stats['total']);
        $this->assertSame(3, $stats['ai_scored']);
        $this->assertSame(1, $stats['vector_only']);
        $this->assertSame(1, $stats['unscored']);
        $this->assertSame(91.0, $stats['top_score']);
    }

    public function test_stats_top_score_null_when_none_scored(): void
    {
        $o = $this->opening();
        $this->applicant($o, 'A');
        $this->applicant($o, 'B');

        $stats = $this->matcher->rankingStats($o->id);

        $this->assertSame(2, $stats['total']);
        $this->assertSame(0, $stats['ai_scored']);
        $this->assertNull($stats['top_score']);
    }

    // ── Route + view integration (M21-2 / M21-3) ───────────

    /** A role-less user with direct permissions (CheckIfAdmin lets role-less through). */
    private function userWith(array $permissions): User
    {
        $guard = config('backpack.base.guard', 'backpack');
        $perms = collect($permissions)->map(fn ($p) =>
            Permission::firstOrCreate(['name' => $p, 'guard_name' => $guard]));

        $user = User::create([
            'name'     => 'Recruiter ' . uniqid(),
            'email'    => 'rec' . uniqid() . '@example.test',
            'password' => bcrypt('secret'),
        ]);
        $user->givePermissionTo($perms);

        return $user;
    }

    public function test_ranking_route_requires_recruitment_view(): void
    {
        $blocked = $this->userWith(['presence.view']);

        $this->actingAs($blocked, config('backpack.base.guard'))
            ->get(backpack_url('recruitment/ranking'))
            ->assertStatus(403);
    }

    public function test_ranking_view_shows_candidates_in_score_order(): void
    {
        $allowed = $this->userWith(['recruitment.view']);
        $o = $this->opening();
        $this->applicant($o, 'Budi', ['ai_score' => 91]);
        $this->applicant($o, 'Sari', ['ai_score' => 87]);

        $res = $this->actingAs($allowed, config('backpack.base.guard'))
            ->get(backpack_url('recruitment/ranking') . '?job_opening_id=' . $o->id)
            ->assertOk()
            ->assertSee('Peringkat Kandidat');

        // Budi (91) must appear before Sari (87) in the rendered HTML.
        $html = $res->getContent();
        $this->assertLessThan(
            strpos($html, 'Sari'),
            strpos($html, 'Budi'),
            'higher score renders first'
        );
    }

    public function test_pipeline_passes_rank_map_when_scoped_to_opening(): void
    {
        $allowed = $this->userWith(['recruitment.view']);
        $o = $this->opening();
        $top = $this->applicant($o, 'Budi', ['ai_score' => 91]);

        $res = $this->actingAs($allowed, config('backpack.base.guard'))
            ->get(backpack_url('recruitment/pipeline') . '?job_opening_id=' . $o->id)
            ->assertOk();

        // The #1 rank badge is rendered on the board card.
        $res->assertSee('#1');
    }
}
