<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

/**
 * M13 — Resolve the active locale for each request.
 *
 * Priority: authenticated user's saved preference → session choice →
 * platform default (M15 setting) → config('app.locale').
 */
class SetLocale
{
    /** Locales the UI actually ships translations for. */
    public const SUPPORTED = ['id', 'en'];

    public function handle(Request $request, Closure $next)
    {
        $locale = $this->resolve($request);

        if (in_array($locale, self::SUPPORTED, true)) {
            App::setLocale($locale);
        }

        return $next($request);
    }

    private function resolve(Request $request): string
    {
        $user = $request->user() ?? (backpack_auth()->check() ? backpack_user() : null);
        if ($user && ! empty($user->locale)) {
            return $user->locale;
        }

        if ($session = $request->session()->get('locale')) {
            return $session;
        }

        return (string) setting('default_locale', config('app.locale', 'id'));
    }
}
