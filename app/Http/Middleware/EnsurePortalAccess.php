<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards /my/*. Every authenticated user has a portal — admins included, since
 * they are employees too and have their own payslips and leave.
 */
class EnsurePortalAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (backpack_auth()->guest()) {
            if ($request->ajax() || $request->wantsJson()) {
                return response('Unauthorized', 401);
            }

            return redirect()->guest(backpack_url('login'));
        }

        return $next($request);
    }
}
