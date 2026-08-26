<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * M17 — Guards candidate-only routes (dashboard, apply). Redirects guests to
 * the candidate login, keeping the intended URL.
 */
class EnsureCandidate
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::guard('candidate')->check()) {
            if ($request->ajax() || $request->wantsJson()) {
                return response('Unauthorized', 401);
            }

            return redirect()->guest(route('career.login'));
        }

        return $next($request);
    }
}
