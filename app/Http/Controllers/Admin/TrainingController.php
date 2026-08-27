<?php

namespace App\Http\Controllers\Admin;

use App\Models\Training;
use App\Models\TrainingEnrollment;
use App\Models\TrainingMaterial;
use App\Models\TrainingQuestion;
use App\Models\User;
use App\Services\StorageManager;
use App\Services\TrainingService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Prologue\Alerts\Facades\Alert;

/**
 * M11 — Training admin. One tabbed "manage" page per training carries the
 * whole authoring flow (materials + quiz + participants) so a trainer never
 * hops between screens (requirement R1/R3).
 */
class TrainingController extends Controller
{
    public function __construct(
        private readonly TrainingService $training,
        private readonly StorageManager $storage,
    ) {
    }

    private function guardView(): void
    {
        abort_unless(backpack_user()?->can('training.view'), 403);
    }

    private function guardEdit(): void
    {
        abort_unless(backpack_user()?->can('training.edit'), 403);
    }

    // ── List ───────────────────────────────────────────────

    public function index(Request $request)
    {
        $this->guardView();

        $showArchived = $request->boolean('archived');

        $trainings = Training::withCount(['materials', 'questions', 'enrollments'])
            ->when($showArchived,
                fn ($q) => $q->where('status', Training::STATUS_ARCHIVED),
                fn ($q) => $q->where('status', '!=', Training::STATUS_ARCHIVED))
            ->orderByDesc('created_at')
            ->get();

        return view('admin.training.index', [
            'trainings'    => $trainings,
            'showArchived' => $showArchived,
            'canEdit'      => backpack_user()->can('training.edit'),
        ]);
    }

    // ── Create ─────────────────────────────────────────────

