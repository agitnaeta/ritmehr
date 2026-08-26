<?php

namespace App\Services;

use App\Models\Kpi;
use App\Models\Review;
use App\Models\ReviewCycle;
use App\Models\ReviewItem;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * M10 — Performance review cycle operations.
 *
 * Owns the parts that must be correct rather than merely present:
 *  - generating one review (with a row per active KPI) per employee in a cycle;
 *  - recording self and manager scores;
 *  - finalising — computing the weighted-average manager score.
 *
 * Generation is idempotent: re-running for a cycle tops up missing reviews/items
 * rather than duplicating them, so adding a KPI or a new hire mid-cycle is safe.
 */
class PerformanceService
{
    public function __construct(private readonly NotificationService $notifications)
    {
    }

    /**
     * Create a Review for every employed user in the cycle, each with a
     * ReviewItem per active KPI. Idempotent — safe to re-run.
     *
     * @return int number of reviews created (not counting ones already present)
     */
    public function generateReviews(ReviewCycle $cycle): int
    {
        $kpis = Kpi::active()->get();

        if ($kpis->isEmpty()) {
            throw new \DomainException('Belum ada KPI aktif. Tambahkan KPI sebelum membuat siklus penilaian.');
        }

        $created = 0;

        DB::transaction(function () use ($cycle, $kpis, &$created) {
            foreach (User::employed()->get() as $user) {
                $review = Review::firstOrCreate(
                    ['review_cycle_id' => $cycle->id, 'user_id' => $user->id],
                    ['reviewer_id' => $user->manager_id, 'status' => Review::STATUS_PENDING]
                );

                if ($review->wasRecentlyCreated) {
                    $created++;
                }

                // Top up any missing KPI rows (handles KPIs added mid-cycle).
                foreach ($kpis as $kpi) {
                    ReviewItem::firstOrCreate(
                        ['review_id' => $review->id, 'kpi_id' => $kpi->id],
                        ['weight' => max(1, (int) $kpi->weight)]
                    );
                }
            }
        });

        return $created;
    }

    /**
     * Save the employee's self-assessment scores + comment.
     *
     * @param array<int,int> $scores  kpi_id => score (1..5)
     * @throws \DomainException when the review is already finalised
     */
    public function submitSelf(Review $review, array $scores, ?string $comment = null): Review
    {
        $this->assertNotFinalized($review);

        DB::transaction(function () use ($review, $scores, $comment) {
            foreach ($review->items as $item) {
                if (array_key_exists($item->kpi_id, $scores) && $scores[$item->kpi_id] !== null) {
                    $item->self_score = $this->clampScore($scores[$item->kpi_id]);
                    $item->save();
                }
            }

            $review->self_comment = $comment;
            $review->self_submitted_at = now();
            // Do not downgrade a manager-submitted review.
            if (in_array($review->status, [Review::STATUS_PENDING], true)) {
                $review->status = Review::STATUS_SELF_SUBMITTED;
            }
            $review->save();
        });

        return $review->refresh();
    }

    /**
     * Save the manager's scores + comment. Does NOT finalise on its own — the
     * manager finalises explicitly so the final score is a deliberate act.
     *
     * @param array<int,int> $scores  kpi_id => score (1..5)
     */
    public function submitManager(Review $review, array $scores, ?string $comment = null): Review
    {
        $this->assertNotFinalized($review);

        DB::transaction(function () use ($review, $scores, $comment) {
            foreach ($review->items as $item) {
                if (array_key_exists($item->kpi_id, $scores) && $scores[$item->kpi_id] !== null) {
                    $item->manager_score = $this->clampScore($scores[$item->kpi_id]);
                    $item->save();
                }
            }

            $review->manager_comment = $comment;
            $review->status = Review::STATUS_MANAGER_SUBMITTED;
            $review->save();
        });

        return $review->refresh();
    }

    /**
     * Finalise: compute the weighted-average manager score and lock the review.
     *
     * @throws \DomainException when no manager scores exist yet
     */
    public function finalize(Review $review): Review
    {
        $this->assertNotFinalized($review);

        $score = $this->weightedManagerScore($review);

        if ($score === null) {
            throw new \DomainException('Belum ada skor manajer — tidak bisa difinalisasi.');
        }

        $review->final_score = $score;
        $review->status = Review::STATUS_FINALIZED;
        $review->finalized_at = now();
        $review->save();

        // Tell the employee their review is done.
        $this->notifications->notify($review->user, 'performance_finalized', [
            'title' => 'Penilaian Kinerja Selesai',
            'body'  => 'Penilaian kinerja Anda untuk siklus "' . ($review->cycle?->name ?? '-')
                . '" telah difinalisasi. Skor akhir: ' . number_format($score, 2) . ' / 5.',
        ]);

        return $review->refresh();
    }

    /**
     * Weighted average of manager scores across the review's items. Items
     * without a manager score are skipped. Returns null when none are scored.
     */
    public function weightedManagerScore(Review $review): ?float
    {
        $items = $review->relationLoaded('items') ? $review->items : $review->items()->get();

        $weightedSum = 0;
        $weightTotal = 0;

        foreach ($items as $item) {
            if ($item->manager_score === null) {
                continue;
            }
            $w = max(1, (int) $item->weight);
            $weightedSum += $item->manager_score * $w;
            $weightTotal += $w;
        }

        if ($weightTotal === 0) {
            return null;
        }

        return round($weightedSum / $weightTotal, 2);
    }

    /**
     * Average finalised score per employee for a cycle, for the dashboard.
     *
     * @return Collection<int, array{user:User, score:float, status:string}>
     */
    public function cycleScoreboard(ReviewCycle $cycle): Collection
    {
        return Review::with('user')
            ->where('review_cycle_id', $cycle->id)
            ->get()
            ->map(fn (Review $r) => [
                'user'   => $r->user,
                'score'  => $r->final_score,
                'status' => $r->status,
            ])
            ->sortByDesc(fn ($row) => $row['score'] ?? -1)
            ->values();
    }

    private function assertNotFinalized(Review $review): void
    {
        if ($review->isFinalized()) {
            throw new \DomainException('Penilaian sudah difinalisasi dan tidak bisa diubah.');
        }
    }

    private function clampScore(int $score): int
    {
        return max(1, min(5, $score));
    }
}
