<?php

namespace App\Http\Controllers\Portal;

use App\Models\CompanyProfile;
use App\Models\Training;
use App\Models\TrainingEnrollment;
use App\Models\User;
use App\Services\TrainingService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Prologue\Alerts\Facades\Alert;

/**
 * M11 — Employee side of Training (/my/training).
 *
 * Every query is scoped to the authenticated user's own enrollments. A
 * participant reads the materials, takes the quiz, and is auto-graded
 * pass/fail; passing issues a printable certificate.
 */
class TrainingPortalController extends Controller
{
    public function __construct(private readonly TrainingService $training)
    {
    }

    private function me(): User
    {
        return backpack_user();
    }

    /** My assigned trainings (via enrollment), each with its result badge. */
    public function index()
    {
        $enrollments = TrainingEnrollment::with('training')
            ->where('user_id', $this->me()->id)
            ->whereHas('training', fn ($q) => $q->where('status', '!=', Training::STATUS_ARCHIVED))
            ->get();

        return view('portal.training_index', ['enrollments' => $enrollments]);
    }

    /** Read a training: materials in order + start-quiz button. */
    public function show(int $id)
    {
        $enrollment = $this->ownedEnrollment($id);
        $training = $enrollment->training->load('materials');

        return view('portal.training_show', [
            'training'   => $training,
            'enrollment' => $enrollment,
        ]);
    }

    /** The quiz form. */
    public function quiz(int $id)
    {
        $enrollment = $this->ownedEnrollment($id);

        if ($enrollment->isPassed()) {
            Alert::info('Anda sudah lulus pelatihan ini.')->flash();

            return redirect()->route('portal.training.show', $id);
        }
        if ($enrollment->isLocked()) {
            Alert::error('Kesempatan mengerjakan sudah habis. Hubungi HR untuk reset.')->flash();

            return redirect()->route('portal.training.show', $id);
        }

        $training = $enrollment->training->load('questions');

        if ($training->questions->isEmpty()) {
            Alert::error('Pelatihan ini belum punya soal latihan.')->flash();

            return redirect()->route('portal.training.show', $id);
        }

        return view('portal.training_quiz', [
            'training'   => $training,
            'enrollment' => $enrollment,
        ]);
    }

    /** Submit answers → auto-grade → result. */
    public function submit(Request $request, int $id)
    {
        $enrollment = $this->ownedEnrollment($id);

        $data = $request->validate([
            'answers'   => 'array',
            'answers.*' => 'nullable|string|in:a,b,c,d',
        ]);

        try {
            $graded = $this->training->grade($enrollment, $data['answers'] ?? []);
        } catch (\DomainException $e) {
            Alert::error($e->getMessage())->flash();

            return redirect()->route('portal.training.show', $id);
        }

        return redirect()->route('portal.training.result', $id);
    }

    /** Result page: pass/fail + score + certificate link when passed. */
    public function result(int $id)
    {
        $enrollment = $this->ownedEnrollment($id);

        return view('portal.training_result', [
            'training'   => $enrollment->training,
            'enrollment' => $enrollment,
        ]);
    }

    /** Printable certificate (A4 print-to-PDF), only when passed. */
    public function certificate(int $id)
    {
        $enrollment = $this->ownedEnrollment($id);

        abort_unless($enrollment->isPassed() && $enrollment->certificate_no, 404, 'Sertifikat belum tersedia.');

        return view('portal.training_certificate', [
            'training'   => $enrollment->training,
            'enrollment' => $enrollment,
            'user'       => $this->me(),
            'company'    => CompanyProfile::first(),
        ]);
    }

    /**
     * Resolve an enrollment owned by the current user, or 404. No route here
     * ever trusts a user id from the request.
     */
    private function ownedEnrollment(int $trainingId): TrainingEnrollment
    {
        return TrainingEnrollment::with('training')
            ->where('training_id', $trainingId)
            ->where('user_id', $this->me()->id)
            ->firstOrFail();
    }
}
