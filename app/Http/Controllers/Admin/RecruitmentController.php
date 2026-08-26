<?php

namespace App\Http\Controllers\Admin;

use App\Models\Applicant;
use App\Models\Interview;
use App\Models\JobOpening;
use App\Services\RecruitmentService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Prologue\Alerts\Facades\Alert;

/**
 * M09 — Recruitment pipeline board, interview calendar and hire action.
 *
 * The CRUD controllers cover data entry; this controller carries the actual
 * business flow: dragging an applicant through stages, and hiring (which
 * provisions a User via RecruitmentService).
 */
class RecruitmentController extends Controller
{
    public function __construct(private readonly RecruitmentService $recruitment)
    {
    }

    private function guardView(): void
    {
        abort_unless(backpack_user()?->can('recruitment.view'), 403);
    }

    private function guardEdit(): void
    {
        abort_unless(backpack_user()?->can('recruitment.edit'), 403);
    }

    /**
     * Pipeline kanban: one column per stage, applicants grouped by stage.
     * Optionally scoped to a single opening.
     */
    public function pipeline(Request $request)
    {
        $this->guardView();

        $openingId = $request->input('job_opening_id') ?: null;

        // M21 — order by score so the board mirrors the ranking view.
        // Best first: ai_score → vector_score → date; NULLs sink to the bottom.
        $query = Applicant::with(['jobOpening', 'hiredUser'])
            ->active()
            ->orderByRaw('ai_score IS NULL, ai_score DESC')
            ->orderByRaw('vector_score IS NULL, vector_score DESC')
            ->orderByDesc('created_at');

        if ($openingId) {
            $query->where('job_opening_id', (int) $openingId);
        }

        // M21 — canonical score rank per opening (so cards can show #N). Only
        // meaningful when scoped to one opening; global view shows no numbers.
        $rankMap = $openingId
            ? app(\App\Services\Matching\MatchingService::class)->rankMap((int) $openingId)
            : [];

        $byStage = [];
        foreach (Applicant::PIPELINE as $stage) {
            $byStage[$stage] = collect();
        }
        foreach ($query->get() as $applicant) {
            $byStage[$applicant->stage][] = $applicant;
        }

        return view('admin.recruitment.pipeline', [
            'byStage'   => $byStage,
            'openings'  => JobOpening::orderBy('title')->get(),
            'openingId' => $openingId,
            'rankMap'   => $rankMap,
            'canEdit'   => backpack_user()->can('recruitment.edit'),
            'interviewers' => \App\Models\User::orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * M21 — Ranking view: applicants of ONE opening listed by score, rank 1..N.
     * The # column is the canonical score rank; $orderBy only controls display.
     */
    public function ranking(Request $request)
    {
        $this->guardView();

        $matcher = app(\App\Services\Matching\MatchingService::class);

        $openings = JobOpening::orderBy('title')->get();
        // Default to the first opening that actually has applicants, else the first.
        $openingId = $request->input('job_opening_id') ?: null;
        if (! $openingId && $openings->isNotEmpty()) {
            $withApplicants = Applicant::active()
                ->select('job_opening_id')
                ->groupBy('job_opening_id')
                ->pluck('job_opening_id');
            $openingId = $openings->firstWhere(fn ($o) => $withApplicants->contains($o->id))?->id
                ?? $openings->first()->id;
        }
        $openingId = $openingId ? (int) $openingId : null;

        $allowedSorts = ['ai_score', 'vector_score', 'created_at', 'name'];
        $orderBy = in_array($request->input('order_by'), $allowedSorts, true)
            ? $request->input('order_by')
            : 'ai_score';

        $applicants = collect();
        $rankMap = [];
        $stats = ['total' => 0, 'ai_scored' => 0, 'vector_only' => 0, 'unscored' => 0, 'top_score' => null];
        $applicantCounts = Applicant::active()
            ->selectRaw('job_opening_id, COUNT(*) as c')
            ->groupBy('job_opening_id')
            ->pluck('c', 'job_opening_id');

        if ($openingId) {
            $applicants = $matcher->rankedApplicants($openingId, $orderBy);
            $rankMap = $matcher->rankMap($openingId);
            $stats = $matcher->rankingStats($openingId);
        }

        return view('admin.recruitment.ranking', [
            'openings'        => $openings,
            'openingId'       => $openingId,
            'orderBy'         => $orderBy,
            'applicants'      => $applicants,
            'rankMap'         => $rankMap,
            'stats'           => $stats,
            'applicantCounts' => $applicantCounts,
            'canEdit'         => backpack_user()->can('recruitment.edit'),
            'interviewers'    => \App\Models\User::orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Move an applicant to a new stage (used by the board's drag/drop + buttons).
     * Hiring is routed through the service so a User is provisioned.
     */
    public function moveStage(Request $request, int $id)
    {
        $this->guardEdit();

        $data = $request->validate([
            'stage' => 'required|in:applied,screening,interview,offer,hired,rejected',
        ]);

        $applicant = Applicant::findOrFail($id);

        try {
            $this->recruitment->moveStage($applicant, $data['stage']);
        } catch (\DomainException $e) {
            if ($request->wantsJson()) {
                return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
            }
            Alert::error($e->getMessage())->flash();

            return redirect()->back();
        }

        if ($request->wantsJson()) {
            return response()->json([
                'ok'      => true,
                'stage'   => $applicant->fresh()->stage,
                'hired'   => (bool) $applicant->fresh()->hired_user_id,
            ]);
        }

        Alert::success('Tahap pelamar diperbarui.')->flash();

        return redirect()->back();
    }

    /**
     * Hire an applicant explicitly (button on the card). Idempotent.
     */
    public function hire(Request $request, int $id)
    {
        $this->guardEdit();

        $applicant = Applicant::findOrFail($id);
        $user = $this->recruitment->hire($applicant);

        Alert::success("{$applicant->name} diterima — akun karyawan dibuat. Lengkapi data onboarding.")->flash();

        if ($request->wantsJson()) {
            return response()->json(['ok' => true, 'user_id' => $user->id]);
        }

        return redirect(backpack_url('user/' . $user->id . '/edit'));
    }

    /**
     * M18-5 — Bulk action on multiple applicants (reject or move stage) from the
     * pipeline. Routed through RecruitmentService so each item respects business
     * rules (hired applicants are never touched). Authorized by recruitment.edit.
     */
    public function bulkAction(Request $request)
    {
        $this->guardEdit();

        $data = $request->validate([
            'ids'    => 'required|array|min:1',
            'ids.*'  => 'integer|exists:applicants,id',
            'action' => 'required|in:reject,move',
            'stage'  => 'required_if:action,move|nullable|in:applied,screening,interview,offer',
        ], [
            'ids.required' => 'Pilih minimal satu pelamar.',
        ]);

        $applicants = Applicant::whereIn('id', $data['ids'])
            ->whereNull('hired_user_id') // never touch already-hired
            ->get();

        $count = 0;
        foreach ($applicants as $applicant) {
            try {
                if ($data['action'] === 'reject') {
                    $this->recruitment->reject($applicant);
                } else {
                    $this->recruitment->moveStage($applicant, $data['stage']);
                }
                $count++;
            } catch (\Throwable $e) {
                // skip individual failures, keep going
            }
        }

        $msg = $data['action'] === 'reject'
            ? "{$count} pelamar ditolak (CV dihapus)."
            : "{$count} pelamar dipindahkan.";

        if ($request->wantsJson()) {
            return response()->json(['ok' => true, 'message' => $msg, 'count' => $count]);
        }

        Alert::success($msg)->flash();
        return redirect()->back();
    }

    /**
     * M18-4 — Schedule an interview inline from the pipeline drawer. Applicant is
     * passed by id (no re-picking from a dropdown). Optionally advances the
     * applicant to the "interview" stage. Authorized by recruitment.edit.
     */
    public function scheduleInterview(Request $request, int $id)
    {
        $this->guardEdit();

        $applicant = Applicant::findOrFail($id);

        $data = $request->validate([
            'scheduled_at'   => 'required|date',
            'mode'           => 'required|in:onsite,online,phone',
            'interviewer_id' => 'nullable|exists:users,id',
            'location'       => 'nullable|string|max:255',
            'advance_stage'  => 'nullable|boolean',
        ], [
            'scheduled_at.required' => 'Jadwal wawancara wajib diisi.',
        ]);

        $interview = Interview::create([
            'applicant_id'   => $applicant->id,
            'scheduled_at'   => $data['scheduled_at'],
            'mode'           => $data['mode'],
            'interviewer_id' => $data['interviewer_id'] ?? null,
            'location'       => $data['location'] ?? null,
            'status'         => Interview::STATUS_SCHEDULED,
        ]);

        // Move to the interview stage unless the applicant is already past it.
        if (! empty($data['advance_stage']) && $applicant->stage !== Applicant::STAGE_HIRED) {
            try {
                $this->recruitment->moveStage($applicant, Applicant::STAGE_INTERVIEW);
            } catch (\DomainException $e) {
                // ignore — scheduling still succeeded
            }
        }

        $msg = 'Wawancara dijadwalkan untuk ' . $applicant->name . '.';

        if ($request->wantsJson()) {
            return response()->json(['ok' => true, 'message' => $msg, 'interview_id' => $interview->id]);
        }

        Alert::success($msg)->flash();
        return redirect()->back();
    }

    /**
     * M18-3 — Applicant detail as JSON for the pipeline drawer: profile, AI
     * reasoning, interviews and the stage timeline. Authorized by recruitment.view.
     */
    public function applicantDetail(int $id)
    {
        $this->guardView();

        $applicant = Applicant::with([
            'jobOpening',
            'interviews' => fn ($q) => $q->orderByDesc('scheduled_at'),
            'interviews.interviewer',
            'stageLogs.actor',
        ])->findOrFail($id);

        // M21 — canonical score rank within the opening (so the drawer can show #N).
        $rank = null;
        $rankTotal = null;
        if ($applicant->job_opening_id) {
            $map = app(\App\Services\Matching\MatchingService::class)
                ->rankMap($applicant->job_opening_id);
            $rank = $map[$applicant->id] ?? null;
            $rankTotal = count($map) ?: null;
        }

        return response()->json([
            'id'          => $applicant->id,
            'name'        => $applicant->name,
            'email'       => $applicant->email,
            'phone'       => $applicant->phone,
            'stage'       => $applicant->stage,
            'stage_label' => $applicant->stageLabel(),
            'opening'     => $applicant->jobOpening?->title,
            'expected_salary' => $applicant->expected_salary,
            'notes'       => $applicant->notes,
            'has_cv'      => (bool) $applicant->cv_path,
            'cv_url'      => $applicant->cv_path ? backpack_url("recruitment/applicant/{$applicant->id}/cv") : null,
            'ai_score'    => $applicant->ai_score,
            'vector_score' => $applicant->vector_score,
            'ai_reasoning' => $applicant->ai_reasoning,
            'ai_model'    => $applicant->ai_model,
            'rank'        => $rank,
            'rank_total'  => $rankTotal,
            'hired'       => (bool) $applicant->hired_user_id,
            'interviews'  => $applicant->interviews->map(fn ($iv) => [
                'id'           => $iv->id,
                'scheduled_at' => optional($iv->scheduled_at)->format('d/m/Y H:i'),
                'mode'         => ucfirst($iv->mode),
                'status'       => ucfirst($iv->status),
                'interviewer'  => $iv->interviewer?->name,
                'score'        => $iv->score,
                'location'     => $iv->location,
            ]),
            'timeline'    => $applicant->stageLogs->map(fn ($log) => [
                'from'   => $log->fromStageLabel(),
                'to'     => $log->toStageLabel(),
                'actor'  => $log->actor?->name ?? 'Sistem',
                'note'   => $log->note,
                'at'     => $log->created_at->format('d/m/Y H:i'),
            ]),
        ]);
    }

    /**
     * M18 — Stream an applicant's CV inline (so HR reads it in the drawer/browser
     * without downloading). Authorized by recruitment.view.
     */
    public function streamCv(int $id)
    {
        $this->guardView();

        $applicant = Applicant::findOrFail($id);

        abort_if(! $applicant->cv_path, 404, 'CV tidak tersedia.');

        $disk = \Illuminate\Support\Facades\Storage::disk('local');
        abort_unless($disk->exists($applicant->cv_path), 404, 'Berkas CV tidak ditemukan.');

        return $disk->response(
            $applicant->cv_path,
            'cv-' . $applicant->id . '.pdf',
            ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="cv-' . $applicant->id . '.pdf"']
        );
    }

    /**
     * Interview calendar for a month (data-based → calendar, not a table).
     */
    public function calendar(Request $request)
    {
        $this->guardView();
        $month = $request->input('month')
            ? Carbon::parse($request->input('month') . '-01')
            : now()->startOfMonth();

        $interviews = Interview::with(['applicant', 'interviewer'])
            ->whereYear('scheduled_at', $month->year)
            ->whereMonth('scheduled_at', $month->month)
            ->where('status', '!=', Interview::STATUS_CANCELLED)
            ->orderBy('scheduled_at')
            ->get();

        $byDate = [];
        foreach ($interviews as $iv) {
            $key = $iv->scheduled_at->toDateString();
            $byDate[$key][] = $iv;
        }

        return view('admin.recruitment.calendar', [
            'month'  => $month,
            'byDate' => $byDate,
        ]);
    }

    /**
     * M17 — Rank all applicants of an opening with AI: Qdrant shortlist (Stage 1)
     * then LLM rubric scoring (Stage 2). Degrades gracefully if AI is down.
     */
    public function rankWithAi(Request $request, int $id)
    {
        $this->guardEdit();

        $opening = JobOpening::findOrFail($id);
        $matcher = app(\App\Services\Matching\MatchingService::class);

        $result = $matcher->rankOpening($opening);

        $msg = "Ranking AI selesai: {$result['shortlisted']} di-shortlist (Qdrant), "
             . "{$result['ai_scored']} dinilai LLM.";

        if ($result['shortlisted'] === 0 && $result['ai_scored'] === 0) {
            $msg = 'AI belum tersedia (embedding/LLM/Qdrant tak bisa dihubungi). '
                 . 'Daftar pelamar tetap bisa diproses manual.';
            if ($request->wantsJson()) {
                return response()->json(['ok' => false, 'message' => $msg, 'result' => $result], 200);
            }
            Alert::warning($msg)->flash();
            return redirect()->back();
        }

        if ($request->wantsJson()) {
            return response()->json(['ok' => true, 'message' => $msg, 'result' => $result]);
        }

        Alert::success($msg)->flash();
        return redirect()->back();
    }

    /**
     * M17 — Reject an applicant: mark rejected + delete CV permanently (policy)
     * and remove its vector from Qdrant. Candidate account + metadata stay.
     */
    public function reject(Request $request, int $id)
    {
        $this->guardEdit();

        $applicant = Applicant::findOrFail($id);
        app(\App\Services\RecruitmentService::class)->reject($applicant);

        $msg = "{$applicant->name} ditolak. CV dihapus permanen; akun kandidat tetap.";

        if ($request->wantsJson()) {
            return response()->json(['ok' => true, 'message' => $msg]);
        }

        Alert::success($msg)->flash();
        return redirect()->back();
    }
}
