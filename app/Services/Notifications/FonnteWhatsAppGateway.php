<?php

namespace App\Services\Notifications;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Fonnte (https://fonnte.com) — a common Indonesian WhatsApp gateway.
 *
 * Enabled by setting FONNTE_TOKEN; see AppServiceProvider for the binding.
 */
class FonnteWhatsAppGateway implements WhatsAppGateway
{
    public function __construct(
        private readonly string $token,
        private readonly string $endpoint = 'https://api.fonnte.com/send',
    ) {
    }

    public function send(string $phone, string $message): bool
    {
        try {
            $response = Http::withHeaders(['Authorization' => $this->token])
                ->timeout(10)
                ->asForm()
                ->post($this->endpoint, [
                    'target'  => $this->normalisePhone($phone),
                    'message' => $message,
                ]);

            if ($response->successful()) {
                return true;
            }

            Log::warning('[WhatsApp:fonnte] send failed', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
        } catch (\Throwable $e) {
            // A gateway outage must never break the action that triggered it.
            Log::error('[WhatsApp:fonnte] exception', ['message' => $e->getMessage()]);
        }

        return false;
    }

    /**
     * Indonesian numbers are stored inconsistently (08xx, +628xx, 628xx);
     * Fonnte expects the 62 form without a plus.
     */
    private function normalisePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($digits, '0')) {
            return '62' . substr($digits, 1);
        }

        return $digits;
    }
}
