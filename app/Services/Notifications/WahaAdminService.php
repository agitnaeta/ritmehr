<?php

namespace App\Services\Notifications;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * M03b — Admin-side control of a WAHA (self-hosted) WhatsApp session so the
 * whole connect/scan/logout flow can live inside our own layout, never the WAHA
 * dashboard.
 *
 * This runs server-side only: the base URL and API key stay here, the browser
 * only ever talks to our own routes (see WahaController).
 */
class WahaAdminService
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $session = 'default',
        private readonly string $apiKey = '',
    ) {
    }

    /** Build from the M15 settings; null when WAHA base URL is not configured. */
    public static function fromSettings(): ?self
    {
        $url = (string) setting('waha_url', '');
        if ($url === '') {
            return null;
        }

        return new self(
            $url,
            (string) setting('waha_session', 'default') ?: 'default',
            (string) setting('waha_api_key', '')
        );
    }

    /**
     * Normalised session status.
     *
     * @return array{reachable:bool, state:string, connected:bool, me:?array, error:?string}
     */
    public function status(): array
    {
        try {
            $res = $this->client()->get($this->url("/api/sessions/{$this->session}"));

            if ($res->status() === 404) {
                // Session not created yet.
                return $this->shape('STOPPED', false);
            }

            if (! $res->successful()) {
                return $this->shape('UNKNOWN', false, null, "HTTP {$res->status()}");
            }

            $data = $res->json();
            $state = strtoupper((string) ($data['status'] ?? 'UNKNOWN'));
            $me = $data['me'] ?? null;

            return $this->shape($state, $state === 'WORKING', $me);
        } catch (\Throwable $e) {
            Log::warning('[WAHA:admin] status failed', ['message' => $e->getMessage()]);

            return $this->shape('UNREACHABLE', false, null, 'Tidak dapat terhubung ke server WAHA.', false);
        }
    }

    /** Ensure the session exists and is started. Returns true on success. */
    public function start(): bool
    {
        try {
            // Idempotent: create if missing (ignore "already exists"), then start.
            $this->client()->post($this->url('/api/sessions'), [
                'name'  => $this->session,
                'start' => true,
            ]);

            $res = $this->client()->post($this->url("/api/sessions/{$this->session}/start"));

            return $res->successful() || $res->status() === 422; // 422 = already started
        } catch (\Throwable $e) {
            Log::warning('[WAHA:admin] start failed', ['message' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Raw QR image bytes (image/png) for the current session, or null if not
     * available (e.g. already connected or WAHA unreachable).
     *
     * @return array{body:string, contentType:string}|null
     */
    public function qr(): ?array
    {
        try {
            // WAHA honours the Accept header over ?format, so ask for an image
            // explicitly — the shared client() sends Accept: application/json.
            $headers = ['Accept' => 'image/png'];
            if ($this->apiKey !== '') {
                $headers['X-Api-Key'] = $this->apiKey;
            }

            $res = Http::withHeaders($headers)
                ->timeout(15)
                ->get($this->url("/api/{$this->session}/auth/qr"), ['format' => 'image']);

            if (! $res->successful()) {
                return null;
            }

            $type = $res->header('Content-Type') ?: 'image/png';

            // If WAHA still returned JSON (e.g. session not in SCAN state), the
            // caller has no image to show.
            if (str_contains($type, 'application/json')) {
                return null;
            }

            return [
                'body'        => $res->body(),
                'contentType' => $type,
            ];
        } catch (\Throwable $e) {
            Log::warning('[WAHA:admin] qr failed', ['message' => $e->getMessage()]);

            return null;
        }
    }

    /** Log the WhatsApp account out of the session. */
    public function logout(): bool
    {
        try {
            $res = $this->client()->post($this->url("/api/sessions/{$this->session}/logout"));

            return $res->successful();
        } catch (\Throwable $e) {
            Log::warning('[WAHA:admin] logout failed', ['message' => $e->getMessage()]);

            return false;
        }
    }

    // ── internals ──────────────────────────────────────────

    private function shape(string $state, bool $connected, ?array $me = null, ?string $error = null, bool $reachable = true): array
    {
        return [
            'reachable' => $reachable,
            'state'     => $state,
            'connected' => $connected,
            'me'        => $me,
            'error'     => $error,
        ];
    }

    private function client()
    {
        $headers = ['Accept' => 'application/json'];
        if ($this->apiKey !== '') {
            $headers['X-Api-Key'] = $this->apiKey;
        }

        return Http::withHeaders($headers)->timeout(15);
    }

    private function url(string $path): string
    {
        return rtrim($this->baseUrl, '/') . $path;
    }
}
