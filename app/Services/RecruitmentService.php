<?php

namespace App\Services;

use App\Models\Applicant;
use App\Models\ApplicantStageLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * M09 — Recruitment pipeline operations.
 *
 * Owns the two things that must be correct rather than merely present:
 *  - moving an applicant through pipeline stages;
 *  - hiring — which provisions a real User and links back to the applicant.
 *
 * Hiring is idempotent: calling hire() twice returns the same User instead of
 * creating a duplicate, so a double-clicked button or a re-dragged card is safe.
 */
class RecruitmentService
{
    public function __construct(private readonly NotificationService $notifications)
    {
    }

    /**
     * Move an applicant to a new pipeline stage. Hiring is routed through hire()
     * so the User is always provisioned; leaving the "hired" stage is refused
     * once a User exists (history must stay consistent).
     *
     * @throws \InvalidArgumentException on an unknown stage
     * @throws \DomainException when un-hiring a provisioned applicant
     */
    public function moveStage(Applicant $applicant, string $stage): Applicant
    {
        $valid = array_keys(Applicant::STAGE_LABELS);
        if (! in_array($stage, $valid, true)) {
            throw new \InvalidArgumentException("Tahap tidak dikenal: {$stage}");
        }

        // Already-hired applicants keep their User; refuse silent un-hiring.
        if ($applicant->hired_user_id && $stage !== Applicant::STAGE_HIRED) {
            throw new \DomainException(
                'Pelamar sudah diterima & akun dibuat — tidak bisa dikembalikan ke tahap lain.'
            );
        }

        if ($stage === Applicant::STAGE_HIRED) {
            $this->hire($applicant);

            return $applicant->refresh();
        }

        $from = $applicant->stage;
        $applicant->stage = $stage;
        $applicant->save();

        $this->logStage($applicant, $from, $stage);

        return $applicant;
    }

    /**
     * Hire an applicant: provision a User (if not already) and link it back.
     *
     * Idempotent — returns the existing hired User when called again. Copies the
     * opening's department/position/branch onto the new employee so they land in
     * the right org slot. A random password is set; the employee resets it via
     * the normal flow (never emailed in plain text here).
     */
    public function hire(Applicant $applicant, array $overrides = []): User
    {
        // Already hired → return the same user, do not duplicate.
        if ($applicant->hired_user_id) {
            return $applicant->hiredUser;
        }

        return DB::transaction(function () use ($applicant, $overrides) {
            $opening = $applicant->jobOpening;
            $fromStage = $applicant->stage;

            $user = User::create(array_merge([
                'name'              => $applicant->name,
                'email'             => $applicant->email ?: $this->placeholderEmail($applicant),
                'password'          => bcrypt(Str::random(24)),
                'phone'             => $applicant->phone,
                'department_id'     => $opening?->department_id,
                'position_id'       => $opening?->position_id,
                'branch_id'         => $opening?->branch_id,
                'employment_status' => User::STATUS_PROBATION,
                'join_date'         => now()->toDateString(),
            ], $overrides));

            $applicant->forceFill([
                'stage'         => Applicant::STAGE_HIRED,
                'hired_user_id' => $user->id,
                'hired_at'      => now(),
            ])->save();

            $this->logStage($applicant, $fromStage, Applicant::STAGE_HIRED, 'Diterima — akun karyawan dibuat.');

            // Let HR know a new hire needs onboarding (documents, payroll, etc.).
            $this->notifications->notifyRole('hr_admin', 'recruitment_hired', [
                'title' => 'Karyawan baru direkrut',
                'body'  => "{$user->name} diterima untuk lowongan "
                    . ($opening?->title ?? '-') . '. Lengkapi data onboarding.',
            ]);

            return $user;
        });
    }

