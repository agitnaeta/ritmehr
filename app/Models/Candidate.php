<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;

/**
 * M17 — External job candidate. Separate auth guard ("candidate") so it can
 * never reach the admin panel or the employee portal. A candidate may apply to
 * many openings (max once each — enforced by applicants unique index).
 */
class Candidate extends Authenticatable
{
    use HasFactory, Notifiable, Auditable;

    protected $table = 'candidates';

    protected $fillable = [
        'name', 'email', 'password', 'phone', 'headline',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected $casts = [
        'password' => 'hashed',
    ];

    /** All applications this candidate has made. */
    public function applications()
    {
        return $this->hasMany(Applicant::class);
    }

    /** Has this candidate already applied to the given opening? */
    public function hasAppliedTo(int $jobOpeningId): bool
    {
        return $this->applications()->where('job_opening_id', $jobOpeningId)->exists();
    }
}