    public function create()
    {
        $this->guardEdit();

        return view('admin.training.create', [
            'trainers' => User::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $this->guardEdit();

        $data = $request->validate([
            'title'         => 'required|string|max:160',
            'description'   => 'nullable|string',
            'trainer_id'    => 'nullable|integer|exists:users,id',
            'category'      => 'nullable|string|max:80',
            'passing_score' => 'required|integer|min:1|max:100',
            'max_attempts'  => 'required|integer|min:1|max:10',
        ], [
            'title.required'         => 'Judul pelatihan wajib diisi.',
            'passing_score.required' => 'KKM (nilai lulus) wajib diisi.',
        ]);

        $training = Training::create($data + ['status' => Training::STATUS_DRAFT]);

        Alert::success('Pelatihan dibuat. Tambahkan materi & latihan.')->flash();

        return redirect()->route('training.manage', $training->id);
    }

    // ── Manage (tabbed page) ───────────────────────────────

    public function manage(int $id)
    {
        $this->guardView();

        $training = Training::with(['materials', 'questions', 'trainer'])->findOrFail($id);
        $enrollments = TrainingEnrollment::with('user')
            ->where('training_id', $id)->get();

        // Candidates for enrolling = employees not yet enrolled.
        $enrolledIds = $enrollments->pluck('user_id')->all();
        $employees = User::whereNotIn('id', $enrolledIds)
            ->orderBy('name')->get(['id', 'name']);

        return view('admin.training.manage', [
            'training'    => $training,
            'enrollments' => $enrollments,
            'employees'   => $employees,
            'trainers'    => User::orderBy('name')->get(['id', 'name']),
            'canEdit'     => backpack_user()->can('training.edit'),
        ]);
    }

    public function update(Request $request, int $id)
    {
        $this->guardEdit();

        $training = Training::findOrFail($id);
        $data = $request->validate([
            'title'         => 'required|string|max:160',
            'description'   => 'nullable|string',
            'trainer_id'    => 'nullable|integer|exists:users,id',
            'category'      => 'nullable|string|max:80',
            'passing_score' => 'required|integer|min:1|max:100',
            'max_attempts'  => 'required|integer|min:1|max:10',
        ]);

        $training->update($data);
        Alert::success('Detail pelatihan diperbarui.')->flash();

        return redirect()->route('training.manage', $id);
    }

    // ── Materials ──────────────────────────────────────────

    public function storeMaterial(Request $request, int $id)
    {
        $this->guardEdit();

        $training = Training::findOrFail($id);
        $data = $request->validate([
            'title'      => 'required|string|max:160',
            'content'    => 'nullable|string',
            'video_url'  => 'nullable|url|max:255',
            'attachment' => 'nullable|file|max:10240',
        ], ['title.required' => 'Judul materi wajib diisi.']);

        $path = null;
        if ($request->hasFile('attachment')) {
            $path = $this->storage->disk()->putFile('training-materials', $request->file('attachment'));
        }

        $nextPos = (int) $training->materials()->max('position') + 1;
        $training->materials()->create([
            'position'        => $nextPos,
            'title'           => $data['title'],
            'content'         => $data['content'] ?? null,
            'video_url'       => $data['video_url'] ?? null,
            'attachment_path' => $path,
        ]);

        Alert::success('Materi ditambahkan.')->flash();

        return redirect()->route('training.manage', $id);
    }

    public function deleteMaterial(int $id, int $materialId)
    {
        $this->guardEdit();

        TrainingMaterial::where('training_id', $id)->where('id', $materialId)->delete();
        Alert::success('Materi dihapus.')->flash();

        return redirect()->route('training.manage', $id);
    }

    public function moveMaterial(Request $request, int $id, int $materialId)
    {
        $this->guardEdit();

        $dir = $request->input('dir') === 'up' ? 'up' : 'down';
        $material = TrainingMaterial::where('training_id', $id)->findOrFail($materialId);

        $swap = TrainingMaterial::where('training_id', $id)
            ->when($dir === 'up',
                fn ($q) => $q->where('position', '<', $material->position)->orderByDesc('position'),
                fn ($q) => $q->where('position', '>', $material->position)->orderBy('position'))
            ->first();

        if ($swap) {
            $tmp = $material->position;
            $material->update(['position' => $swap->position]);
            $swap->update(['position' => $tmp]);
        }

        return redirect()->route('training.manage', $id);
    }

    // ── Questions ──────────────────────────────────────────

    public function storeQuestion(Request $request, int $id)
    {
        $this->guardEdit();

        $training = Training::findOrFail($id);
        $data = $request->validate([
            'question'       => 'required|string',
            'option_a'       => 'required|string|max:500',
            'option_b'       => 'required|string|max:500',
            'option_c'       => 'nullable|string|max:500',
            'option_d'       => 'nullable|string|max:500',
            'correct_option' => 'required|in:a,b,c,d',
        ], [
            'question.required'       => 'Pertanyaan wajib diisi.',
            'correct_option.required' => 'Tandai kunci jawaban.',
        ]);

        $nextPos = (int) $training->questions()->max('position') + 1;
        $training->questions()->create($data + ['position' => $nextPos]);

        Alert::success('Soal ditambahkan.')->flash();

        return redirect()->route('training.manage', $id);
    }

    public function deleteQuestion(int $id, int $questionId)
    {
        $this->guardEdit();

        TrainingQuestion::where('training_id', $id)->where('id', $questionId)->delete();
        Alert::success('Soal dihapus.')->flash();

        return redirect()->route('training.manage', $id);
    }

    // ── Participants ───────────────────────────────────────

    public function enroll(Request $request, int $id)
    {
        $this->guardEdit();

        $training = Training::findOrFail($id);
        $data = $request->validate([
            'user_ids'   => 'required|array',
            'user_ids.*' => 'integer|exists:users,id',
        ], ['user_ids.required' => 'Pilih minimal satu peserta.']);

        $created = $this->training->enroll($training, $data['user_ids']);
        Alert::success("{$created} peserta ditugaskan.")->flash();

        return redirect()->route('training.manage', $id);
    }

    public function unenroll(int $id, int $enrollmentId)
    {
        $this->guardEdit();

        TrainingEnrollment::where('training_id', $id)->where('id', $enrollmentId)->delete();
        Alert::success('Peserta dikeluarkan.')->flash();

        return redirect()->route('training.manage', $id);
    }

    public function resetAttempt(int $id, int $enrollmentId)
    {
        $this->guardEdit();

        $enrollment = TrainingEnrollment::where('training_id', $id)->findOrFail($enrollmentId);
        $this->training->resetAttempts($enrollment);
        Alert::success('Percobaan peserta direset.')->flash();

        return redirect()->route('training.manage', $id);
    }

    // ── Lifecycle ──────────────────────────────────────────

    public function publish(int $id)
    {
        $this->guardEdit();

        $training = Training::withCount(['materials', 'questions'])->findOrFail($id);

        if ($training->materials_count === 0 || $training->questions_count === 0) {
            Alert::error('Tambahkan minimal 1 materi dan 1 soal sebelum menerbitkan.')->flash();

            return redirect()->route('training.manage', $id);
        }

        $training->update(['status' => Training::STATUS_PUBLISHED, 'archived_at' => null]);
        Alert::success('Pelatihan diterbitkan. Peserta bisa mulai.')->flash();

        return redirect()->route('training.manage', $id);
    }

    public function archive(int $id)
    {
        $this->guardEdit();

        $this->training->archive(Training::findOrFail($id));
        Alert::success('Pelatihan diarsipkan.')->flash();

        return redirect()->route('training.index');
    }

    public function restore(int $id)
    {
        $this->guardEdit();

        $this->training->restore(Training::findOrFail($id));
        Alert::success('Pelatihan dipulihkan (status Draft).')->flash();

        return redirect()->route('training.manage', $id);
    }
}
