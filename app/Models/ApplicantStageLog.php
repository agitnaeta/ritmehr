<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * M18-2 — One row per applicant pipeline transition (audit trail / timeline).
 */
class ApplicantStageLog extends Model
{
    protected $fillable = [
        'applicant_id', 'from_stage', 'to_stage', 'actor_id', 'note',
    ];

    public function applicant()
    {
        return $this->belongsTo(Applicant::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /** Human label for the destination stage. */
    public function toStageLabel(): string
    {
        return Applicant::STAGE_LABELS[$this->to_stage] ?? $this->to_stage;
    }

    /** Human label for the origin stage (or em dash when first entry). */
    public function fromStageLabel(): string
    {
        return $this->from_stage
            ? (Applicant::STAGE_LABELS[$this->from_stage] ?? $this->from_stage)
            : '—';
    }
}
