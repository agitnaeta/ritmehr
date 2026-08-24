<?php

namespace App\Services\Notifications;

use Illuminate\Support\Facades\Log;

/**
 * Default gateway — writes to the log instead of calling a provider.
 *
 * This keeps WhatsApp notifications harmless until real credentials are
 * configured, rather than throwing or silently pretending to have sent.
 */
class LogWhatsAppGateway implements WhatsAppGateway
{
    public function send(string $phone, string $message): bool
    {
        Log::info('[WhatsApp:not-configured] would send message', [
            'to'      => $phone,
            'message' => $message,
        ]);

        return false;
    }
}
