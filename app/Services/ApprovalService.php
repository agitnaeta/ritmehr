<?php

namespace App\Services;

use App\Models\Approval;
use App\Models\ApprovalAction;
use App\Models\ApprovalFlow;
use App\Models\ApprovalFlowStep;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Drives multi-step approval chains for any model tagged with HasApproval.
 *
 * A flow is a list of ordered steps; each step names an approver by role, by
 * "the requester's manager", or by a specific user. An approval walks the
 * steps one at a time — approving the last step resolves the whole request.
 */
class ApprovalService
{
    /**
     * Open an approval chain for $approvable using the active flow for $module.
     *
     * @throws \RuntimeException when no usable flow is configured
     * @throws \DomainException  when the record already has an approval
     */
    public function submitForApproval(Model $approvable, User $requester, ?string $module = null): Approval
    {
        $module ??= $this->moduleFor($approvable);
        $flow = ApprovalFlow::forModuleOrFail($module);

        return DB::transaction(function () use ($approvable, $requester, $flow) {
            $existing = Approval::where('approvable_type', $approvable->getMorphClass())
                ->where('approvable_id', $approvable->getKey())
                ->lockForUpdate()
                ->first();

            if ($existing) {
                throw new \DomainException('This record already has an approval request.');
            }

            $approval = Approval::create([
                'approvable_type'  => $approvable->getMorphClass(),
                'approvable_id'    => $approvable->getKey(),
                'approval_flow_id' => $flow->id,
                'current_step'     => 1,
                'status'           => Approval::STATUS_PENDING,
                'requested_by'     => $requester->id,
            ]);

            $this->pingNextApprovers($approval);

            return $approval;
        });
    }

    /**
     * Let the current step's approvers know something is waiting on them.
     * Never allowed to break the surrounding operation.
     */
    private function pingNextApprovers(Approval $approval): void
    {
        try {
            $approval->loadMissing(['approvalFlow.flowSteps', 'requester']);

            app(NotificationService::class)->notifyMany(
                $this->getNextApprovers($approval),
                Notification::APPROVAL_PENDING,
                [
                    'approval_id' => $approval->id,
                    'module'      => $approval->approvalFlow?->module,
                    'requester'   => $approval->requester?->name,
                ]
            );
        } catch (\Throwable $e) {
            Log::error('[Approval] failed to notify approvers', [
                'approval_id' => $approval->id,
                'message'     => $e->getMessage(),
            ]);
        }
    }

    /**
     * Record an approval for the current step and advance, or finalise if this
     * was the last step.
     *
     * @throws \DomainException when $approver may not act on this step
     */
    public function approve(Approval $approval, User $approver, ?string $notes = null): Approval
    {
        return DB::transaction(function () use ($approval, $approver, $notes) {
            $approval = $this->lockAndAuthorise($approval, $approver);

            $actedStep = $approval->current_step;

            $this->recordAction($approval, $actedStep, ApprovalAction::ACTION_APPROVE, $approver, $notes);

            $isFinalStep = $actedStep >= $approval->approvalFlow->totalSteps();

            if ($isFinalStep) {
                $approval->status = Approval::STATUS_APPROVED;
            } else {
                $approval->current_step = $actedStep + 1;
            }

            $approval->save();

            if ($isFinalStep) {
                $this->notifyApprovable($approval, 'onApprovalApproved');
            } else {
                // Chain moved on — tell whoever owns the new current step.
                $this->pingNextApprovers($approval);
            }

            return $approval;
        });
    }

    /**
     * Reject at the current step. A rejection ends the chain immediately —
     * there is no partial rejection.
     *
     * @throws \DomainException when $approver may not act, or reason is blank
     */
    public function reject(Approval $approval, User $approver, string $reason): Approval
    {
        if (trim($reason) === '') {
            throw new \DomainException('A rejection reason is required.');
        }

        return DB::transaction(function () use ($approval, $approver, $reason) {
            $approval = $this->lockAndAuthorise($approval, $approver);

            $this->recordAction(
                $approval,
                $approval->current_step,
                ApprovalAction::ACTION_REJECT,
                $approver,
                $reason
            );

            $approval->status = Approval::STATUS_REJECTED;
            $approval->save();

            $this->notifyApprovable($approval, 'onApprovalRejected');

            return $approval;
        });
    }

