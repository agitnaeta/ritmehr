<?php

namespace App\Services;

use App\Mail\NotificationMail;
use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Notifications\NotificationTemplates;
use App\Services\Notifications\WhatsAppGateway;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Fan-out for in-app / email / WhatsApp notifications.
 *
 * Delivery never throws: a notification is a side effect of some other action
 * (approving leave, running payroll) and a mail or gateway failure must not
 * roll that action back. Failures are logged and the database row is still
 * written so nothing is silently lost.
 */
class NotificationService
{
    public function __construct(private readonly WhatsAppGateway $whatsapp)
    {
    }

    /**
     * Send $type to one recipient across whichever channels they've opted into.
     */
    public function notify(User $recipient, string $type, array $data = []): ?Notification
    {
        $rendered = NotificationTemplates::render($type, $data);
        $channels = NotificationPreference::channelsFor($recipient->id, $type);

        // The in-app record is always written — it is the audit trail for the
        // notification itself, regardless of which channels were requested.
        $notification = Notification::create([
            'user_id' => $recipient->id,
            'type'    => $type,
            'title'   => $rendered['title'],
            'body'    => $rendered['body'],
            'data'    => $data,
            'channel' => Notification::CHANNEL_DATABASE,
            'sent_at' => now(),
        ]);

        if (in_array(Notification::CHANNEL_EMAIL, $channels, true)) {
            $this->sendEmail($recipient, $rendered, $notification->url());
        }

        if (in_array(Notification::CHANNEL_WHATSAPP, $channels, true)) {
            $this->sendWhatsApp($recipient, $rendered);
        }

        return $notification;
    }

    /**
     * Notify every user holding $role. Returns how many were reached.
     */
    public function notifyRole(string $role, string $type, array $data = []): int
    {
        $users = User::employed()
            ->whereHas('roles', fn ($q) => $q->where('name', $role))
            ->get();

        foreach ($users as $user) {
            $this->notify($user, $type, $data);
        }

        return $users->count();
    }

    /**
     * @param  iterable<User>  $recipients
     */
    public function notifyMany(iterable $recipients, string $type, array $data = []): int
    {
        $count = 0;

        foreach ($recipients as $recipient) {
            if ($recipient instanceof User) {
                $this->notify($recipient, $type, $data);
                $count++;
            }
        }

        return $count;
    }

    // ── Reading ────────────────────────────────────────────

    public function getUnread(User $user, int $limit = 10): Collection
    {
        return Notification::forUser($user->id)
            ->unread()
            ->latest()
            ->limit($limit)
            ->get();
    }

    public function unreadCount(User $user): int
    {
        return Notification::forUser($user->id)->unread()->count();
    }

    public function markAsRead(Notification $notification): void
    {
        if (! $notification->isRead()) {
            $notification->update(['read_at' => now()]);
        }
    }

    public function markAllRead(User $user): int
    {
        return Notification::forUser($user->id)->unread()->update(['read_at' => now()]);
    }

    // ── Channel implementations ────────────────────────────

    private function sendEmail(User $recipient, array $rendered, ?string $url = null): void
    {
        if (! $recipient->email) {
            return;
        }

        try {
            Mail::to($recipient->email)->send(
                new NotificationMail($rendered['title'], $rendered['body'], $url)
            );
        } catch (\Throwable $e) {
            Log::error('[Notification] email delivery failed', [
                'user_id' => $recipient->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function sendWhatsApp(User $recipient, array $rendered): void
    {
        if (! $recipient->phone) {
            return;
        }

        try {
            $this->whatsapp->send(
                $recipient->phone,
                "*{$rendered['title']}*\n\n{$rendered['body']}"
            );
        } catch (\Throwable $e) {
            Log::error('[Notification] whatsapp delivery failed', [
                'user_id' => $recipient->id,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
