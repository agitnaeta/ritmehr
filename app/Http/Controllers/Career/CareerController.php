<?php

namespace App\Http\Controllers\Career;

use App\Models\Applicant;
use App\Models\JobOpening;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * M17 — Public careers portal: browse published openings, apply once each,
 * and a candidate dashboard tracking application status.
 */
class CareerController extends Controller
{
    /** Public list of published, open vacancies. */
    public function index()
    {
        $openings = JobOpening::published()
            ->with(['department', 'branch'])
            ->orderByDesc('published_at')
            ->get();

        return view('career.index', [
            'openings'  => $openings,
            'candidate' => Auth::guard('candidate')->user(),
        ]);
    }

    /** Public detail of one opening (by slug). */
    public function show(string $slug)
    {
        $opening = JobOpening::published()->where('slug', $slug)->firstOrFail();
        $candidate = Auth::guard('candidate')->user();

        $alreadyApplied = $candidate
            ? $candidate->hasAppliedTo($opening->id)
            : false;

        return view('career.show', [
            'opening'        => $opening,
            'candidate'      => $candidate,
            'alreadyApplied' => $alreadyApplied,
        ]);
    }

    /** Submit an application (candidate only, once per opening). */
    public function apply(Request $request, string $slug)
    {
        $opening = JobOpening::published()->where('slug', $slug)->firstOrFail();
        $candidate = Auth::guard('candidate')->user();

        // Apply-once guard (app level; DB unique is the backstop).
        if ($candidate->hasAppliedTo($opening->id)) {
            return redirect()->route('career.show', $opening->slug)
                ->with('error', 'Anda sudah melamar posisi ini.');
        }

        if (! $opening->isOpenForApplication()) {
            return redirect()->route('career.show', $opening->slug)
                ->with('error', 'Lowongan ini sudah ditutup.');
        }

        $data = $request->validate([
            'cover_note'      => 'nullable|string|max:2000',
            'expected_salary' => 'nullable|integer|min:0',
            'cv'              => 'required|file|mimes:pdf,doc,docx|max:5120',
        ], [
            'cv.required' => 'CV wajib diunggah.',
            'cv.mimes'    => 'CV harus berupa PDF/DOC/DOCX.',
            'cv.max'      => 'Ukuran CV maksimal 5 MB.',
        ]);

        // Store CV on the local disk (private) under applicant-cv/<candidate_id>.
        $cvPath = $request->file('cv')->store('applicant-cv/' . $candidate->id, 'local');

        $application = Applicant::create([
            'job_opening_id'  => $opening->id,
            'candidate_id'    => $candidate->id,
            'name'            => $candidate->name,
            'email'           => $candidate->email,
            'phone'           => $candidate->phone,
            'stage'           => Applicant::STAGE_APPLIED,
            'cv_path'         => $cvPath,
            'notes'           => $data['cover_note'] ?? null,
            'expected_salary' => $data['expected_salary'] ?? null,
        ]);

        // M17-3 — extract CV text for search + AI matching. Graceful: a failure
        // here never blocks the application being recorded.
        app(\App\Services\CvExtractionService::class)->extractFor($application);

        return redirect()->route('career.dashboard')
            ->with('success', 'Lamaran untuk "' . $opening->title . '" berhasil dikirim.');
    }

    /** Candidate dashboard: their applications + statuses. */
    public function dashboard()
    {
        $candidate = Auth::guard('candidate')->user();

        $applications = Applicant::with('jobOpening')
            ->where('candidate_id', $candidate->id)
            ->latest()
            ->get();

        return view('career.dashboard', [
            'candidate'    => $candidate,
            'applications' => $applications,
        ]);
    }
}
