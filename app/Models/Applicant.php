<?php

namespace App\Models;

use App\Traits\Auditable;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Applicant extends Model
{
    use CrudTrait, HasFactory, Auditable;

    protected $table = 'applicants';

    public const STAGE_APPLIED = 'applied';
    public const STAGE_SCREENING = 'screening';
    public const STAGE_INTERVIEW = 'interview';
    public const STAGE_OFFER = 'offer';
    public const STAGE_HIRED = 'hired';
    public const STAGE_REJECTED = 'rejected';

    /** Ordered pipeline stages shown on the kanban board (rejected is off-board). */
    public const PIPELINE = [
        self::STAGE_APPLIED,
        self::STAGE_SCREENING,
        self::STAGE_INTERVIEW,
        self::STAGE_OFFER,
        self::STAGE_HIRED,
    ];

    public const STAGE_LABELS = [
        self::STAGE_APPLIED   => 'Melamar',
        self::STAGE_SCREENING => 'Seleksi Berkas',
        self::STAGE_INTERVIEW => 'Wawancara',
        self::STAGE_OFFER     => 'Penawaran',
        self::STAGE_HIRED     => 'Diterima',
        self::STAGE_REJECTED  => 'Ditolak',
    ];

    protected $fillable = [
        'job_opening_id', 'candidate_id', 'name', 'email', 'phone', 'stage', 'notes',
        'cv_path', 'cv_text', 'expected_salary', 'hired_user_id', 'hired_at',
        'vector_score', 'ai_score', 'ai_reasoning', 'ai_model', 'ai_scored_at',
        'rejected_at', 'cv_purged_at',
    ];

    protected $casts = [
        'expected_salary' => 'integer',
        'hired_at'        => 'datetime',
        'vector_score'    => 'float',
        'ai_score'        => 'float',
        'ai_reasoning'    => 'array',
        'ai_scored_at'    => 'datetime',
        'rejected_at'     => 'datetime',
        'cv_purged_at'    => 'datetime',
    ];

    // ── Relationships ──────────────────────────────────────

    public function jobOpening()
    {
        return $this->belongsTo(JobOpening::class);
    }

    public function candidate()
    {
        return $this->belongsTo(Candidate::class);
    }

    public function interviews()
    {
        return $this->hasMany(Interview::class);
    }

    /** M18-2 — pipeline transition history (timeline), newest first. */
    public function stageLogs()
    {
        return $this->hasMany(ApplicantStageLog::class)->latest();
    }

    public function hiredUser()
    {
        return $this->belongsTo(User::class, 'hired_user_id');
    }

    // ── Scopes ─────────────────────────────────────────────

    public function scopeInStage($query, string $stage)
    {
        return $query->where('stage', $stage);
    }

    public function scopeActive($query)
    {
        return $query->whereNotIn('stage', [self::STAGE_REJECTED]);
    }

    // ── Helpers ────────────────────────────────────────────

    public function stageLabel(): string
    {
        return self::STAGE_LABELS[$this->stage] ?? $this->stage;
    }

    public function isHired(): bool
    {
        return $this->stage === self::STAGE_HIRED;
    }
}
