<?php

namespace App\Services\Notifications;

interface WhatsAppGateway
{
    /**
     * @return bool true when the provider accepted the message
     */
    public function send(string $phone, string $message): bool;
}
