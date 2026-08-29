<?php

namespace App\Services\Matching;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * M17-4 — Thin client for Qdrant (vector DB). Server-side credentials come from
 * M15 settings (qdrant_url, qdrant_api_key). Every method degrades gracefully:
 * a failure logs and returns a safe empty/false value so the business flow
 * (applying, listing applicants) is never blocked by a vector-store outage.
 */
class QdrantService
{
    public function baseUrl(): string
    {
        return rtrim((string) (setting('qdrant_url') ?: config('services.matching.qdrant_url')), '/');
    }

    private function apiKey(): ?string
    {
        return setting('qdrant_api_key') ?: config('services.matching.qdrant_api_key');
    }

    private function http()
    {
        $req = Http::timeout(10)->acceptJson();
        if ($key = $this->apiKey()) {
            $req = $req->withHeaders(['api-key' => $key]);
        }
        return $req;
    }

    /** Is Qdrant reachable? Used by the "Test Connection" button and fallbacks. */
    public function isUp(): bool
    {
        try {
            return $this->http()->get($this->baseUrl() . '/')->successful();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Create the collection if it does not exist (idempotent). */
    public function ensureCollection(string $collection, int $dim, string $distance = 'Cosine'): bool
    {
        try {
            $exists = $this->http()->get($this->baseUrl() . "/collections/{$collection}");
            if ($exists->successful()) {
                return true;
            }

            $res = $this->http()->put($this->baseUrl() . "/collections/{$collection}", [
                'vectors' => ['size' => $dim, 'distance' => $distance],
            ]);

            return $res->successful();
        } catch (\Throwable $e) {
            Log::channel('daily_log')->warning('Qdrant ensureCollection failed: ' . $e->getMessage());
            return false;
        }
    }

    /** Upsert a single point (id, vector, payload). */
    public function upsert(string $collection, int $id, array $vector, array $payload = []): bool
    {
        try {
            $res = $this->http()->put($this->baseUrl() . "/collections/{$collection}/points", [
                'points' => [[
                    'id'      => $id,
                    'vector'  => $vector,
                    'payload' => $payload,
                ]],
            ]);
            return $res->successful();
        } catch (\Throwable $e) {
            Log::channel('daily_log')->warning('Qdrant upsert failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Search nearest points to $vector, optionally filtered by payload equality.
     * Returns array of ['id' => int, 'score' => float, 'payload' => array].
     */
    public function search(string $collection, array $vector, int $limit = 30, array $mustMatch = []): array
    {
        try {
            $body = [
                'vector'       => $vector,
                'limit'        => $limit,
                'with_payload' => true,
            ];

            if ($mustMatch) {
                $body['filter'] = [
                    'must' => array_map(
                        fn ($k, $v) => ['key' => $k, 'match' => ['value' => $v]],
                        array_keys($mustMatch),
                        array_values($mustMatch)
                    ),
                ];
            }

            $res = $this->http()->post($this->baseUrl() . "/collections/{$collection}/points/search", $body);
            if (! $res->successful()) {
                return [];
            }

            return $res->json('result', []);
        } catch (\Throwable $e) {
            Log::channel('daily_log')->warning('Qdrant search failed: ' . $e->getMessage());
            return [];
        }
    }

    /** Delete a single point by id. */
    public function delete(string $collection, int $id): bool
    {
        try {
            $res = $this->http()->post($this->baseUrl() . "/collections/{$collection}/points/delete", [
                'points' => [$id],
            ]);
            return $res->successful();
        } catch (\Throwable $e) {
            Log::channel('daily_log')->warning('Qdrant delete failed: ' . $e->getMessage());
            return false;
        }
    }
}
