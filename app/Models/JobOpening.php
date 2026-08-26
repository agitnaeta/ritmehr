<?php

namespace App\Models;

use App\Traits\Auditable;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobOpening extends Model
{
    use CrudTrait, HasFactory, Auditable;

    protected $table = 'job_openings';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_OPEN = 'open';
    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'title', 'code', 'slug', 'department_id', 'position_id', 'branch_id',
        'description', 'required_skills', 'min_experience_years', 'education_min',
        'scoring_prompt', 'vector_synced_at',
        'vacancies', 'salary_min', 'salary_max',
        'status', 'is_published', 'published_at', 'opened_at', 'closed_at',
    ];

    protected $casts = [
        'vacancies'            => 'integer',
        'salary_min'           => 'integer',
        'salary_max'           => 'integer',
        'required_skills'      => 'array',
        'min_experience_years' => 'integer',
        'is_published'         => 'boolean',
        'published_at'         => 'datetime',
        'vector_synced_at'     => 'datetime',
        'opened_at'            => 'date',
        'closed_at'            => 'date',
    ];

    // ── Relationships ──────────────────────────────────────

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function position()
    {
        return $this->belongsTo(Position::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function applicants()
    {
        return $this->hasMany(Applicant::class);
    }

    // ── Scopes ─────────────────────────────────────────────

    public function scopeOpen($query)
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    /** Openings visible on the public careers page. */
    public function scopePublished($query)
    {
        return $query->where('is_published', true)
                     ->where('status', self::STATUS_OPEN);
    }

    // ── Boot: auto-slug ────────────────────────────────────

    protected static function booted(): void
    {
        static::saving(function (JobOpening $opening) {
            if (empty($opening->slug) && ! empty($opening->title)) {
                $base = \Illuminate\Support\Str::slug($opening->title);
                $slug = $base;
                $i = 2;
                while (static::where('slug', $slug)->where('id', '!=', $opening->id)->exists()) {
                    $slug = $base . '-' . $i++;
                }
                $opening->slug = $slug;
            }
        });
    }

    /** Can candidates still apply? (published, open, slots remain) */
    public function isOpenForApplication(): bool
    {
        return $this->is_published
            && $this->status === self::STATUS_OPEN
            && $this->remainingVacancies() > 0;
    }

    /**
     * Accept required_skills as either an array or a comma/newline-separated
     * string (from the admin textarea) → normalise to a clean array.
     */
    public function setRequiredSkillsAttribute($value): void
    {
        if (is_string($value)) {
            $value = array_values(array_filter(array_map(
                'trim',
                preg_split('/[,\n]+/', $value) ?: []
            )));
        }

        $this->attributes['required_skills'] = $value !== null ? json_encode($value) : null;
    }

    // ── Helpers ────────────────────────────────────────────

    /** How many applicants have been hired against this opening. */
    public function hiredCount(): int
    {
        return $this->applicants()->where('stage', Applicant::STAGE_HIRED)->count();
    }

    /** Remaining slots (never negative). */
    public function remainingVacancies(): int
    {
        return max(0, (int) $this->vacancies - $this->hiredCount());
    }

    public function salaryRangeLabel(): string
    {
        if (! $this->salary_min && ! $this->salary_max) {
            return '—';
        }
        if ($this->salary_min && $this->salary_max) {
            return money($this->salary_min) . ' – ' . money($this->salary_max);
        }

        return money($this->salary_min ?: $this->salary_max);
    }
}
