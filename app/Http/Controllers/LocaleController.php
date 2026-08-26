<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use Illuminate\Http\Request;

/**
 * M13 — Switch the UI language. Persists to the logged-in user (so it sticks
 * across devices) and to the session (for immediate effect / guests).
 */
class LocaleController extends Controller
{
    public function switch(Request $request, string $locale)
    {
        abort_unless(in_array($locale, SetLocale::SUPPORTED, true), 404);

        $request->session()->put('locale', $locale);

        $user = $request->user() ?? (backpack_auth()->check() ? backpack_user() : null);
        if ($user) {
            $user->locale = $locale;
            $user->save();
        }

        return redirect()->back();
    }
}
