<?php

namespace App\Models;

use App\Traits\Auditable;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveBalance extends Model
{
    use CrudTrait, HasFactory, Auditable;

    protected $table = 'leave_balances';

    /**
     * `remaining` is a generated column — writing to it is a database error,
     * so it must stay out of $fillable.
     */
    protected $fillable = [
        'user_id',
        'leave_type_id',
        'year',
        'quota',
        'used',
        'carry_over',
    ];

    protected $casts = [
        'year'       => 'integer',
        'quota'      => 'integer',
        'used'       => 'integer',
        'carry_over' => 'integer',
        'remaining'  => 'integer',
    ];

    // ── Relationships ──────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class);
    }

    // ── Scopes ─────────────────────────────────────────────

    public function scopeForYear($query, int $year)
    {
        return $query->where('year', $year);
    }

    // ── Helpers ────────────────────────────────────────────

    public function totalEntitlement(): int
    {
        return (int) $this->quota + (int) $this->carry_over;
    }

    /**
     * Read the generated column, falling back to computing it in PHP for
     * instances that were never reloaded from the database.
     */
    public function remainingDays(): int
    {
        return $this->remaining ?? ($this->totalEntitlement() - (int) $this->used);
    }

    public function canCover(float $days): bool
    {
        return $this->remainingDays() >= $days;
    }
}