    /**
     * Withdraw a still-pending request. Only the original requester may cancel.
     *
     * @throws \DomainException when not pending, or $user is not the requester
     */
    public function cancel(Approval $approval, User $user): Approval
    {
        return DB::transaction(function () use ($approval, $user) {
            $approval = Approval::whereKey($approval->getKey())->lockForUpdate()->firstOrFail();

            if (! $approval->isPending()) {
                throw new \DomainException('Only a pending request can be cancelled.');
            }

            if ((int) $approval->requested_by !== (int) $user->id) {
                throw new \DomainException('Only the requester can cancel this request.');
            }

            $approval->status = Approval::STATUS_CANCELLED;
            $approval->save();

            $this->notifyApprovable($approval, 'onApprovalCancelled');

            return $approval;
        });
    }

    /**
     * Who is expected to act next. Returns a collection because a role-based
     * step can resolve to several people.
     */
    public function getNextApprovers(Approval $approval): Collection
    {
        if (! $approval->isPending()) {
            return User::query()->whereRaw('1 = 0')->get();
        }

        $step = $approval->currentFlowStep();

        if (! $step) {
            return User::query()->whereRaw('1 = 0')->get();
        }

        return match ($step->approver_type) {
            ApprovalFlowStep::TYPE_ROLE => $step->approver_role_id
                ? User::whereHas('roles', fn ($q) => $q->where('id', $step->approver_role_id))->get()
                : User::query()->whereRaw('1 = 0')->get(),

            ApprovalFlowStep::TYPE_MANAGER => User::whereKey($approval->requester->manager_id)->get(),

            ApprovalFlowStep::TYPE_SPECIFIC_USER => User::whereKey($step->approver_user_id)->get(),

            default => User::query()->whereRaw('1 = 0')->get(),
        };
    }

    /**
     * Convenience wrapper for callers that just want one addressee.
     */
    public function getNextApprover(Approval $approval): ?User
    {
        return $this->getNextApprovers($approval)->first();
    }

    /**
     * Every pending approval $user is currently able to act on.
     *
     * Role and specific-user steps are resolved with a query; manager steps
     * need the requester's manager_id, so they are filtered in PHP.
     */
    public function getPendingForUser(User $user): Collection
    {
        $roleIds = $user->roles->pluck('id')->all();

        return Approval::query()
            ->pending()
            ->with(['approvalFlow.flowSteps', 'requester', 'approvable'])
            ->whereHas('approvalFlow.flowSteps', function ($q) use ($user, $roleIds) {
                $q->whereColumn('approval_flow_steps.step_order', 'approvals.current_step')
                  ->where(function ($inner) use ($user, $roleIds) {
                      $inner->where('approver_type', ApprovalFlowStep::TYPE_MANAGER)
                            ->orWhere(function ($q2) use ($roleIds) {
                                $q2->where('approver_type', ApprovalFlowStep::TYPE_ROLE)
                                   ->whereIn('approver_role_id', $roleIds ?: [0]);
                            })
                            ->orWhere(function ($q2) use ($user) {
                                $q2->where('approver_type', ApprovalFlowStep::TYPE_SPECIFIC_USER)
                                   ->where('approver_user_id', $user->id);
                            });
                  });
            })
            ->get()
            ->filter(fn (Approval $a) => $a->canBeActedOnBy($user))
            ->values();
    }

    // ── Internals ──────────────────────────────────────────

    /**
     * Re-read the approval under a row lock and confirm $approver may act on
     * the step that is current *at lock time* — not the one the caller saw.
     */
    private function lockAndAuthorise(Approval $approval, User $approver): Approval
    {
        $fresh = Approval::whereKey($approval->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        $fresh->load(['approvalFlow.flowSteps', 'requester']);
        $approver->loadMissing('roles');

        if (! $fresh->isPending()) {
            throw new \DomainException('This request is no longer pending.');
        }

        if (! $fresh->canBeActedOnBy($approver)) {
            throw new \DomainException('You are not the designated approver for this step.');
        }

        return $fresh;
    }

    private function recordAction(
        Approval $approval,
        int $stepOrder,
        string $action,
        User $actor,
        ?string $notes
    ): ApprovalAction {
        return ApprovalAction::create([
            'approval_id' => $approval->id,
            'step_order'  => $stepOrder,
            'action'      => $action,
            'acted_by'    => $actor->id,
            'notes'       => $notes,
            'acted_at'    => now(),
        ]);
    }

    /**
     * Let the underlying record react to the outcome, if it wants to.
     */
    private function notifyApprovable(Approval $approval, string $hook): void
    {
        $approvable = $approval->approvable;

        if ($approvable && method_exists($approvable, $hook)) {
            $approvable->{$hook}($approval);
        }
    }

    /**
     * Models may declare their own module name; otherwise derive it from the
     * class name (LeaveRequest -> leave_request).
     */
    private function moduleFor(Model $approvable): string
    {
        if (method_exists($approvable, 'approvalModule')) {
            return $approvable->approvalModule();
        }

        return str(class_basename($approvable))->snake()->toString();
    }
}
