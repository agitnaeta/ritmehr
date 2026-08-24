<?php

namespace App\Models;

use App\Traits\Auditable;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Approval extends Model
{
    use CrudTrait, HasFactory, Auditable;

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'approvals';

    protected $fillable = [
        'approvable_type',
        'approvable_id',
        'approval_flow_id',
        'current_step',
        'status',
        'requested_by',
    ];

    protected $casts = [
        'current_step' => 'integer',
    ];

    // ── Relationships ──────────────────────────────────────

    public function approvable()
    {
        return $this->morphTo();
    }

    public function approvalFlow()
    {
        return $this->belongsTo(ApprovalFlow::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function actions()
    {
        return $this->hasMany(ApprovalAction::class)->orderBy('step_order');
    }

    // ── Scopes ─────────────────────────────────────────────

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeForModule($query, string $module)
    {
        return $query->whereHas('approvalFlow', fn ($q) => $q->where('module', $module));
    }

    // ── State ──────────────────────────────────────────────

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function currentFlowStep(): ?ApprovalFlowStep
    {
        return $this->approvalFlow?->stepAt($this->current_step);
    }

    /**
     * Can $user approve/reject this approval right now?
     */
    public function canBeActedOnBy(User $user): bool
    {
        if (! $this->isPending()) {
            return false;
        }

        $step = $this->currentFlowStep();

        if (! $step) {
            return false;
        }

        return $step->isSatisfiedBy($user, $this->requester);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING   => 'Menunggu',
            self::STATUS_APPROVED  => 'Disetujui',
            self::STATUS_REJECTED  => 'Ditolak',
            self::STATUS_CANCELLED => 'Dibatalkan',
            default                => $this->status,
        };
    }
}
