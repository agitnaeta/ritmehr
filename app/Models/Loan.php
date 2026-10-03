<?php

namespace App\Models;

use App\Traits\HasApproval;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Loan extends Model
{
    use CrudTrait;
    use HasFactory;
    use HasApproval;

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'user_id', 'amount', 'date', 'status', 'reason', 'rejection_reason',
    ];

    public function user(){
        return $this->hasOne(User::class,'id','user_id');
    }

    public function getDateLabelAttribute(){
        return Carbon::createFromFormat("Y-m-d",$this->date)
            ->format('d/M/Y');
    }

    // ── Approval integration ───────────────────────────────

    public function approvalModule(): string
    {
        return 'loan';
    }

    /**
     * Final approval reached — commit the loan and book it to accounting.
     */
    public function onApprovalApproved(Approval $approval): void
    {
        app(\App\Services\LoanService::class)->finaliseApproval($this, $approval);
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

        app(\App\Services\LoanService::class)->notifyOutcome($this, false);
    }

    public function onApprovalCancelled(Approval $approval): void
    {
        $this->forceFill(['status' => self::STATUS_CANCELLED])->save();
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING   => 'Menunggu',
            self::STATUS_APPROVED  => 'Disetujui',
            self::STATUS_REJECTED  => 'Ditolak',
            self::STATUS_CANCELLED => 'Dibatalkan',
            default                => (string) $this->status,
        };
    }

    // ── Scopes ─────────────────────────────────────────────

    /**
     * Only loans that are real debt: booked, not pending/rejected/cancelled.
     */
    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }
}
