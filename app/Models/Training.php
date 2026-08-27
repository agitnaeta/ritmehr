<?php

namespace App\Models;

use App\Traits\Auditable;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * M11 — a training course: materials + one quiz + enrolled participants.
 */
class Training extends Model
{
    use CrudTrait, HasFactory, Auditable;

    protected $table = 'trainings';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_ARCHIVED = 'archived';

    public const STATUS_LABELS = [
        self::STATUS_DRAFT     => 'Draft',
        self::STATUS_PUBLISHED => 'Terbit',
        self::STATUS_ARCHIVED  => 'Diarsipkan',
    ];

    protected $fillable = [
        'title', 'description', 'trainer_id', 'category',
        'passing_score', 'max_attempts', 'status', 'archived_at',
    ];

    protected $casts = [
        'passing_score' => 'integer',
        'max_attempts'  => 'integer',
        'archived_at'   => 'datetime',
    ];

    // ── Relationships ──────────────────────────────────────

    public function trainer()
    {
        return $this->belongsTo(User::class, 'trainer_id');
    }

    public function materials()
    {
        return $this->hasMany(TrainingMaterial::class)->orderBy('position');
    }

    public function questions()
    {
        return $this->hasMany(TrainingQuestion::class)->orderBy('position');
    }

    public function enrollments()
    {
        return $this->hasMany(TrainingEnrollment::class);
    }

    // ── Scopes ─────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', '!=', self::STATUS_ARCHIVED);
    }

    public function scopePublished($query)
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    // ── Helpers ────────────────────────────────────────────

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function isArchived(): bool
    {
        return $this->status === self::STATUS_ARCHIVED;
    }
}
