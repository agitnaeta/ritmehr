<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\HasApproval;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveRequest extends Model
{
    use CrudTrait, HasFactory, Auditable, HasApproval;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'leave_requests';

    protected $fillable = [
        'user_id',
        'leave_type_id',
        'start_date',
        'end_date',
        'total_days',
        'reason',
        'attachment',
        'status',
        'approved_by',
        'approved_at',
        'rejection_reason',
    ];

    protected $casts = [
        'start_date'  => 'date',
        'end_date'    => 'date',
        'total_days'  => 'float',
        'approved_at' => 'datetime',
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

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function dates()
    {
        return $this->hasMany(LeaveRequestDate::class)->orderBy('date');
    }

    // ── Approval integration ───────────────────────────────

    public function approvalModule(): string
    {
        return 'leave';
    }

    /**
     * Final approval reached — commit the leave and spend the balance.
     */
    public function onApprovalApproved(Approval $approval): void
    {
        app(\App\Services\LeaveService::class)->finaliseApproval($this, $approval);
    }

    public function onApprovalRejected(Approval $approval): void
    {
        // reorder() clears the relation's own step_order sort — without it
        // the "latest" action would still resolve to step 1.
        $lastAction = $approval->actions()
            ->reorder()
            ->orderByDesc('acted_at')
            ->orderByDesc('step_order')
            ->first();

        $this->forceFill([
            'status'           => self::STATUS_REJECTED,
            'rejection_reason' => $lastAction?->notes,
        ])->save();

        app(\App\Services\LeaveService::class)->notifyOutcome($this, false);
    }

    public function onApprovalCancelled(Approval $approval): void
    {
        $this->forceFill(['status' => self::STATUS_CANCELLED])->save();
    }

    // ── Scopes ─────────────────────────────────────────────

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Requests that overlap the given range in any way.
     */
    public function scopeOverlapping($query, $start, $end)
    {
        return $query->where('start_date', '<=', $end)
                     ->where('end_date', '>=', $start);
    }

    /**
     * Statuses that block a second request on the same dates.
     */
    public function scopeBlocking($query)
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_APPROVED]);
    }

    // ── Helpers ────────────────────────────────────────────

    public function isApprovedLeave(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_PENDING], true);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT     => 'Draft',
            self::STATUS_PENDING   => 'Menunggu',
            self::STATUS_APPROVED  => 'Disetujui',
            self::STATUS_REJECTED  => 'Ditolak',
            self::STATUS_CANCELLED => 'Dibatalkan',
            default                => (string) $this->status,
        };
    }

    public function periodLabel(): string
    {
        $start = $this->start_date instanceof Carbon ? $this->start_date : Carbon::parse($this->start_date);
        $end = $this->end_date instanceof Carbon ? $this->end_date : Carbon::parse($this->end_date);

        if ($start->isSameDay($end)) {
            return $start->format('d/m/Y');
        }

        return $start->format('d/m/Y') . ' — ' . $end->format('d/m/Y');
    }
}
