<?php

namespace App\Http\Controllers\Admin;

use App\Models\Review;
use App\Models\ReviewCycle;
use App\Services\PerformanceService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Prologue\Alerts\Facades\Alert;

/**
 * M10 — Performance review flow.
 *
 * The CRUD controllers cover cycles and the KPI catalogue; this controller
 * carries the business flow: generating reviews, filling the self/manager
 * scoring form, finalising (weighted score), and the scoreboard dashboard.
 */
class PerformanceController extends Controller
{
    public function __construct(private readonly PerformanceService $performance)
    {
    }

    private function guardView(): void
    {
        abort_unless(
            backpack_user()?->canAny(['performance.view', 'performance.review_self']),
            403
        );
    }

    private function guardManage(): void
    {
        abort_unless(backpack_user()?->can('performance.edit'), 403);
    }

    /**
     * My reviews (self-service) — every review where I am the employee, plus,
     * for managers/HR, a link to the team scoreboard per cycle.
     */
    public function index(Request $request)
    {
        $this->guardView();

        $me = backpack_user();

        $mine = Review::with(['cycle'])
            ->where('user_id', $me->id)
            ->get();

        // Reviews I must score as the manager.
        $toReview = collect();
        if ($me->can('performance.edit')) {
            $toReview = Review::with(['cycle', 'user'])
                ->where('reviewer_id', $me->id)
                ->whereNotIn('status', [Review::STATUS_FINALIZED])
                ->get();
        }

        return view('admin.performance.index', [
            'mine'     => $mine,
            'toReview' => $toReview,
            'cycles'   => ReviewCycle::orderByDesc('start_date')->get(),
            'canManage' => $me->can('performance.edit'),
        ]);
    }

    /**
     * Generate reviews for every employee in a cycle (idempotent).
     */
    public function generate(Request $request, int $cycleId)
    {
        $this->guardManage();

        $cycle = ReviewCycle::findOrFail($cycleId);

        try {
            $created = $this->performance->generateReviews($cycle);
        } catch (\DomainException $e) {
            Alert::error($e->getMessage())->flash();

            return redirect()->back();
        }

        Alert::success("Penilaian dibuat untuk siklus {$cycle->name} ({$created} review baru).")->flash();

        return redirect()->back();
    }

    /**
     * The scoring form for a single review. Self-column is editable by the
     * employee; manager column by the reviewer/HR.
     */
    public function show(int $id)
    {
        $this->guardView();

        $review = Review::with(['items.kpi', 'user', 'reviewer', 'cycle'])->findOrFail($id);
        $me = backpack_user();

        $isOwner = $review->user_id === $me->id;
        $isManager = $me->can('performance.edit')
            && ($review->reviewer_id === $me->id || $me->hasRole(['super_admin', 'hr_admin']));

        // You may only open a review you own or one you manage.
        abort_unless($isOwner || $isManager, 403, 'Anda tidak berhak membuka penilaian ini.');

        return view('admin.performance.review', [
            'review'    => $review,
            'isOwner'   => $isOwner,
            'isManager' => $isManager,
            'weighted'  => $this->performance->weightedManagerScore($review),
        ]);
    }

    /**
     * Save self-assessment scores (employee).
     */
    public function submitSelf(Request $request, int $id)
    {
        $this->guardView();

        $review = Review::with('items')->findOrFail($id);
        abort_unless($review->user_id === backpack_user()->id, 403);

        $data = $request->validate([
            'scores'  => 'array',
            'scores.*' => 'nullable|integer|min:1|max:5',
            'comment' => 'nullable|string',
        ]);

        try {
            $this->performance->submitSelf($review, $data['scores'] ?? [], $data['comment'] ?? null);
        } catch (\DomainException $e) {
            Alert::error($e->getMessage())->flash();

            return redirect()->back();
        }

        Alert::success('Self-review tersimpan.')->flash();

        return redirect()->back();
    }

    /**
     * Save manager scores (reviewer / HR).
     */
    public function submitManager(Request $request, int $id)
    {
        $this->guardManage();

        $review = Review::with('items')->findOrFail($id);
        $me = backpack_user();
        abort_unless(
            $review->reviewer_id === $me->id || $me->hasRole(['super_admin', 'hr_admin']),
            403
        );

        $data = $request->validate([
            'scores'  => 'array',
            'scores.*' => 'nullable|integer|min:1|max:5',
            'comment' => 'nullable|string',
        ]);

        try {
            $this->performance->submitManager($review, $data['scores'] ?? [], $data['comment'] ?? null);
        } catch (\DomainException $e) {
            Alert::error($e->getMessage())->flash();

            return redirect()->back();
        }

        Alert::success('Penilaian manajer tersimpan.')->flash();

        return redirect()->back();
    }

    /**
     * Finalise a review — computes and locks the weighted final score.
     */
    public function finalize(int $id)
    {
        $this->guardManage();

        $review = Review::with('items')->findOrFail($id);
        $me = backpack_user();
        abort_unless(
            $review->reviewer_id === $me->id || $me->hasRole(['super_admin', 'hr_admin']),
            403
        );

        try {
            $this->performance->finalize($review);
        } catch (\DomainException $e) {
            Alert::error($e->getMessage())->flash();

            return redirect()->back();
        }

        Alert::success('Penilaian difinalisasi.')->flash();

        return redirect()->back();
    }

    /**
     * Scoreboard dashboard for a cycle (bar chart of final scores).
     */
    public function scoreboard(Request $request)
    {
        $this->guardManage();

        $cycleId = $request->input('cycle_id');
        $cycle = $cycleId
            ? ReviewCycle::find($cycleId)
            : ReviewCycle::orderByDesc('start_date')->first();

        $rows = $cycle ? $this->performance->cycleScoreboard($cycle) : collect();

        return view('admin.performance.scoreboard', [
            'cycles'  => ReviewCycle::orderByDesc('start_date')->get(),
            'cycle'   => $cycle,
            'rows'    => $rows,
        ]);
    }
}