    /**
     * M17 — Reject an applicant. Policy (keputusan Capt): delete the CV file
     * PERMANENTLY, remove its vector from Qdrant, and mark rejected_at. The
     * candidate account + application metadata stay (audit + anti re-apply).
     *
     * A daily cron (M17-5) also purges any CV that slipped through, 30 days
     * after rejection, as a safety net.
     */
    public function reject(Applicant $applicant): Applicant
    {
        $fromStage = $applicant->stage;

        // CV disposition follows config (keputusan Capt: no hardcode).
        $action = (string) (setting('recruitment_reject_action') ?: 'delete');

        if ($applicant->cv_path) {
            if ($action === 'archive') {
                $this->archiveCv($applicant);
            } else {
                try {
                    \Illuminate\Support\Facades\Storage::disk('local')->delete($applicant->cv_path);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::channel('daily_log')
                        ->warning('CV delete on reject failed: ' . $e->getMessage());
                }
            }
        }

        // Remove the vector from Qdrant so it stops appearing in rankings.
        try {
            app(\App\Services\Matching\MatchingService::class)->forget($applicant);
        } catch (\Throwable $e) {
            // Non-fatal.
        }

        // On archive the CV still exists (cold) → cv_purged_at stays null.
        $isArchive = $action === 'archive';
        $applicant->forceFill([
            'stage'        => Applicant::STAGE_REJECTED,
            'rejected_at'  => now(),
            'cv_purged_at' => $isArchive ? null : now(),
            // On delete the file is gone → clear the path. On archive keep the
            // new cold path already set by archiveCv().
            'cv_path'      => $isArchive ? $applicant->cv_path : null,
        ])->save();

        $this->logStage($applicant, $fromStage, Applicant::STAGE_REJECTED, $isArchive ? 'Ditolak (CV diarsip).' : 'Ditolak.');

        return $applicant;
    }

    /**
     * M18-6 — Move a CV to cold storage. Target disk follows config
     * (`recruitment_archive_disk`); when blank it uses the active StorageManager
     * provider. Never hardcodes a disk. Updates cv_path to the cold location.
     */
    private function archiveCv(Applicant $applicant): void
    {
        try {
            $local = \Illuminate\Support\Facades\Storage::disk('local');
            if (! $local->exists($applicant->cv_path)) {
                return;
            }

            $coldPath = 'cold/' . $applicant->cv_path;
            $target = $this->archiveDisk();
            $target->put($coldPath, $local->get($applicant->cv_path));

            // Remove the hot copy; keep the cold one referenced by cv_path.
            $local->delete($applicant->cv_path);
            $applicant->cv_path = $coldPath;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::channel('daily_log')
                ->warning('CV archive failed: ' . $e->getMessage());
        }
    }

    /**
     * Resolve the archive disk from config. Blank → active StorageManager
     * provider. A named disk → that disk. No hardcoded disk name.
     */
    private function archiveDisk(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        $name = trim((string) (setting('recruitment_archive_disk') ?: ''));

        if ($name !== '') {
            return \Illuminate\Support\Facades\Storage::disk($name);
        }

        return app(\App\Services\StorageManager::class)->disk();
    }

    /**
     * M18-2 — Record a pipeline transition for the timeline/audit trail. Actor is
     * the current backpack user when available (null for cron/automation).
     */
    private function logStage(Applicant $applicant, ?string $from, string $to, ?string $note = null): void
    {
        // Skip no-op transitions.
        if ($from === $to) {
            return;
        }

        ApplicantStageLog::create([
            'applicant_id' => $applicant->id,
            'from_stage'   => $from,
            'to_stage'     => $to,
            'actor_id'     => function_exists('backpack_user') ? backpack_user()?->id : null,
            'note'         => $note,
        ]);
    }

    /**
     * Applicants without an email still need a unique, non-colliding login
     * placeholder so the account can be created; HR corrects it during onboarding.
     */
    private function placeholderEmail(Applicant $applicant): string
    {
        return 'pelamar' . $applicant->id . '.' . Str::random(6) . '@recruit.local';
    }
}
