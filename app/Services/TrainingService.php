<?php

namespace App\Services;

use App\Models\Training;
use App\Models\TrainingEnrollment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * M11 — Training operations that must be correct rather than merely present:
 *  - grade(): auto-score a quiz submission → passed | failed | locked, issue cert;
 *  - enroll(): add participants idempotently;
 *  - reorderMaterial()/reorderQuestion(): keep positions stable.
 *
 * Grading rule (locked with Capt):
 *   score = correct × (100 / total_questions), rounded.
 *   score ≥ training.passing_score → passed (+ certificate).
 *   else → failed; once attempts reach max_attempts and still not passed → locked.
 */
class TrainingService
{
    public function __construct(private readonly NotificationService $notifications)
    {
    }

    /**
     * Grade a quiz submission for an enrollment.
     *
     * @param  array<int,string>  $answers  question_id => chosen option (a|b|c|d)
     * @return TrainingEnrollment fresh enrollment with score + status
     *
     * @throws \DomainException when the enrollment is already locked/passed or attempts are exhausted
     */
    public function grade(TrainingEnrollment $enrollment, array $answers): TrainingEnrollment
    {
        $training = $enrollment->training;

        if ($enrollment->status === TrainingEnrollment::STATUS_PASSED) {
            throw new \DomainException('Peserta sudah lulus pelatihan ini.');
        }
        if ($enrollment->status === TrainingEnrollment::STATUS_LOCKED) {
            throw new \DomainException('Kesempatan mengerjakan sudah habis. Hubungi HR untuk reset.');
        }

        $questions = $training->questions()->get();
        $total = $questions->count();
        if ($total === 0) {
            throw new \DomainException('Pelatihan belum punya soal latihan.');
        }

        $correct = 0;
        foreach ($questions as $q) {
            $picked = $answers[$q->id] ?? null;
            if ($q->isCorrect($picked)) {
                $correct++;
            }
        }

        $score = (int) round($correct * (100 / $total));
        $passed = $score >= $training->passing_score;

        return DB::transaction(function () use ($enrollment, $training, $score, $passed) {
            $enrollment->score = $score;
            $enrollment->attempts = $enrollment->attempts + 1;

            if ($passed) {
                $enrollment->status = TrainingEnrollment::STATUS_PASSED;
                $enrollment->passed_at = now();
                if (! $enrollment->certificate_no) {
                    $enrollment->certificate_no = $this->makeCertificateNo($enrollment);
                    $enrollment->certificate_issued_at = now();
                }
            } elseif ($enrollment->attempts >= $training->max_attempts) {
                $enrollment->status = TrainingEnrollment::STATUS_LOCKED;
            } else {
                $enrollment->status = TrainingEnrollment::STATUS_FAILED;
            }

            $enrollment->save();

            if ($passed) {
                $this->notifyPassed($enrollment);
            }

            return $enrollment->fresh();
        });
    }

    /**
     * Enroll users into a training (idempotent — existing enrollments are kept).
     *
     * @param  array<int,int>  $userIds
     * @return int number of NEW enrollments created
     */
    public function enroll(Training $training, array $userIds): int
    {
        $created = 0;
        foreach (array_unique($userIds) as $userId) {
            $enrollment = TrainingEnrollment::firstOrCreate(
                ['training_id' => $training->id, 'user_id' => (int) $userId],
                ['status' => TrainingEnrollment::STATUS_ENROLLED]
            );
            if ($enrollment->wasRecentlyCreated) {
                $created++;
                $this->notifyAssigned($enrollment);
            }
        }

        return $created;
    }

    /** HR/trainer resets a locked (or any) enrollment so the participant can retry. */
    public function resetAttempts(TrainingEnrollment $enrollment): TrainingEnrollment
    {
        $enrollment->update([
            'status'   => TrainingEnrollment::STATUS_ENROLLED,
            'score'    => null,
            'attempts' => 0,
        ]);

        return $enrollment->fresh();
    }

    /** Archive / restore a training (data is kept, only visibility changes). */
    public function archive(Training $training): void
    {
        $training->update([
            'status'      => Training::STATUS_ARCHIVED,
            'archived_at' => now(),
        ]);
    }

    public function restore(Training $training): void
    {
        $training->update([
            'status'      => Training::STATUS_DRAFT,
            'archived_at' => null,
        ]);
    }

    private function makeCertificateNo(TrainingEnrollment $enrollment): string
    {
        return sprintf(
            'CERT/%s/%d/%s',
            now()->format('Y'),
            $enrollment->training_id,
            strtoupper(Str::random(6))
        );
    }

    private function notifyPassed(TrainingEnrollment $enrollment): void
    {
        $user = $enrollment->user ?: User::find($enrollment->user_id);
        if ($user) {
            $this->notifications->notify($user, 'training_passed', [
                'title'   => $enrollment->training->title,
                'score'   => $enrollment->score,
                'message' => "Selamat! Anda LULUS pelatihan \"{$enrollment->training->title}\" (skor {$enrollment->score}).",
            ]);
        }
    }

    private function notifyAssigned(TrainingEnrollment $enrollment): void
    {
        $user = $enrollment->user ?: User::find($enrollment->user_id);
        if ($user) {
            $this->notifications->notify($user, 'training_assigned', [
                'title'   => $enrollment->training->title,
                'message' => "Anda ditugaskan mengikuti pelatihan \"{$enrollment->training->title}\".",
            ]);
        }
    }
}
