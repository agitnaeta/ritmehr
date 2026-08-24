<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Models\User;
use App\Services\ApprovalService;
use App\Services\NotificationService;
use Illuminate\Console\Command;

/**
 * Weekly nudge to anyone sitting on pending approvals.
 *
 * Only users with something actually waiting on them are notified, so an empty
 * queue produces no noise.
 */
class SendApprovalDigest extends Command
{
    protected $signature = 'notify:approval-digest';

    protected $description = 'Send each approver a digest of the requests waiting on them';

    public function handle(ApprovalService $approvals, NotificationService $notifications): int
    {
        // Anyone who could plausibly be an approver: a manager of someone, or
        // a holder of a role used in a flow. Checking every employed user is
        // cheap enough here and avoids missing specific-user steps.
        $candidates = User::employed()->with('roles')->get();

        $sent = 0;

        foreach ($candidates as $user) {
            $pending = $approvals->getPendingForUser($user);

            if ($pending->isEmpty()) {
                continue;
            }

            $notifications->notify($user, Notification::APPROVAL_DIGEST, [
                'count' => $pending->count(),
            ]);

            $sent++;
        }

        $this->info("Approval digest sent to {$sent} user(s).");

        return self::SUCCESS;
    }
}
