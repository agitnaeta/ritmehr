<?php

namespace Tests\Feature;

use App\Models\Kpi;
use App\Models\Review;
use App\Models\ReviewCycle;
use App\Models\ReviewItem;
use App\Models\User;
use App\Services\PerformanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * M10 — Performance: cycle generation, self/manager scoring, weighted finalise,
 * route guards + ownership.
 */
class PerformanceTest extends TestCase
{
    use RefreshDatabase;

    private PerformanceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PerformanceService::class);
    }

    private function user(string $name, array $attrs = []): User
    {
        return User::create(array_merge([
            'name'     => $name,
            'email'    => str($name)->slug() . uniqid() . '@example.test',
            'password' => bcrypt('secret'),
        ], $attrs));
    }

    private function cycle(array $attrs = []): ReviewCycle
    {
        return ReviewCycle::create(array_merge([
            'name'       => 'Semester 1',
            'start_date' => now()->subMonth(),
            'end_date'   => now(),
            'status'     => ReviewCycle::STATUS_ACTIVE,
        ], $attrs));
    }

    private function kpi(string $name, int $weight = 1): Kpi
    {
        return Kpi::create(['name' => $name, 'weight' => $weight, 'is_active' => true]);
    }

    private function userWith(array $permissions): User
    {
        $guard = config('backpack.base.guard', 'backpack');
        $perms = collect($permissions)->map(fn ($p) =>
            Permission::firstOrCreate(['name' => $p, 'guard_name' => $guard]));
        $user = $this->user('Actor ' . uniqid());
        $user->givePermissionTo($perms);

        return $user;
    }

    // ── Generation ─────────────────────────────────────────

    public function test_generate_creates_one_review_per_employee_with_kpi_items(): void
    {
        $this->kpi('Kualitas');
        $this->kpi('Kuantitas');
        $this->user('Emp A');
        $this->user('Emp B');
        $cycle = $this->cycle();

        $created = $this->service->generateReviews($cycle);

        $this->assertSame(2, $created);
        $this->assertSame(2, Review::where('review_cycle_id', $cycle->id)->count());
        $review = Review::where('review_cycle_id', $cycle->id)->first();
        $this->assertSame(2, $review->items()->count(), 'a row per active KPI');
    }

    public function test_generate_is_idempotent_and_tops_up_new_kpis(): void
    {
        $this->kpi('Kualitas');
        $this->user('Emp A');
        $cycle = $this->cycle();

        $this->service->generateReviews($cycle);
        // Add a KPI mid-cycle, regenerate.
        $this->kpi('Inisiatif');
        $createdAgain = $this->service->generateReviews($cycle);

        $this->assertSame(0, $createdAgain, 'no duplicate reviews');
        $review = Review::where('review_cycle_id', $cycle->id)->first();
        $this->assertSame(2, $review->items()->count(), 'the new KPI is topped up');
    }

    public function test_generate_without_active_kpi_throws(): void
    {
        $this->user('Emp A');
        $cycle = $this->cycle();

        $this->expectException(\DomainException::class);
        $this->service->generateReviews($cycle);
    }

    public function test_reviewer_is_the_users_manager(): void
    {
        $this->kpi('Kualitas');
        $manager = $this->user('Boss');
        $this->user('Staff', ['manager_id' => $manager->id]);
        $cycle = $this->cycle();

        $this->service->generateReviews($cycle);

        $review = Review::whereHas('user', fn ($q) => $q->where('name', 'Staff'))->first();
        $this->assertSame($manager->id, $review->reviewer_id);
    }

    // ── Scoring + weighted finalise ────────────────────────

    public function test_finalize_computes_weighted_manager_score(): void
    {
        $k1 = $this->kpi('Kualitas', 3);
        $k2 = $this->kpi('Kuantitas', 1);
        $this->user('Emp A');
        $cycle = $this->cycle();
        $this->service->generateReviews($cycle);

        $review = Review::where('review_cycle_id', $cycle->id)->with('items')->first();
        $this->service->submitManager($review, [$k1->id => 5, $k2->id => 1]);
        $finalized = $this->service->finalize($review->fresh());

        // (5*3 + 1*1) / (3+1) = 16/4 = 4.00
        $this->assertSame(4.0, (float) $finalized->final_score);
        $this->assertSame(Review::STATUS_FINALIZED, $finalized->status);
        $this->assertNotNull($finalized->finalized_at);
    }

    public function test_finalize_without_manager_scores_throws(): void
    {
        $this->kpi('Kualitas');
        $this->user('Emp A');
        $cycle = $this->cycle();
        $this->service->generateReviews($cycle);
        $review = Review::where('review_cycle_id', $cycle->id)->first();

        $this->expectException(\DomainException::class);
        $this->service->finalize($review);
    }

    public function test_a_finalized_review_cannot_be_edited(): void
    {
        $k1 = $this->kpi('Kualitas');
        $this->user('Emp A');
        $cycle = $this->cycle();
        $this->service->generateReviews($cycle);
        $review = Review::where('review_cycle_id', $cycle->id)->with('items')->first();
        $this->service->submitManager($review, [$k1->id => 4]);
        $this->service->finalize($review->fresh());

        $this->expectException(\DomainException::class);
        $this->service->submitManager($review->fresh(), [$k1->id => 2]);
    }

    public function test_self_scores_are_saved_and_clamped(): void
    {
        $k1 = $this->kpi('Kualitas');
        $this->user('Emp A');
        $cycle = $this->cycle();
        $this->service->generateReviews($cycle);
        $review = Review::where('review_cycle_id', $cycle->id)->with('items')->first();

        $this->service->submitSelf($review, [$k1->id => 9], 'mantap'); // out of range → clamp to 5

        $item = ReviewItem::where('review_id', $review->id)->where('kpi_id', $k1->id)->first();
        $this->assertSame(5, $item->self_score);
        $this->assertSame('mantap', $review->fresh()->self_comment);
        $this->assertSame(Review::STATUS_SELF_SUBMITTED, $review->fresh()->status);
    }

    // ── Route guards + ownership ───────────────────────────

    public function test_scoreboard_requires_performance_edit(): void
    {
        $viewer = $this->userWith(['performance.review_self']);

        $this->actingAs($viewer, config('backpack.base.guard'))
            ->get(backpack_url('performance/scoreboard'))
            ->assertStatus(403);
    }

    public function test_employee_can_open_their_own_review_but_not_others(): void
    {
        $k1 = $this->kpi('Kualitas');
        $owner = $this->userWith(['performance.review_self']);
        $other = $this->user('Someone Else');
        $cycle = $this->cycle();
        $this->service->generateReviews($cycle);

        $ownReview = Review::where('user_id', $owner->id)->first();
        $otherReview = Review::where('user_id', $other->id)->first();

        $this->actingAs($owner, config('backpack.base.guard'))
            ->get(backpack_url('performance/review/' . $ownReview->id))
            ->assertOk();

        $this->actingAs($owner, config('backpack.base.guard'))
            ->get(backpack_url('performance/review/' . $otherReview->id))
            ->assertStatus(403);
    }

    public function test_owner_can_submit_self_via_endpoint(): void
    {
        $k1 = $this->kpi('Kualitas');
        $owner = $this->userWith(['performance.review_self']);
        $cycle = $this->cycle();
        $this->service->generateReviews($cycle);
        $review = Review::where('user_id', $owner->id)->first();

        $this->actingAs($owner, config('backpack.base.guard'))
            ->post(backpack_url('performance/review/' . $review->id . '/self'), [
                'scores' => [$k1->id => 4],
                'comment' => 'oke',
            ])
            ->assertRedirect();

        $this->assertSame(4, ReviewItem::where('review_id', $review->id)->first()->self_score);
    }

    public function test_index_page_lists_my_reviews(): void
    {
        $this->kpi('Kualitas');
        $owner = $this->userWith(['performance.review_self']);
        $cycle = $this->cycle(['name' => 'Siklus Uji']);
        $this->service->generateReviews($cycle);

        $this->actingAs($owner, config('backpack.base.guard'))
            ->get(backpack_url('performance'))
            ->assertOk()
            ->assertSee('Siklus Uji');
    }
}
