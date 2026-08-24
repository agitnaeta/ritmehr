<?php

namespace App\Models;

use App\Traits\Auditable;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveType extends Model
{
    use CrudTrait, HasFactory, Auditable;

    protected $table = 'leave_types';

    protected $fillable = [
        'name',
        'code',
        'is_paid',
        'default_quota',
        'max_consecutive_days',
        'requires_attachment',
        'is_active',
        'color',
    ];

    protected $casts = [
        'is_paid'              => 'boolean',
        'requires_attachment'  => 'boolean',
        'is_active'            => 'boolean',
        'default_quota'        => 'integer',
        'max_consecutive_days' => 'integer',
    ];

    // ── Relationships ──────────────────────────────────────

    public function balances()
    {
        return $this->hasMany(LeaveBalance::class);
    }

    public function requests()
    {
        return $this->hasMany(LeaveRequest::class);
    }

    // ── Scopes ─────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // ── Helpers ────────────────────────────────────────────

    /**
     * Types with a null quota (typically sick leave) are not balance-tracked.
     */
    public function hasQuota(): bool
    {
        return $this->default_quota !== null;
    }

    public function identity(): string
    {
        return $this->name . ($this->is_paid ? '' : ' (tidak dibayar)');
    }
}
