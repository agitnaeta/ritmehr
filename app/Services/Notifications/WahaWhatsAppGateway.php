<?php

namespace App\Services\Notifications;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * WAHA (https://waha.devlike.pro) — self-hosted WhatsApp HTTP API, run as our
 * own container. Preferred over third-party gateways so messaging stays
 * in-house (no external SaaS dependency).
 *
 * Sends via POST {base}/api/sendText with an optional X-Api-Key header.
 */
class WahaWhatsAppGateway implements WhatsAppGateway
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $session = 'default',
        private readonly string $apiKey = '',
    ) {
    }

    public function send(string $phone, string $message): bool
    {
        $endpoint = rtrim($this->baseUrl, '/') . '/api/sendText';

        $headers = ['Accept' => 'application/json'];
        if ($this->apiKey !== '') {
            $headers['X-Api-Key'] = $this->apiKey;
        }

        try {
            $response = Http::withHeaders($headers)
                ->timeout(15)
                ->post($endpoint, [
                    'session' => $this->session ?: 'default',
                    'chatId'  => $this->chatId($phone),
                    'text'    => $message,
                ]);

            if ($response->successful()) {
                return true;
            }

            Log::warning('[WhatsApp:waha] send failed', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
        } catch (\Throwable $e) {
            // A gateway outage must never break the action that triggered it.
            Log::error('[WhatsApp:waha] exception', ['message' => $e->getMessage()]);
        }

        return false;
    }

    /**
     * WAHA expects "<number>@c.us". Indonesian numbers come in as 08xx / +628xx
     * / 628xx — normalise to the 62 form, then append the WhatsApp suffix.
     */
    private function chatId(string $phone): string
    {
        // Already a full chat/group id? leave it alone.
        if (str_contains($phone, '@')) {
            return $phone;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($digits, '0')) {
            $digits = '62' . substr($digits, 1);
        }

        return $digits . '@c.us';
    }
}
