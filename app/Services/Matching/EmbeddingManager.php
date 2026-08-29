<?php

namespace App\Services\Matching;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * M17-4 — Pluggable embedding provider (OpenAI-compatible /v1/embeddings).
 *
 * Providers (chosen in M15 Settings, keputusan Capt):
 *   - openai : official OpenAI embeddings
 *   - custom : any OpenAI-compatible base URL (Ollama, vLLM, gateway, etc.)
 *
 * Graceful: returns null on any failure so the pipeline can fall back to manual
 * ordering instead of breaking the apply/list flow.
 */
class EmbeddingManager
{
    public function provider(): string
    {
        return (string) (setting('embedding_provider') ?: 'custom');
    }

    public function model(): string
    {
        return (string) (setting('embedding_model') ?: 'text-embedding-3-small');
    }

    public function baseUrl(): string
    {
        $default = $this->provider() === 'openai'
            ? 'https://api.openai.com/v1'
            : (string) config('services.matching.embedding_base_url');

        return rtrim((string) (setting('embedding_base_url') ?: $default), '/');
    }

    private function apiKey(): ?string
    {
        return setting('embedding_api_key') ?: config('services.matching.embedding_api_key');
    }

    /**
     * Embed a single string. Returns a float vector or null on failure.
     */
    public function embed(string $text): ?array
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }

        try {
            $req = Http::timeout(30)->acceptJson();
            if ($key = $this->apiKey()) {
                $req = $req->withToken($key);
            }

            $res = $req->post($this->baseUrl() . '/embeddings', [
                'model' => $this->model(),
                'input' => $text,
            ]);

            if (! $res->successful()) {
                Log::channel('daily_log')->warning('Embedding failed (' . $res->status() . '): ' . $res->body());
                return null;
            }

            $vector = $res->json('data.0.embedding');

            return is_array($vector) && $vector !== [] ? $vector : null;
        } catch (\Throwable $e) {
            Log::channel('daily_log')->warning('Embedding exception: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Probe the provider with a short string and report result — for the
     * "Test Embedding" button in Settings. Returns [ok, dim, message].
     */
    public function testConnection(): array
    {
        $vec = $this->embed('software engineer laravel test');
        if ($vec === null) {
            return ['ok' => false, 'dim' => 0, 'message' => 'Gagal memanggil provider embedding. Cek URL/API key/model.'];
        }

        return ['ok' => true, 'dim' => count($vec), 'message' => 'Berhasil. Dimensi vektor: ' . count($vec)];
    }
}
