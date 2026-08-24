<?php

namespace App\Traits;

use App\Models\Approval;
use App\Models\User;
use App\Services\ApprovalService;

/**
 * Attach to any model that needs to go through an approval chain.
 *
 * The model may optionally define:
 *   - approvalModule(): string        — which flow to use (defaults to snake_case class name)
 *   - onApprovalApproved(Approval)    — hook fired when the final step approves
 *   - onApprovalRejected(Approval)    — hook fired on rejection
 *   - onApprovalCancelled(Approval)   — hook fired when the requester withdraws
 */
trait HasApproval
{
    public function approval()
    {
        return $this->morphOne(Approval::class, 'approvable');
    }

    public function submitForApproval(User $requester): Approval
    {
        return app(ApprovalService::class)->submitForApproval($this, $requester);
    }

    public function isApprovalPending(): bool
    {
        return (bool) $this->approval?->isPending();
    }

    public function isApproved(): bool
    {
        return (bool) $this->approval?->isApproved();
    }

    public function isRejected(): bool
    {
        return (bool) $this->approval?->isRejected();
    }

    /**
     * True when the record has never been submitted.
     */
    public function hasNoApproval(): bool
    {
        return $this->approval === null;
    }

    public function approvalStatus(): string
    {
        return $this->approval?->status ?? 'draft';
    }
}
