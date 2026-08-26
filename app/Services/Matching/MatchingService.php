<?php

namespace App\Services\Matching;

use App\Models\Applicant;
use App\Models\JobOpening;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * M17-4 — Stage 1 (shortlist) orchestration.
 *
 * Pipeline:
 *   ingest($application) : embed CV text → upsert into Qdrant (payload keyed by opening).
 *   shortlist($opening)  : embed the opening's criteria → search Qdrant → write vector_score.
 *
 * Everything degrades gracefully: if embedding or Qdrant is unavailable, scores
 * stay null and the applicant list falls back to manual ordering (by date).
 * That keeps hiring unblocked even when the AI stack is down.
 */
class MatchingService
{
    public function __construct(
        private EmbeddingManager $embeddings,
        private QdrantService $qdrant,
        private LlmScoringManager $llm,
    ) {}

    /**
     * Collection name is versioned by provider+model so switching providers
     * (and thus vector dimensions) never mixes incompatible vectors.
     */
    public function collectionName(): string
    {
        $slug = Str::slug($this->embeddings->provider() . '-' . $this->embeddings->model(), '_');
        return 'applications_' . $slug;
    }

    /** True when both embedding + Qdrant appear usable. */
    public function isAvailable(): bool
    {
        return $this->qdrant->isUp();
    }

    /**
     * Ingest one application into the vector store (embed its CV text).
     * Returns true if the vector was stored.
     */
    public function ingest(Applicant $application): bool
    {
        $text = $application->cv_text;
        if (! $text) {
            return false;
        }

        $vector = $this->embeddings->embed($text);
        if ($vector === null) {
            return false;
        }

        $collection = $this->collectionName();
        if (! $this->qdrant->ensureCollection($collection, count($vector))) {
            return false;
        }

        return $this->qdrant->upsert($collection, $application->id, $vector, [
            'application_id' => $application->id,
            'job_opening_id' => $application->job_opening_id,
            'candidate_id'   => $application->candidate_id,
            'stage'          => $application->stage,
        ]);
    }

    /**
     * Shortlist applicants for an opening: embed the opening's criteria, search
     * Qdrant for the nearest CVs, and write vector_score (0..100) back to each
     * application. Returns the number of applications scored.
     */
    public function shortlist(JobOpening $opening, ?int $limit = null): int
    {
        $limit ??= (int) (setting('recruitment_shortlist_size') ?: 30);

        $criteria = $this->openingCriteriaText($opening);
        if (! $criteria) {
            return 0;
        }

        $vector = $this->embeddings->embed($criteria);
        if ($vector === null) {
            return 0;
        }

        $collection = $this->collectionName();
        if (! $this->qdrant->ensureCollection($collection, count($vector))) {
            return 0;
        }

        $hits = $this->qdrant->search($collection, $vector, $limit, [
            'job_opening_id' => $opening->id,
        ]);

        $scored = 0;
        foreach ($hits as $hit) {
            $appId = $hit['payload']['application_id'] ?? $hit['id'] ?? null;
            if (! $appId) {
                continue;
            }

            // Cosine similarity in Qdrant is roughly [-1, 1]; map to 0..100.
            $score = max(0, min(100, round(((float) ($hit['score'] ?? 0)) * 100, 2)));

            Applicant::where('id', $appId)->update(['vector_score' => $score]);
            $scored++;
        }

        $opening->forceFill(['vector_synced_at' => now()])->saveQuietly();

        return $scored;
    }

    /** Remove an application's vector (used on reject/purge in M17-5). */
    public function forget(Applicant $application): void
    {
        try {
            $this->qdrant->delete($this->collectionName(), $application->id);
        } catch (\Throwable $e) {
            Log::channel('daily_log')->warning('Qdrant forget failed: ' . $e->getMessage());
        }
    }

    /**
     * Stage 2 — score ONE application against its opening's rubric via LLM.
     * Writes ai_score + ai_reasoning + ai_model + ai_scored_at. Returns the
     * score, or null if scoring was unavailable (caller keeps vector_score).
     */
    public function aiScore(Applicant $application): ?float
    {
        $opening = $application->jobOpening;
        if (! $opening || ! $application->cv_text) {
            return null;
        }

        $result = $this->llm->scoreCandidate(
            (string) $opening->scoring_prompt,
            (string) $application->cv_text,
            $this->openingCriteriaText($opening)
        );

        if ($result === null) {
            return null;
        }

        $application->forceFill([
            'ai_score'     => $result['score'],
            'ai_reasoning' => [
                'summary'  => $result['summary'],
                'criteria' => $result['criteria'],
            ],
            'ai_model'     => $result['model'],
            'ai_scored_at' => now(),
        ])->saveQuietly();

        return $result['score'];
    }

    /**
     * Full ranking for an opening: shortlist via Qdrant, then LLM-score the
     * shortlisted applications. Returns [shortlisted, ai_scored] counts.
     *
     * Degrades: if embeddings/Qdrant are down, falls back to LLM-scoring the
     * most recent applicants directly (still rubric-based, just no vector
     * pre-filter). If the LLM is down too, only vector_score (if any) remains.
     */
    public function rankOpening(JobOpening $opening, ?int $limit = null): array
    {
        $limit ??= (int) (setting('recruitment_shortlist_size') ?: 30);

        $shortlisted = $this->shortlist($opening, $limit);

        // Pick which applications to LLM-score.
        $query = Applicant::where('job_opening_id', $opening->id)
            ->whereNull('rejected_at')
            ->whereNotNull('cv_text');

        if ($shortlisted > 0) {
            // Score the vector-shortlisted ones, best first.
            $apps = (clone $query)->whereNotNull('vector_score')
                ->orderByDesc('vector_score')->limit($limit)->get();
        } else {
            // Fallback: no vectors available → score most recent applicants.
            $apps = (clone $query)->latest()->limit($limit)->get();
        }

        $aiScored = 0;
        foreach ($apps as $app) {
            if ($this->aiScore($app) !== null) {
                $aiScored++;
            }
        }

        return ['shortlisted' => $shortlisted, 'ai_scored' => $aiScored];
    }

    /** Build a single text blob describing what the opening wants. */
    private function openingCriteriaText(JobOpening $opening): string
    {
        $parts = array_filter([
            $opening->title,
            $opening->description,
            is_array($opening->required_skills) ? implode(', ', $opening->required_skills) : null,
            $opening->min_experience_years ? "Pengalaman minimal {$opening->min_experience_years} tahun" : null,
            $opening->education_min ? "Pendidikan minimal {$opening->education_min}" : null,
            $opening->scoring_prompt,
        ]);

        return trim(implode('. ', $parts));
    }
}
