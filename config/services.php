<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
    | Accounting sync (external ledger integration, e.g. Firefly III). Managed
    | via the M15 Settings UI at runtime; these are just the .env fallbacks used
    | before a value is saved to the settings table.
    */
    'acc' => [
        'active' => env('ACC_ACTIVE', false),
        'host'   => env('ACC_HOST'),
        'key'    => env('ACC_KEY'),
    ],

    /*
    | AI recruitment matching (M17) — Qdrant vector store + OpenAI-compatible
    | LLM/embedding endpoints. Managed via M15 Settings UI at runtime; these are
    | the .env fallbacks. Dibaca via config() (bukan env() runtime) supaya aman
    | terhadap `php artisan config:cache` di produksi. (Ref: CFG-01/BP-1)
    */
    'matching' => [
        'qdrant_url'         => env('QDRANT_URL', 'http://localhost:6333'),
        'qdrant_api_key'     => env('QDRANT_API_KEY'),
        'llm_base_url'       => env('LLM_BASE_URL', 'http://localhost:20128/v1'),
        'llm_api_key'        => env('LLM_API_KEY'),
        'embedding_base_url' => env('EMBEDDING_BASE_URL', 'http://localhost:20128/v1'),
        'embedding_api_key'  => env('EMBEDDING_API_KEY'),
    ],

    /*
    | CV text extraction (M17-3) — path ke interpreter python untuk extractor.
    */
    'cv' => [
        'python_bin' => env('PYTHON_BIN', 'python3'),
    ],

];
