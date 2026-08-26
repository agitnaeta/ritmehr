<?php

use App\Services\CurrencyService;
use App\Services\SettingService;

if (! function_exists('setting')) {
    /**
     * M15 — Read a platform setting managed by the super admin.
     *
     * Falls back to config()/.env when no value has been saved yet, so the app
     * keeps working during the migration away from environment files.
     */
    function setting(string $key, mixed $default = null): mixed
    {
        return app(SettingService::class)->get($key, $default);
    }
}

if (! function_exists('money')) {
    /**
     * M14 — Format an amount using the platform's active currency (M15 setting).
     * e.g. IDR → "Rp 1.500.000", USD → "$1,500.00".
     */
    function money(mixed $amount, ?string $code = null): string
    {
        return app(CurrencyService::class)->format($amount, $code);
    }
}

