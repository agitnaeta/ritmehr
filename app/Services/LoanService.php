<?php

namespace App\Services;

use App\Models\Approval;
use App\Models\Loan;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Employee kasbon requests. A request is a pending Loan pushed through the
 * 'loan' approval flow; it only becomes debt (and is booked to accounting)
 * once the final step approves it. HR direct entry in LoanCrudController
 * bypasses this and creates approved loans straight away.
 */
class LoanService
{
    public function __construct(
        private readonly ApprovalService $approvalService,
        private readonly TransactionService $transactionService,
        private readonly NotificationService $notifications,
    ) {
    }

    /**
     * @throws \DomainException when the employee already has an open request
     * @throws \RuntimeException when no active 'loan' approval flow exists
     */
    public function requestLoan(User $user, int $amount, string $date, ?string $reason = null): Loan
    {
        if ($amount <= 0) {
            throw new \DomainException('Jumlah kasbon harus lebih dari 0.');
        }

        return DB::transaction(function () use ($user, $amount, $date, $reason) {
            // Serialise per employee so two quick submits cannot both pass
            // the "one open request" check.
            User::whereKey($user->id)->lockForUpdate()->first();

            if (Loan::pending()->where('user_id', $user->id)->exists()) {
                throw new \DomainException(
                    'Anda masih punya pengajuan kasbon yang menunggu persetujuan.'
                );
            }

            $loan = Loan::create([
                'user_id' => $user->id,
                'amount'  => $amount,
                'date'    => $date,
                'reason'  => $reason,
                'status'  => Loan::STATUS_PENDING,
            ]);

            $this->approvalService->submitForApproval($loan, $user, 'loan');

            return $loan->fresh('approval');
        });
    }

    /**
     * Withdraw a request that is still waiting. Approved loans are booked
     * debt and can only be changed by HR, not cancelled by the employee.
     *
     * @throws \DomainException
     */
    public function cancel(Loan $loan, User $actor): Loan
    {
        if ((int) $loan->user_id !== (int) $actor->id) {
            throw new \DomainException('Hanya pemohon yang bisa membatalkan pengajuan.');
        }

        if ($loan->status !== Loan::STATUS_PENDING) {
            throw new \DomainException('Hanya pengajuan yang masih menunggu yang bisa dibatalkan.');
        }

        if ($loan->approval && $loan->approval->isPending()) {
            // Fires Loan::onApprovalCancelled, which sets the status.
            $this->approvalService->cancel($loan->approval, $actor);
        }

        $loan->forceFill(['status' => Loan::STATUS_CANCELLED])->save();

        return $loan;
    }

    /**
     * Final approval reached — commit the loan and book it to accounting.
     * Locked and guarded so a double-fire cannot post KASBON twice.
     */
    public function finaliseApproval(Loan $loan, Approval $approval): void
    {
        $approved = DB::transaction(function () use ($loan) {
            $fresh = Loan::whereKey($loan->getKey())->lockForUpdate()->firstOrFail();

            if ($fresh->status === Loan::STATUS_APPROVED) {
                return null;
            }

            $fresh->forceFill(['status' => Loan::STATUS_APPROVED])->save();
            $this->transactionService->recordLoanACC($fresh);

            return $fresh;
        });

        if ($approved) {
            $loan->setRawAttributes($approved->fresh()->getAttributes(), true);
            $this->notifyOutcome($loan, true);
        }
    }

    public function notifyOutcome(Loan $loan, bool $approved): void
    {
        try {
            $user = User::find($loan->user_id);

            if (! $user) {
                return;
            }

            $this->notifications->notify(
                $user,
                $approved ? Notification::LOAN_CREATED : Notification::LOAN_REJECTED,
                [
                    'loan_id' => $loan->id,
                    'amount'  => $loan->amount,
                    'reason'  => $loan->rejection_reason,
                ]
            );
        } catch (\Throwable $e) {
            Log::error('[Loan] outcome notification failed', [
                'loan_id' => $loan->id,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
